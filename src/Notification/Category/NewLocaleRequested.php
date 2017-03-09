<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\User\UserInfo;
use Exception;

/**
 * Notification category: a new locale has been requested.
 */
class NewLocaleRequested extends Category
{
    /**
     * {@inheritdoc}
     *
     * @see Category::getRecipientIDs()
     */
    protected function getRecipientIDs(NotificationEntity $notification)
    {
        $group = $this->getGroupsHelper()->getGlobalAdministrators();

        return $group->getGroupMemberIDs();
    }

    /**
     * {@inheritdoc}
     *
     * @see Category::getMailParameters()
     */
    public function getMailParameters(NotificationEntity $notification, UserInfo $recipient)
    {
        $notificationData = $notification->getNotificationData();
        $locale = $this->app->make(LocaleRepository::class)->find($notificationData['localeID']);
        if ($locale === null) {
            throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
        }
        $requestedBy = $locale->getRequestedBy();

        return [
            'requestedBy' => $requestedBy ? $requestedBy->getUserName() : '',
            'localeName' => $locale->getDisplayName(),
            'teamsUrl' => $this->getBlockPageURL('CommunityTranslation Team List'),
            'notes' => $notificationData['notes'] ? $this->app->make('helper/text')->makenice($notificationData['notes']) : '',
        ] + parent::getCommonMailParameters($notification, $recipient);
    }
}
