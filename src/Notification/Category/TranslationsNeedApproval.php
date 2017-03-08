<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Notification\Category;

/**
 * Notification category: some translations need approval.
 */
class TranslationsNeedApproval extends Category
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
