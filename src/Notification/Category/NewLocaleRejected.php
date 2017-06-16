<?php

namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Punic\Language;

/**
 * Notification category: the request of a new locale has been rejected.
 */
class NewLocaleRejected extends Category
{
    /**
     * @var int
     */
    const PRIORITY = 10;

    /**
     * {@inheritdoc}
     *
     * @see Category::getRecipientIDs()
     */
    protected function getRecipientIDs(NotificationEntity $notification)
    {
        $result = [];
        $notificationData = $notification->getNotificationData();
        $result[] = $notificationData['requestedBy'];
        $group = $this->getGroupsHelper()->getGlobalAdministrators();
        $result = array_merge($result, $group->getGroupMemberIDs());

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see Category::getMailParameters()
     */
    public function getMailParameters(NotificationEntity $notification, UserInfo $recipient)
    {
        $notificationData = $notification->getNotificationData();
        $uir = $this->app->make(UserInfoRepository::class);
        $requestedBy = $notificationData['requestedBy'] ? $uir->getByID($notificationData['requestedBy']) : null;
        $deniedBy = $notificationData['by'] ? $uir->getByID($notificationData['by']) : null;

        return [
            'localeName' => Language::getName($notificationData['localeID']),
            'requestedBy' => $requestedBy,
            'deniedBy' => $deniedBy,
            'teamsUrl' => $this->getBlockPageURL('CommunityTranslation Team List'),
        ] + $this->getCommonMailParameters($notification, $recipient);
    }
}
