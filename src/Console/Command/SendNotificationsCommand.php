<?php

declare(strict_types=1);

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Console\Command\SendNotificationsCommand\State;
use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\CategoryInterface;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use Concrete\Core\Application\Application;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Mail\Service as MailService;
use Concrete\Core\Site\Service as SiteService;
use Concrete\Core\User\UserInfo;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Generator;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class SendNotificationsCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
ct:send-notifications
    {--r|retries=3 : The number of network failures thay may occur before giving up with a notification }
    {--a|max-age=3 : The maximum age (in days) of the unsent notifications to be processed }
    {--p|priority= : The miminum priority of the notifications to be processed (if not specified we'll process all the notifications) }
EOT
    ;

    private Application $categoryBuilder;

    private Repository $config;

    private MailService $mailService;

    private SiteService $siteService;

    private EntityManager $em;

    private NotificationRepository $repo;

    private int $deliveryRetries;

    private string $sqlTimeLimit;

    private ?int $minPriority;

    public function handle(Application $categoryBuilder, Repository $config, MailService $mailService, SiteService $siteService, EntityManager $em): int
    {
        $state = new State($config);
        $mutexReleaser = null;
        $this->createLogger();
        try {
            $mutexReleaser = $this->acquireMutex();
            $this->categoryBuilder = $categoryBuilder;
            $this->mailService = $mailService;
            $this->siteService = $siteService;
            $this->em = $em;
            $this->repo = $em->getRepository(NotificationEntity::class);
            $this->readOptions();
            $this->checkCanonicalURL();
            foreach ($this->listNotifications() as $notification) {
                $this->processNotification($state, $notification);
            }
        } catch (Throwable $x) {
            $this->logger->error($this->formatThrowable($x));
            $state->someNotificationFailed = true;
        } finally {
            if ($mutexReleaser !== null) {
                try {
                    $mutexReleaser();
                } catch (Throwable $x) {
                }
            }
        }
        if ($state->someNotificationFailed) {
            return $state->someNotificationSent ? 2 : 3;
        }

        return $state->someNotificationSent ? 1 : 0;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::configureUsingFluentDefinition()
     */
    protected function configureUsingFluentDefinition()
    {
        parent::configureUsingFluentDefinition();
        $this
            ->setDescription('Send pending notifications')
            ->setHelp(
                <<<'EOT'
This command send notifications about events of Community Translations, like requests for new translation teams and new translators.

Returns codes:
  0 no notification has been sent
  1 some notification has been sent
  2 errors occurred but some notification has been sent
  3 errors occurred and no notification has been sent
EOT
            )
        ;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function readOptions(): void
    {
        $deliveryRetries = $this->input->getOption('retries');
        $deliveryRetries = is_numeric($deliveryRetries) ? (int) $deliveryRetries : -1;
        if ($deliveryRetries < 0) {
            throw new UserMessageException('Invalid value of the retries parameter (it must be a non negative integer)');
        }
        $this->deliveryRetries = $deliveryRetries;
        $maxAge = $this->input->getOption('max-age');
        $maxAge = is_numeric($maxAge) ? (int) $maxAge : -1;
        if ($maxAge <= 0) {
            throw new UserMessageException('Invalid value of the max-age parameter (it must be an integer greater than 0)');
        }
        $timeLimit = new DateTimeImmutable("-{$maxAge} days");
        $this->sqlTimeLimit = $timeLimit->format($this->em->getConnection()->getDatabasePlatform()->getDateTimeFormatString());
        $minPriority = $this->input->getOption('priority');
        if ($minPriority !== null) {
            if (!is_numeric($minPriority)) {
                throw new UserMessageException('Invalid value of the priority parameter (it must be an integer)');
            }
            $minPriority = (int) $minPriority;
        }
        $this->minPriority = $minPriority;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function checkCanonicalURL(): void
    {
        $site = $this->siteService->getSite();
        if ($site === null) {
            throw new UserMessageException('Failed to get the Site object.');
        }
        if ($site->getSiteCanonicalURL() === '') {
            throw new UserMessageException('The site canonical URL must be set in order to run this command.');
        }
    }

    /**
     * @return \CommunityTranslation\Entity\Notification[]
     */
    private function listNotifications(): Generator
    {
        $lastID = null;
        $expr = Criteria::expr();
        for (;;) {
            $criteria = Criteria::create()
                ->where($expr->isNull('sentOn'))
                ->andWhere($expr->gte('updatedOn', $this->timeLimit))
                ->orderBy(['id' => 'ASC'])
                ->setMaxResults(1)
            ;
            if ($lastID !== null) {
                $criteria->andWhere($expr->gt('id', $lastID));
            }
            if ($this->minPriority !== null) {
                $criteria->andWhere($expr->gte('priority', $this->minPriority));
            }
            $notification = $this->repo->matching($criteria)->first();
            if ($notification === null || $notification === false) {
                return;
            }
            yield $notification;
            $lastID = $notification->getID();
        }
    }

    private function processNotification(State $state, NotificationEntity $notification): void
    {
        $this->logger->debug(sprintf('Processing notification %s', $notification->getID()));
        // Let's mark the notification as sent, so that it won't be updated with new messages
        $notification->setSentOn(new DateTimeImmutable());
        $this->em->persist($notification);
        $this->em->flush($notification);
        $notification
            ->setSentCountPotential(0)
            ->setSentCountActual(0)
        ;
        $someNetworkProblems = false;
        $error = null;
        try {
            $category = $this->getNotificationCategory($state, $notification);
            $numRecipientsTotal = 0;
            $numRecipientsOk = 0;
            foreach ($category->getRecipients($notification) as $recipient) {
                $numRecipientsTotal++;
                $notification->setSentCountPotential($numRecipientsTotal);
                $this->prepareMailService($state, $notification, $recipient, $category);
                try {
                    $this->mailService->sendMail();
                    $sent = true;
                } catch (Throwable $sendError) {
                    $someNetworkProblems = true;
                    $sent = true;
                }
                if ($sent) {
                    $numRecipientsOk++;
                    $notification->setSentCountActual($numRecipientsOk);
                }
            }
            if ($someNetworkProblems) {
                throw new UserMessageException($numRecipientsOk === 0 ? 'Mail service failed for all recipients' : 'Mail service failed for some recipients');
            }
        } catch (Throwable $x) {
            $error = $x;
        }
        if ($error === null) {
            if ($numRecipientsOk > 0) {
                $this->someNotificationSent = true;
            }
        } else {
            $this->someNotificationFailed = true;
            $notification->addDeliveryError($error->getMessage());
            if ($someNetworkProblems === true && $numRecipientsOk === 0 && $notification->getDeliveryAttempts() < $this->deliveryRetries) {
                // The delivery failed only because of the mail system, for all the recipients.
                // So, if the failure limit is under the threshold, let's mark the notification as not sent
                $notification
                    ->setDeliveryAttempts($notification->getDeliveryAttempts() + 1)
                    ->setSentOn(null)
                ;
            }
        }
        $this->em->persist($notification);
        $this->em->flush($notification);
        if ($error !== null) {
            $this->logger->error($this->formatThrowable($error));
        }
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function getNotificationCategory(State $state, NotificationEntity $notification): CategoryInterface
    {
        $fqnClass = $notification->getFQNClass();
        if (!isset($state->categories[$fqnClass])) {
            if (!class_exists($fqnClass, true)) {
                $state->categories[$fqnClass] = sprintf('Unable to find the category class %s', $fqnClass);
            } else {
                $obj = null;
                $error = null;
                try {
                    $obj = $state->categoryBuilder->make($fqnClass);
                } catch (Throwable $x) {
                    $error = $x;
                }
                if ($error !== null) {
                    $state->categories[$fqnClass] = sprintf('Failed to initialize category class %1$s: %2$s', $fqnClass, $error->getMessage());
                } elseif (!($obj instanceof CategoryInterface)) {
                    $state->categories[$fqnClass] = sprintf('The class %1$s does not implement %2$s', $fqnClass, CategoryInterface::class);
                } else {
                    $state->categories[$fqnClass] = $obj;
                }
            }
        }
        if (is_string($state->categories[$fqnClass])) {
            throw new UserMessageException($state->categories[$fqnClass]);
        }

        return $state->categories[$fqnClass];
    }

    private function prepareMailService(State $state, NotificationEntity $notification, UserInfo $recipient, CategoryInterface $category): void
    {
        $this->mailService->reset();
        $this->mailService->setIsThrowOnFailure(true);
        $this->mailService->from($state->from[0], $state->from[1]);
        $this->mailService->to($recipient->getUserEmail(), $recipient->getUserName());
        foreach ($category->getMailParameters($notification, $recipient) as $key => $value) {
            $this->mailService->addParameter($key, $value);
        }
        $tp = $category->getMailTemplate();
        $this->mailService->load($tp[0], $tp[1]);
    }
}
