<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Notification\Category;

/**
 * Notification category: a new comment about a translation has been submitted.
 */
class TranslatableComment extends Category
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
