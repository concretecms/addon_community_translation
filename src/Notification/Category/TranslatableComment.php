<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\Mail\Service as MailService;
use Concrete\Core\User\UserList;

/**
 * Notification category: a new comment about a translation has been submitted.
 */
class TranslatableComment extends Category
{
    /**
     * {@inheritdoc}
     *
     * @see Category::addMailParameters()
     */
    protected function addMailParameters(array $notificationData, MailService $mail)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @see Category::getRecipientIDs()
     */
    protected function getRecipientIDs(array $notificationData)
    {
        $result = [];
        $locale = null;
        if ($notificationData['localeID'] === null) {
            $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
        }
        if ($locale === null) {
            $ul = new UserList();
            $ul->disableAutomaticSorting();
            $ul->filterByGroup($this->getGroupsHelper()->getGlobalAdministrators());
            $ul->filterByAttribute('notify_translatable_messages', 1);
            $result = array_merge($result, $ul->getResultIDs());
        } else {
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
}
