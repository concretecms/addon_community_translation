<?php

namespace CommunityTranslation\Translation;

/**
 * Options for CommunityTranslation\Translation\Importer::import().
 */
class ImportOptions
{
    /**
     * Consider all the translations as fuzzy?
     *
     * @var bool
     */
    protected $allFuzzy;

    /**
     * Unapprove fuzzy translations?
     *
     * @var bool
     */
    protected $unapproveFuzzy;

    /**
     * Consider all the translations as fuzzy?
     *
     * @return bool
     */
    public function getAllFuzzy()
    {
        return $this->allFuzzy;
    }

    /**
     * Unapprove fuzzy translations?
     *
     * @return bool
     */
    public function getUnapproveFuzzy()
    {
        return $this->unapproveFuzzy;
    }

    /**
     * Initialize the instance.
     *
     * @param mixed $allFuzzy Consider all the translations as fuzzy?
     * @param mixed $unapproveFuzzy Unapprove fuzzy translations?
     */
    public function __construct($allFuzzy, $unapproveFuzzy)
    {
        $this->allFuzzy = (bool) $allFuzzy;
        $this->unapproveFuzzy = (bool) $unapproveFuzzy;
    }

    /**
     * Get the options for normal (not administrator) translators.
     *
     * @return ImportOptions
     */
    public static function forTranslators()
    {
        return new self(true, false);
    }

    /**
     * Get the options for normal (not administrator) translators.
     *
     * @param mixed $unapproveFuzzy Unapprove fuzzy translations?
     *
     * @return ImportOptions
     */
    public static function forAdministrators($unapproveFuzzy = true)
    {
        return new self(false, $unapproveFuzzy);
    }
}
