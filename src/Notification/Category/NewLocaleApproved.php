<?php

declare(strict_types=1);

namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Exception;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Notification category: the request of a new locale has been approved.
 */
class NewLocaleApproved extends Category
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
        $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
        if ($locale === null) {
            throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
        }

        return [
            'localeName' => $locale->getDisplayName(),
            'requestedBy' => $locale->getRequestedBy(),
            'approvedBy' => $notificationData['by'] ? $this->app->make(UserInfoRepository::class)->getByID($notificationData['by']) : null,
            'teamsUrl' => $this->getBlockPageURL('CommunityTranslation Team List', "details/{$locale->getID()}"),
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
        $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
        if ($locale === null) {
            throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
        }
        if ($locale->getRequestedBy() !== null) {
            $result[] = $locale->getRequestedBy()->getUserID();
        }
        $group = $this->getGroupService()->getGlobalAdministrators();

        return array_merge($result, $group->getGroupMemberIDs());
    }
}
