<?php

declare(strict_types=1);

namespace CommunityTranslation\Translation;

defined('C5_EXECUTE') or die('Access Denied.');

class ImportResult
{
    /**
     * Number of strings not translated (skipped).
     */
    public int $emptyTranslations = 0;

    /**
     * Number of unknown translations (skipped).
     */
    public int $unknownStrings = 0;

    /**
     * Number of new translations added and marked as the current ones.
     */
    public int $addedAsCurrent = 0;

    /**
     * Number of new translations added but not marked as the current ones.
     */
    public int $addedNotAsCurrent = 0;

    /**
     * Number of already current translations untouched.
     */
    public int $existingCurrentUntouched = 0;

    /**
     * Number of current translations marked as approved.
     */
    public int $existingCurrentApproved = 0;

    /**
     * Number of current translations marked as not approved.
     */
    public int $existingCurrentUnapproved = 0;

    /**
     * Number of previous translations that have been activated (made current).
     */
    public int $existingActivated = 0;

    /**
     * Number of translations untouched.
     */
    public int $existingNotCurrentUntouched = 0;

    /**
     * Number of new translations needing approval.
     */
    public int $newApprovalNeeded = 0;
}
