<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Localization\Service\Date as DateService;
use Concrete\Core\Page\Controller\DashboardPageController;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use CommunityTranslation\Notification\CategoryInterface;
use CommunityTranslation\Notification\Sender;

defined('C5_EXECUTE') or die('Access Denied.');

class NotificationsLog extends DashboardPageController
{
    private const PAGE_SIZE = 50;

    public function view(): ?Response
    {
        $this->set('categories', $this->getCategories());
        $this->set('notifications', $this->fetchNotifications());
        $this->set('token', $this->token);

        return null;
    }

    public function get_next_page(): Response
    {
        if (!$this->token->validate('comtra-notifications-nextpage')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $id = $this->request->request->get('id');
        $id = is_numeric($id) ? (int) $id : 0;
        if ($id <= 0) {
            $id = null;
        }
        $createdOnDB = $this->request->request->get('createdOnDB');
        if (!is_string($createdOnDB)) {
            $createdOnDB = '';
        }
        $category = $this->request->request->get('category');
        if (!is_string($category)) {
            $category = '';
        }
        if ($id === null) {
            if ($createdOnDB !== '') {
                throw new UserMessageException(t('Invalid parameter received'));
            }
            $lastLoaded = null;
        } else {
            if ($createdOnDB === '') {
                throw new UserMessageException(t('Invalid parameter received'));
            }
            $dbDateTimeFormat = $this->app->make(EntityManagerInterface::class)->getConnection()->getDatabasePlatform()->getDateTimeFormatString();
            set_error_handler(static function () {}, -1);
            try {
                $createdOnDBOk = DateTimeImmutable::createFromFormat($dbDateTimeFormat, $createdOnDB);
            } finally {
                restore_error_handler();
            }
            if ($createdOnDBOk === false) {
                throw new UserMessageException(t('Invalid parameter received'));
            }
            $lastLoaded = [
                'id' => $id,
                'createdOn' => $createdOnDBOk,
            ];
        }
        $records = $this->fetchNotifications($lastLoaded, $category);

        return $this->app->make(ResponseFactoryInterface::class)->json($records);
    }

    public function refresh_notification(): Response
    {
        if (!$this->token->validate('comtra-notifications-refresh1')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $id = $this->request->request->get('id');
        $id = is_numeric($id) ? (int) $id : 0;
        if ($id <= 0) {
            throw new UserMessageException(t('Invalid parameter received'));
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $notification = $em->find(NotificationEntity::class, $id);
        if ($notification === null) {
            throw new UserMessageException(t('Unable to find the notification requested.'));
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($this->serializeNotification($notification));
    }

    public function send_notification(): Response
    {
        if (!$this->token->validate('comtra-notifications-send')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $id = $this->request->request->get('id');
        $id = is_numeric($id) ? (int) $id : 0;
        if ($id <= 0) {
            throw new UserMessageException(t('Invalid parameter received'));
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $notification = $em->find(NotificationEntity::class, $id);
        if ($notification === null) {
            throw new UserMessageException(t('Unable to find the notification requested.'));
        }
        $sender = $this->app->make(Sender::class);
        $sender->send($notification);

        return $this->app->make(ResponseFactoryInterface::class)->json($this->serializeNotification($notification));
    }

    private function getCategories(): array
    {
        $repo = $this->app->make(NotificationRepository::class);
        $qb = $repo->createQueryBuilder('n')
            ->select('DISTINCT n.fqnClass')
        ;
        $query = $qb->getQuery();
        $classNames = $query->getSingleColumnResult();
        $result = [];
        foreach ($classNames as $className) {
            $result[$className] = $this->getNotificationCategoryDescription($className);
        }
        uksort($result, static function (string $a, string $b): int {
            return mb_strtolower($a) <=> mb_strtolower($b);
        });

        return $result;
    }
    private function fetchNotifications(?array $lastLoaded = null, string $category = ''): array
    {
        $repo = $this->app->make(NotificationRepository::class);
        $expr = Criteria::expr();
        $criteria = Criteria::create();
        $criteria
            ->orderBy([
                'createdOn' => 'DESC',
                'id' => 'DESC',
            ])
            ->setMaxResults(self::PAGE_SIZE)
        ;
        if ($lastLoaded !== null) {
            $criteria->andWhere($expr->orX(
                $expr->lt('createdOn', $lastLoaded['createdOn']),
                $expr->andX(
                    $expr->eq('createdOn', $lastLoaded['createdOn']),
                    $expr->lt('id', $lastLoaded['id']),
                )
            ));
        }
        if ($category !== '') {
            $criteria->andWhere($expr->eq('fqnClass', $category));
        }
        $result = [];
        $dateService = $this->app->make(DateService::class);
        $dbDateTimeFormat = $this->app->make(EntityManagerInterface::class)->getConnection()->getDatabasePlatform()->getDateTimeFormatString();
        foreach ($repo->matching($criteria) as $notification) {
            $result[] = $this->serializeNotification($notification, $dbDateTimeFormat, $dateService);
        }

        return $result;
    }

    private function serializeNotification(NotificationEntity $notification, string $dbDateTimeFormat = '', ?DateService $dateService = null): array
    {
        if ($dbDateTimeFormat === '') {
            $dbDateTimeFormat = $this->app->make(EntityManagerInterface::class)->getConnection()->getDatabasePlatform()->getDateTimeFormatString();
        }
        if ($dateService === null) {
            $dateService = $this->app->make(DateService::class);
        }

        return [
            'id' => $notification->getID(),
            'createdOnDB' => $notification->getCreatedOn()->format($dbDateTimeFormat),
            'createdOn' => $dateService->formatPrettyDateTime($notification->getCreatedOn(), true),
            'updatedOn' => $dateService->formatPrettyDateTime($notification->getUpdatedOn(), true),
            'category' => $this->getNotificationCategoryDescription($notification->getFQNClass()),
            'priority' => $notification->getPriority(),
            'deliveryAttempts' => $notification->getDeliveryAttempts(),
            'sentOn' => $notification->getSentOn() === null ? null : $dateService->formatPrettyDateTime($notification->getSentOn(), true),
            'sentCountPotential' => $notification->getSentCountPotential(),
            'sentCountActual' => $notification->getSentCountActual(),
            'deliveryErrors' => $notification->getDeliveryErrors(),
        ];
    }

    private function getNotificationCategoryDescription(string $className): string
    {
        if (is_a($className, CategoryInterface::class, true)) {
            return tc('NotificationCategory', $className::getDescription());
        }
        return $className;
    }
}
