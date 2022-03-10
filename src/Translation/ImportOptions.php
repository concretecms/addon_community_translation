<?php

declare(strict_types=1);

namespace CommunityTranslation\Translation;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Options for CommunityTranslation\Translation\Importer::import().
 */
class ImportOptions
{
    /**
     * Consider all the translations as fuzzy?
     */
    private bool $allFuzzy;

    /**
     * Unapprove fuzzy translations?
     */
    private bool $unapproveFuzzy;

    /**
     * Initialize the instance.
     *
     * @param bool $allFuzzy Consider all the translations as fuzzy?
     * @param bool $unapproveFuzzy Unapprove fuzzy translations?
     */
    public function __construct(bool $allFuzzy, bool $unapproveFuzzy)
    {
        $this->allFuzzy = $allFuzzy;
        $this->unapproveFuzzy = $unapproveFuzzy;
    }

    /**
     * Consider all the translations as fuzzy?
     */
    public function getAllFuzzy(): bool
    {
        return $this->allFuzzy;
    }

    /**
     * Unapprove fuzzy translations?
     */
    public function getUnapproveFuzzy(): bool
    {
        return $this->unapproveFuzzy;
    }

    /**
     * Get the options for normal (not administrator) translators.
     */
    public static function forTranslators(): self
    {
        return new self(true, false);
    }

    /**
     * Get the options for normal (not administrator) translators.
     *
     * @param bool $unapproveFuzzy Unapprove fuzzy translations?
     */
    public static function forAdministrators(bool $unapproveFuzzy = true): self
    {
        return new self(false, $unapproveFuzzy);
    }
}
