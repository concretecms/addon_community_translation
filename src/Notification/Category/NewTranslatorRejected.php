<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Exception;

/**
 * Notification category: a translation team join request has been rejected.
 */
class NewTranslatorRejected extends Category
{
    /**
     * {@inheritdoc}
     *
     * @see Category::getRecipientIDs()
     */
    protected function getRecipientIDs(NotificationEntity $notification)
    {
        $result = [];
        $notificationData = $notification->getNotificationData();
        $result[] = $notificationData['applicantUserID'];
        $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
        if ($locale === null) {
            throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
        }
        $group = $this->getGroupsHelper()->getAdministrators($locale);
        $result = array_merge($result, $group->getGroupMemberIDs());
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
        $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
        if ($locale === null) {
            throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
        }
        $uir = $this->app->make(UserInfoRepository::class);
        $applicant = $uir->getByID($notificationData['applicantUserID']);
        $rejectedBy = $uir->getByID($notificationData['rejectedByUserID']);

        return [
            'localeName' => $locale->getDisplayName(),
            'applicant' => $applicant,
            'rejectedBy' => $rejectedBy,
            'teamsUrl' => $this->getBlockPageURL('CommunityTranslation Team List', 'details/' . $locale->getID()),
        ] + $this->getCommonMailParameters($notification, $recipient);
    }
}
