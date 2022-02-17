<?php

declare(strict_types=1);

namespace CommunityTranslation\Parser;

use CommunityTranslation\Entity\Locale;
use CommunityTranslation\Service\SourceLocale;
use Gettext\Translations;

defined('C5_EXECUTE') or die('Access Denied.');

final class Parsed
{
    /**
     * The source (untranslated) strings (representing a .pot file).
     */
    private ?Translations $sourceStrings = null;

    /**
     * The translations (keys are the locale ID).
     *
     * @var [[Locale, Translations]]
     */
    private array $translations = [];

    /**
     * Set the source (untranslated) strings (representing a .pot file).
     *
     * @return $this
     */
    public function setSourceStrings(?Translations $sourceStrings): self
    {
        $this->sourceStrings = $sourceStrings;

        return $this;
    }

    /**
     * Get the source (untranslated) strings (representing a .pot file).
     *
     * @param bool $buildIfNotSet set to true to build the string list if it's not set
     */
    public function getSourceStrings(bool $buildIfNotSet = false): ?Translations
    {
        if ($this->sourceStrings !== null || $buildIfNotSet === false) {
            return $this->sourceStrings;
        }
        $sourceLocale = app(SourceLocale::class)->getRequiredSourceLocale();
        $result = new Translations();
        $result->setLanguage($sourceLocale->getID());
        $result->setPluralForms($sourceLocale->getPluralCount(), $sourceLocale->getPluralFormula());
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

        return $this->sourceStrings;
    }

    /**
     * Set the translations for a specific locale.
     *
     * @return $this
     */
    public function setTranslations(Locale $locale, Translations $translations): self
    {
        $this->translations[$locale->getID()] = [$locale, $translations];

        return $this;
    }

    /**
     * Get the translations for a specific locale.
     */
    public function getTranslations(Locale $locale): Translations
    {
        $localeID = $locale->getID();
        $translations = $this->translations[$localeID][1] ?? null;
        if ($translations !== null) {
            return $translations;
        }
        $translations = clone $this->getSourceStrings(true);
        $translations->setLanguage($locale->getID());
        $translations->setPluralForms($locale->getPluralCount(), $locale->getPluralFormula());
        $this->setTranslations($locale, $translations);

        return $translations;
    }

    /**
     * Merge another instance into this one.
     *
     * @return $this
     */
    public function mergeWith(self $other): self
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

        return $this;
    }
}
