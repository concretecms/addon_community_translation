<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Notification\Category;
use Concrete\Core\Mail\Service as MailService;
use Exception;

/**
 * Notification category: the request of a new locale has been rejected.
 */
class NewLocaleRejected extends Category
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
        $result[] = $notificationData['requestedBy'];
        $group = $this->getGroupsHelper()->getGlobalAdministrators();
        $result = array_merge($result, $group->getGroupMemberIDs());

        return $result;
    }
}
