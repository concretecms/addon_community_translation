<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserList;
use Exception;

/**
 * Notification category: a new comment about a translation has been submitted.
 */
class TranslatableComment extends Category
{
    /**
     * {@inheritdoc}
     *
     * @see Category::getRecipientIDs()
     */
    protected function getRecipientIDs(NotificationEntity $notification)
    {
        $result = [];
        $locale = null;
        $notificationData = $notification->getNotificationData();
        if ($notificationData['localeID'] === null) {
            $ul = new UserList();
            $ul->disableAutomaticSorting();
            $ul->filterByGroup($this->getGroupsHelper()->getGlobalAdministrators());
            $ul->filterByAttribute('notify_translatable_messages', 1);
            $result = array_merge($result, $ul->getResultIDs());
        } else {
            $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
            if ($locale === null) {
                throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
            }
            // If it's a locale-specific issue, let's notify only the people involved in that locale
            $ul = new UserList();
            $ul->disableAutomaticSorting();
            $ul->filterByGroup($this->getGroupsHelper()->getTranslators($locale));
            $ul->filterByAttribute('notify_translatable_messages', 1);
            $result = array_merge($result, $ul->getResultIDs());
            $ul = new UserList();
            $ul->disableAutomaticSorting();
            $ul->filterByGroup($this->getGroupsHelper()->getAdministrators($locale));
            $ul->filterByAttribute('notify_translatable_messages', 1);
            $result = array_merge($result, $ul->getResultIDs());
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
        throw new Exception('@todo');
    }
}
