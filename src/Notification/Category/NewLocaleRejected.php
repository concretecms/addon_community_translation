<?php

declare(strict_types=1);

namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Punic\Language;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Notification category: the request of a new locale has been rejected.
 */
class NewLocaleRejected extends Category
{
    /**
     * @var int
     */
    public const PRIORITY = 10;

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Notification\CategoryInterface::getMailParameters()
     */
    public function getMailParameters(NotificationEntity $notification, UserInfo $recipient): array
    {
        $notificationData = $notification->getNotificationData();
        $uir = $this->app->make(UserInfoRepository::class);

        return [
            'localeName' => Language::getName($notificationData['localeID']),
            'requestedBy' => $notificationData['requestedBy'] ? $uir->getByID($notificationData['requestedBy']) : null,
            'deniedBy' => $notificationData['by'] ? $uir->getByID($notificationData['by']) : null,
            'teamsUrl' => $this->getBlockPageURL('CommunityTranslation Team List'),
        ] + $this->getCommonMailParameters($notification, $recipient);
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Notification\Category::getRecipientIDs()
     */
    protected function getRecipientIDs(NotificationEntity $notification): array
    {
        $result = [];
        $notificationData = $notification->getNotificationData();
        $result[] = $notificationData['requestedBy'];
        $group = $this->getGroupService()->getGlobalAdministrators();

        return array_merge($result, $group->getGroupMemberIDs());
    }
}
