<?php
namespace CommunityTranslation\Parser;

use CommunityTranslation\Entity\Locale;
use Gettext\Translations;

class Parsed
{
    /**
     * The source (untranslated) strings (representing a .pot file).
     *
     * @var Translations|null
     */
    protected $sourceStrings = null;

    /**
     * The translations (keys are the locale ID).
     *
     * @var [[Locale, Translations]]
     */
    protected $translations = [];

    /**
     * Set the source (untranslated) strings (representing a .pot file).
     *
     * @param Translations $sourceStrings
     */
    public function setSourceStrings(Translations $sourceStrings)
    {
        $this->sourceStrings = $sourceStrings;
    }

    /**
     * Get the source (untranslated) strings (representing a .pot file).
     *
     * @param bool $buildIfNotSet set to true to build the string list if it's not set
     *
     * @return Translations|null
     */
    public function getSourceStrings($buildIfNotSet = false)
    {
        $result = $this->sourceStrings;
        if ($result === null && $buildIfNotSet) {
            $result = new Translations();
            $result->setLanguage($this->app->make('community_translation/sourceLocale'));
            $mergeMethod = Translations::MERGE_ADD | Translations::MERGE_PLURAL;
            foreach ($this->translations as $translations) {
                foreach ($translations[1] as $key => $translation) {
                    if (!$result->offsetExists($key)) {
                        $newTranslation = $result->insert($translation->getContext(), $translation->getOriginal(), $translation->getPlural());
                        foreach ($translation->getExtractedComments() as $comment) {
                            $newTranslation->addExtractedComment($comment);
                        }
                    }
                }
            }
            $this->sourceStrings = $result;
        }

        return $result;
    }

    /**
     * Set the translations for a specific locale.
     *
     * @param Locale $locale
     * @param Translations $translations
     */
    public function setTranslations(Locale $locale, Translations $translations)
    {
        $this->translations[$locale->getID()] = [$locale, $translations];
    }

    /**
     * Get the translations for a specific locale.
     *
     * @param Locale $locale
     */
    public function getTranslations(Locale $locale)
    {
        $localeID = $locale->getID();
        $translations = isset($this->translations[$localeID]) ? $this->translations[$localeID][1] : null;
        if ($translations === null) {
            $translations = clone $this->getSourceStrings(true);
            $translations->setLanguage($locale->getID());
            $this->setTranslations($locale, $translations);
        }

        return $translations;
    }

    /**
     * Get all the translations set.
     *
     * @return [Locale, Translations]
     */
    public function getTranslationsList()
    {
        return array_values($this->translations);
    }

    /**
     * Merge another instance into this one.
     *
     * @param Parsed $other
     */
    public function mergeWith(Parsed $other)
    {
        $mergeMethod = Translations::MERGE_ADD | Translations::MERGE_PLURAL;
        if ($other->sourceStrings !== null) {
            if ($this->sourceStrings === null) {
                $this->sourceStrings = $other->sourceStrings;
            } else {
                $this->sourceStrings->mergeWith($other->sourceStrings, $mergeMethod);
            }
        }
        foreach ($other->translations as $key => $data) {
            if (isset($this->translations[$key])) {
                $this->translations[$key][1]->mergeWith($data[1], $mergeMethod);
            } else {
                $this->translations[$key] = $data;
            }
        }
    }
}
