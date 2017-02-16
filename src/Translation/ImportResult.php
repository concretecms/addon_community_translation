<?php
namespace CommunityTranslation\Translation;

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
    public $addedAsCurrent = 0;

    /**
     * Number of new translations added but not marked as the current ones.
     *
     * @var int
     */
    public $addedNotAsCurrent = 0;

    /**
     * Number of already current translations untouched.
     *
     * @var int
     */
    public $existingCurrentUntouched = 0;

    /**
     * Number of current translations marked as approved.
     *
     * @var int
     */
    public $existingCurrentApproved = 0;

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
    public $existingNotCurrentUntouched = 0;

    /**
     * Number of new translations needing approval.
     *
     * @var int
     */
    public $newApprovalNeeded = 0;
}
