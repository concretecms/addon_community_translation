<?php

declare(strict_types=1);

namespace CommunityTranslation\TranslationsConverter;

use Gettext\Translations;

defined('C5_EXECUTE') or die('Access Denied.');

interface ConverterInterface
{
    /**
     * Get the handle of the converter.
     */
    public function getHandle(): string;

    /**
     * Get the name of the converter.
     */
    public function getName(): string;

    /**
     * Get the description of the converter.
     */
    public function getDescription(): string;

    /**
     * Get the file extension (without the initial dot).
     */
    public function getFileExtension(): string;

    /**
     * Does this format support a language header?
     */
    public function supportLanguageHeader(): bool;

    /**
     * Does this format support plural rules and forms?
     */
    public function supportPlurals(): bool;

    /**
     * Is this converter able to convert \Gettext\Translations instances to strings/files?
     */
    public function canSerializeTranslations(): bool;

    /**
     * Is this converter able to convert strings/files to \Gettext\Translations instances?
     */
    public function canUnserializeTranslations(): bool;

    /**
     * Convert a \Gettext\Translations instance to string.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function serializeTranslations(Translations $translations): string;

    /**
     * Convert a string into a \Gettext\Translations instance.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function unserializeTranslations(string $string): Translations;

    /**
     * Save a \Gettext\Translations instance to file.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function saveTranslationsToFile(Translations $translations, string $filename): void;

    /**
     * Load \Gettext\Translations from file.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function loadTranslationsFromFile(string $filename): Translations;
}
