<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Notification\Category;

/**
 * Notification category: the request of a new locale has been rejected.
 */
class NewLocaleRejected extends Category
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
