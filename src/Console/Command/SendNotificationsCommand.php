<?php

declare(strict_types=1);

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Sender;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Site\Service as SiteService;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Generator;

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

    private int $deliveryRetries;

    private DateTimeImmutable $timeLimit;

    private ?int $minPriority;

    public function handle(Sender $sender, SiteService $siteService, EntityManager $entityManager): int
    {
        $errorsOccurred = false;
        $this->readOptions();
        $this->checkCanonicalURL($siteService);
        foreach ($this->listNotifications($entityManager->getRepository(NotificationEntity::class)) as $notification) {
            $this->logger->debug(sprintf('Processing notification %s', $notification->getID()));
            $sendError = $sender->send($notification);
            if ($sendError !== null) {
                $this->logger->error($this->formatThrowable($sendError));
                $errorsOccurred = true;
            }
        }

        return $errorsOccurred ? static::FAILURE : static::SUCCESS;
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
        if ($deliveryRetries <= 0) {
            throw new UserMessageException('Invalid value of the retries parameter (it must be a positive integer)');
        }
        $this->deliveryRetries = $deliveryRetries;
        $maxAge = $this->input->getOption('max-age');
        $maxAge = is_numeric($maxAge) ? (int) $maxAge : -1;
        if ($maxAge <= 0) {
            throw new UserMessageException('Invalid value of the max-age parameter (it must be an integer greater than 0)');
        }
        $this->timeLimit = new DateTimeImmutable("-{$maxAge} days");
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
    private function checkCanonicalURL(SiteService $siteService): void
    {
        $site = $siteService->getSite();
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
    private function listNotifications(NotificationRepository $repo): Generator
    {
        $lastID = null;
        $expr = Criteria::expr();
        for (;;) {
            $criteria = Criteria::create()
                ->where($expr->isNull('sentOn'))
                ->andWhere($expr->lt('deliveryAttempts', $this->deliveryRetries))
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
            $notification = $repo->matching($criteria)->first();
            if ($notification === null || $notification === false) {
                return;
            }
            yield $notification;
            $lastID = $notification->getID();
        }
    }
}
