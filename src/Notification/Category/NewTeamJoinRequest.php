<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\Mail\Service as MailService;

/**
 * Notification category: someone wants to join a translation team.
 */
class NewTeamJoinRequest extends Category
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
        $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
        if ($locale !== null) {
            $group = $this->getGroupsHelper()->getAdministrators($locale);
            $result = array_merge($result, $group->getGroupMemberIDs());
        }
        $group = $this->getGroupsHelper()->getGlobalAdministrators();
        $result = array_merge($result, $group->getGroupMemberIDs());

        return $result;
    }
}
