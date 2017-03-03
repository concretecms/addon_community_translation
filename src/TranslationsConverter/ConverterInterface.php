<?php
namespace CommunityTranslation\TranslationsConverter;

use CommunityTranslation\UserException;
use Gettext\Translations;

interface ConverterInterface
{
    /**
     * Get the handle of the converter.
     *
     * @return string
     */
    public function getHandle();

    /**
     * Get the name of the converter.
     *
     * @return string
     */
    public function getName();

    /**
     * Get the description of the converter.
     *
     * @return string
     */
    public function getDescription();

    /**
     * Get the file extension (without the initial dot).
     *
     * @return string
     */
    public function getFileExtension();

    /**
     * Does this format support a language header?
     *
     * @return bool
     */
    public function supportLanguageHeader();

    /**
     * Does this format support plural rules and forms?
     *
     * @return bool
     */
    public function supportPlurals();

    /**
     * Is this converter able to convert \Gettext\Translations instances to strings/files?
     *
     * @return bool
     */
    public function canSerializeTranslations();

    /**
     * Is this converter able to convert strings/files to \Gettext\Translations instances?
     *
     * @return bool
     */
    public function canUnserializeTranslations();

    /**
     * Convert a \Gettext\Translations instance to string.
     *
     * @param Translations $translations
     *
     * @throws UserException
     *
     * @return string
     */
    public function convertTranslationsToString(Translations $translations);

    /**
     * Convert a string into a \Gettext\Translations instance.
     *
     * @param string $string
     *
     * @throws UserException
     *
     * @return Translations
     */
    public function convertStringToTranslations($string);

    /**
     * Save a \Gettext\Translations instance to file.
     *
     * @param Translations $translations
     * @param string $filename
     *
     * @throws UserException
     *
     * @return string
     */
    public function saveTranslationsToFile(Translations $translations, $filename);

    /**
     * Load \Gettext\Translations from file.
     *
     * @param string $filename
     *
     * @throws UserException
     *
     * @return Translations
     */
    public function loadTranslationsFromFile($filename);
}
