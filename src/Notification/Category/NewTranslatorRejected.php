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
 * Notification category: a translation team join request has been rejected.
 */
class NewTranslatorRejected extends Category
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
        $uir = $this->app->make(UserInfoRepository::class);

        return [
            'localeName' => $locale->getDisplayName(),
            'applicant' => $uir->getByID($notificationData['applicantUserID']),
            'rejectedBy' => $uir->getByID($notificationData['rejectedByUserID']),
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
        $result[] = $notificationData['applicantUserID'];
        $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
        if ($locale === null) {
            throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
        }
        $group = $this->getGroupService()->getAdministrators($locale);
        $result = array_merge($result, $group->getGroupMemberIDs());
        $group = $this->getGroupService()->getGlobalAdministrators();

        return array_merge($result, $group->getGroupMemberIDs());
    }
}
