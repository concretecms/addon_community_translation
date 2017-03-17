<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Exception;
use URL;

/**
 * Notification category: some translations need approval.
 */
class TranslationsNeedApproval extends Category
{
    /**
     * @var int
     */
    const PRIORITY = 1;

    /**
     * {@inheritdoc}
     *
     * @see Category::getRecipientIDs()
     */
    protected function getRecipientIDs(NotificationEntity $notification)
    {
        $result = [];
        $notificationData = $notification->getNotificationData();
        $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
        if ($locale === null) {
            throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
        }
        $group = $this->getGroupsHelper()->getAdministrators($locale);
        $result = array_merge($result, $group->getGroupMemberIDs());
        if (empty($result)) {
            $group = $this->getGroupsHelper()->getGlobalAdministrators();
            $result = $group->getGroupMemberIDs();
        }

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
        $packageVersion = $notificationData['packageVersionID'] ? $this->app->make(PackageVersionRepository::class)->find($notificationData['packageVersionID']) : null;
        $translations = [];
        foreach ($notificationData['numTranslations'] as $userID => $numTranslations) {
            $translations[] = [
                'user' => $uir->getByID($userID),
                'numTranslations' => $numTranslations,
            ];
        }

        return [
            'localeName' => $locale->getDisplayName(),
            'translations' => $translations,
            'approvalURL' => (string) URL::to(
                $this->app->make('community_translation/config')->get('options.onlineTranslationPath'),
                $packageVersion ? $packageVersion->getID() : 'unreviewed',
                $locale->getID()
            ),
        ] + $this->getCommonMailParameters($notification, $recipient);
    }
}
