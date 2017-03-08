<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Notification\Category;
use Concrete\Core\Mail\Service as MailService;

/**
 * Notification category: a new locale has been requested.
 */
class NewLocaleRequested extends Category
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
        $group = $this->getGroupsHelper()->getGlobalAdministrators();

        return $group->getGroupMemberIDs();
    }
}
