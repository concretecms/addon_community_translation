<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Notification\Category;

/**
 * Notification category: someone wants to join a translation team.
 */
class NewTeamJoinRequest extends Category
{
    /**
     * {@inheritdoc}
     *
     * @see Category::getRecipients()
     */
    public function getRecipients()
    {
        // @todo
        return [];
    }
}
