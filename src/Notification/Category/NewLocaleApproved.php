<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\Mail\Service as MailService;
use Exception;

/**
 * Notification category: the request of a new locale has been approved.
 */
class NewLocaleApproved extends Category
{
    /**
     * {@inheritdoc}
     *
     * @see Category::addMailParameters()
     */
    protected function addMailParameters(array $notificationData, MailService $mail)
    {
        throw new Exception('@todo');
    }

    /**
     * {@inheritdoc}
     *
     * @see Category::getRecipientIDs()
     */
    protected function getRecipientIDs(array $notificationData)
    {
        $result = [];
        $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
        if ($locale === null) {
            throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
        }
        if ($locale->getRequestedBy() !== null) {
            $result[] = $locale->getRequestedBy()->getUserID();
        }
        $group = $this->getGroupsHelper()->getGlobalAdministrators();
        $result = array_merge($result, $group->getGroupMemberIDs());

        return $result;
    }
}
