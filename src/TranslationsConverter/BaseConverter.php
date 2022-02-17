<?php

declare(strict_types=1);

namespace CommunityTranslation\TranslationsConverter;

use Concrete\Core\Error\UserMessageException;
use Gettext\Translations;
use Illuminate\Filesystem\Filesystem;

defined('C5_EXECUTE') or die('Access Denied.');

abstract class BaseConverter implements ConverterInterface
{
    protected Filesystem $fs;

    public function __construct(Filesystem $fs)
    {
        $this->fs = $fs;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::canSerializeTranslations()
     */
    public function canSerializeTranslations(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::canUnserializeTranslations()
     */
    public function canUnserializeTranslations(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::saveTranslationsToFile()
     */
    public function saveTranslationsToFile(Translations $translations, string $filename): void
    {
        if (!$this->canSerializeTranslations()) {
            throw new UserMessageException(t('The "%s" converter is not able to serialize translations', $this->getName()));
        }
        $serialized = $this->serializeTranslations($translations);
        set_error_handler(static function () {}, -1);
        try {
            $saved = $this->fs->put($filename, $serialized);
        } finally {
            restore_error_handler();
        }
        if ($saved === false) {
            throw new UserMessageException(t('Failed to save translations to file'));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::loadTranslationsFromFile()
     */
    public function loadTranslationsFromFile(string $filename): Translations
    {
        if (!$this->canSerializeTranslations()) {
            throw new UserMessageException(t('The "%s" converter is not able to unserialize translations', $this->getName()));
        }

        if (!$this->fs->isFile($filename)) {
            throw new UserMessageException(t('File not found'));
        }

        set_error_handler(static function () {}, -1);
        try {
            $contents = $this->fs->get($filename);
        } finally {
            restore_error_handler();
        }
        if ($contents === false) {
            throw new UserMessageException(t('Unable to read a file'));
        }

        return $this->unserializeTranslations($contents);
    }
}
