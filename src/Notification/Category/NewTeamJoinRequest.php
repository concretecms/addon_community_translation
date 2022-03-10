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
 * Notification category: someone wants to join a translation team.
 */
class NewTeamJoinRequest extends Category
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
        $applicant = $uir->getByID($notificationData['applicantUserID']);
        if ($applicant === null) {
            throw new Exception(t('Unable to find the user with ID %s', $notificationData['applicantUserID']));
        }

        return [
            'localeName' => $locale->getDisplayName(),
            'applicant' => $applicant,
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
        $uir = $this->app->make(UserInfoRepository::class);
        $applicantUser = $uir->getByID($notificationData['applicantUserID']);
        if ($applicantUser === null) {
            throw new Exception(t('Unable to find the user with ID %s', $notificationData['applicantUserID']));
        }
        if (!$applicantUser->getUserObject()->inGroup($this->getGroupService()->getAspiringTranslators($locale))) {
            throw new Exception(t(
                'The user %1$s is no more in the aspiring translators of %2$s',
                sprintf('%s (%s)', $applicantUser->getUserName(), $applicantUser->getUserID()),
                $locale->getDisplayName()
            ));
        }
        $group = $this->getGroupService()->getAdministrators($locale);
        $result = array_merge($result, $group->getGroupMemberIDs());
        $group = $this->getGroupService()->getGlobalAdministrators();

        return array_merge($result, $group->getGroupMemberIDs());
    }
}
