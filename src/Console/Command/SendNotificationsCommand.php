<?php

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\CategoryInterface;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use Concrete\Core\User\UserInfo;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Exception;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class SendNotificationsCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see Command::RETURN_CODE_ON_FAILURE
     */
    const RETURN_CODE_ON_FAILURE = 3;

    /**
     * The default number of retries in case of network problems.
     *
     * @var int
     */
    const DEFAULT_DELIVERY_RETRIES = 3;

    /**
     * The default maximum age (in days) of the unsent notifications to be parsed.
     * Unsent notifications older than this time won't be sent.
     *
     * @var int
     */
    const DEFAULT_MAX_AGE = 3;

    protected function configure()
    {
        $errExitCode = static::RETURN_CODE_ON_FAILURE;
        $this
            ->setName('ct:send-notifications')
            ->setDescription('Send pending notifications')
            ->addOption('retries', 'r', InputOption::VALUE_REQUIRED, 'The number of network failures before giving up with a notification', static::DEFAULT_DELIVERY_RETRIES)
            ->addOption('max-age', 'a', InputOption::VALUE_REQUIRED, 'The maximum age (in days) of the unsent notifications to be parsed', static::DEFAULT_MAX_AGE)
            ->addOption('priority', 'p', InputOption::VALUE_REQUIRED, 'The miminum priority of the notifications to be parsed')
            ->setHelp(<<<EOT
This command send notifications about events of Community Translations, like requests for new translation teams and new translators.

Returns codes:
  0 no notification has been sent
  1 some notification has been sent
  2 errors occurred but some notification has been sent
  $errExitCode errors occurred and no notification has been sent
EOT
            )
        ;
    }

    /**
     * @var EntityManager|null
     */
    private $em;

    /**
     * @var NotificationRepository|null
     */
    private $repo;

    /**
     * @var \Concrete\Core\Mail\Service|null
     */
    private $mail;

    /**
     * @var CategoryInterface[]|null
     */
    private $categories;

    /**
     * @var string[]|null
     */
    private $from;

    /**
     * @var bool|null
     */
    private $someNotificationSent;

    /**
     * @var bool|null
     */
    private $someNotificationFailed;

    /**
     * @var int|null
     */
    private $deliveryRetries;

    /**
     * @var string|null
     */
    private $timeLimit;

    /**
     * @var int|null
     */
    private $minPriority;

    protected function executeWithLogger()
    {
        $this->initializeState();
        $this->readParameters();
        $this->checkCanonicalURL();
        if ($this->acquireLock(20) === false) {
            throw new Exception('Failed to acquire lock');
        }
        $lastID = null;
        for (; ;) {
            $criteria = Criteria::create()
                ->where(Criteria::expr()->isNull('sentOn'))
                ->andWhere(Criteria::expr()->gte('updatedOn', $this->timeLimit))
                ->orderBy(['id' => 'ASC'])
                ->setMaxResults(1);
            if ($lastID !== null) {
                $criteria->andWhere(Criteria::expr()->gt('id', $lastID));
            }
            if ($this->minPriority !== null) {
                $criteria->andWhere(Criteria::expr()->gte('priority', $this->minPriority));
            }
            $notification = $this->repo->matching($criteria)->first();
            if (!$notification) {
                break;
            }
            $lastID = $notification->getID();
            $this->logger->debug(sprintf('Processing notification %s', $notification->getID()));
            $this->processNotification($notification);
        }
        $this->releaseLock();
        if ($this->someNotificationSent && $this->someNotificationFailed) {
            $rc = 2;
        } elseif ($this->someNotificationSent) {
            $rc = 1;
        } elseif ($this->someNotificationFailed) {
            $rc = static::RETURN_CODE_ON_FAILURE;
        } else {
            $rc = 0;
        }

        return $rc;
    }

    private function initializeState()
    {
        $this->em = $this->app->make(EntityManager::class);
        $this->repo = $this->app->make(NotificationRepository::class);
        $this->mail = $this->app->make('mail');
        $this->categories = [];
        $ctConfig = $this->app->make('community_translation/config');
        $fromEmail = (string) $ctConfig->get('options.notificationsSenderAddress');
        if ($fromEmail !== '') {
            $this->from = [$fromEmail, $ctConfig->get('options.notificationsSenderName') ?: null];
        } else {
            $config = $this->app->make('config');
            $this->from = [$config->get('concrete.email.default.address'), $config->get('concrete.email.default.name') ?: null];
        }
        $this->someNotificationSent = false;
        $this->someNotificationFailed = false;
    }

    private function readParameters()
    {
        $deliveryRetries = $this->input->getOption('retries');
        $deliveryRetries = is_numeric($deliveryRetries) ? (int) $deliveryRetries : -1;
        if ($deliveryRetries < 0) {
            throw new Exception('Invalid value of the retries parameter (it must be a non negative integer)');
        }
        $this->deliveryRetries = $deliveryRetries;

        $maxAge = (int) $this->input->getOption('max-age');
        if ($maxAge <= 0) {
            throw new Exception('Invalid value of the max-age parameter (it must be an integer greater than 0)');
        }
        $this->timeLimit = new DateTime("-$maxAge days");
        $p = $this->input->getOption('priority');
        if ($p === null) {
            $this->minPriority = null;
        } else {
            $this->minPriority = @(int) $p;
            if ((string) $this->minPriority !== (string) $p) {
                throw new Exception('Invalid value of the priority parameter (it must be an integer)');
            }
        }
    }

    private function checkCanonicalURL()
    {
        $site = $this->app->make('site')->getSite();
        if (!$site->getSiteCanonicalURL()) {
            throw new Exception('The site canonical URL must be set in order to run this command.');
        }
    }

    /**
     * @param string $fqnClass
     *
     * @throws Exception
     *
     * @return CategoryInterface
     */
    private function getNotificationCategory(NotificationEntity $notification)
    {
        $fqnClass = $notification->getFQNClass();
        if (!isset($this->categories[$fqnClass])) {
            if (!class_exists($fqnClass, true)) {
                $this->categories[$fqnClass] = sprintf('Unable to find the category class %s', $fqnClass);
            } else {
                $obj = null;
                $error = null;
                try {
                    $obj = $this->app->make($fqnClass);
                } catch (Exception $x) {
                    $error = $x;
                } catch (Throwable $x) {
                    $error = $x;
                }
                if ($error !== null) {
                    $this->categories[$fqnClass] = sprintf('Failed to initialize category class %1$s: %2$s', $fqnClass, $error->getMessage());
                } elseif (!($obj instanceof CategoryInterface)) {
                    $this->categories[$fqnClass] = sprintf('The class %1$s does not implement %2$s', $fqnClass, CategoryInterface::class);
                } else {
                    $this->categories[$fqnClass] = $obj;
                }
            }
        }
        if (is_string($this->categories[$fqnClass])) {
            throw new Exception($this->categories[$fqnClass]);
        }

        return $this->categories[$fqnClass];
    }

    /**
     * @param NotificationEntity $notification
     */
    private function processNotification(NotificationEntity $notification)
    {
        // Let's mark the notification as sent, so that it won't be updated with new messages
        $notification->setSentOn(new DateTime());
        $this->em->persist($notification);
        $this->em->flush($notification);
        $notification
            ->setSentCountPotential(0)
            ->setSentCountActual(0);
        $someNetworkProblems = false;
        $error = null;
        try {
            $category = $this->getNotificationCategory($notification);
            $numRecipientsTotal = 0;
            $numRecipientsOk = 0;
            foreach ($category->getRecipients($notification) as $recipient) {
                ++$numRecipientsTotal;
                $notification->setSentCountPotential($numRecipientsTotal);
                $this->prepareMail($notification, $recipient, $category);
                if ($this->mail->sendMail()) {
                    ++$numRecipientsOk;
                    $notification->setSentCountActual($numRecipientsOk);
                } else {
                    $someNetworkProblems = true;
                }
            }
            if ($someNetworkProblems) {
                throw new Exception($numRecipientsOk === 0 ? 'Mail service failed for all recipients' : 'Mail service failed for some recipients');
            }
        } catch (Exception $x) {
            $error = $x;
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
                    ->setSentOn(null);
            }
        }
        $this->em->persist($notification);
        $this->em->flush($notification);
        if ($error !== null) {
            $this->logger->error($this->formatThrowable($error));
        }
    }

    private function prepareMail(NotificationEntity $notification, UserInfo $recipient, CategoryInterface $category)
    {
        $this->mail->reset();
        $this->mail->from($this->from[0], $this->from[1]);
        $this->mail->to($recipient->getUserEmail(), $recipient->getUserName());
        foreach ($category->getMailParameters($notification, $recipient) as $key => $value) {
            $this->mail->addParameter($key, $value);
        }
        $tp = $category->getMailTemplate();
        $this->mail->load($tp[0], $tp[1]);
    }
}
