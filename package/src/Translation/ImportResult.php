<?php
namespace Concrete\Package\CommunityTranslation\Src\Translation;

class ImportResult
{
    /**
     * Number of strings not translated (skipped).
     *
     * @var int
     */
    public $emptyTranslations = 0;

    /**
     * Number of translations for unknown translatable strings (skipped).
     *
     * @var int
     */
    public $unknownStrings = 0;

    /**
     * Number of new translations added and marked as the current ones.
     *
     * @var int
     */
    public $addedActivated = 0;

    /**
     * Number of new translations added but waiting for review (not marked as current).
     *
     * @var int
     */
    public $addedNeedReview = 0;

    /**
     * Number of already active translations untouched.
     *
     * @var int
     */
    public $existingActiveUntouched = 0;

    /**
     * Number of current translations marked as reviewed.
     *
     * @var int
     */
    public $existingActiveReviewed = 0;

    /**
     * Number of previous translations that have been activated (made current).
     *
     * @var int
     */
    public $existingActivated = 0;

    /**
     * Number of translations untouched.
     *
     * @var int
     */
    public $existingInactiveUntouched = 0;
}
