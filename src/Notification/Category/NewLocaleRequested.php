<?php

declare(strict_types=1);

namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\User\UserInfo;
use Exception;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Notification category: a new locale has been requested.
 */
class NewLocaleRequested extends Category
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
        $locale = $this->app->make(LocaleRepository::class)->find($notificationData['localeID']);
        if ($locale === null) {
            throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
        }

        return [
            'requestedBy' => $locale->getRequestedBy(),
            'localeName' => $locale->getDisplayName(),
            'teamsUrl' => $this->getBlockPageURL('CommunityTranslation Team List'),
            'notes' => $notificationData['notes'] ? $this->app->make('helper/text')->makenice($notificationData['notes']) : '',
        ] + $this->getCommonMailParameters($notification, $recipient);
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Notification\Category::getRecipientIDs()
     */
    protected function getRecipientIDs(NotificationEntity $notification): array
    {
        $notificationData = $notification->getNotificationData();
        $locale = $this->app->make(LocaleRepository::class)->find($notificationData['localeID']);
        if ($locale === null && $locale->isApproved()) {
            // The request has already been approved/refused
            $result = [];
        } else {
            $group = $this->getGroupService()->getGlobalAdministrators();
            $result = $group->getGroupMemberIDs();
        }

        return $result;
    }
}
