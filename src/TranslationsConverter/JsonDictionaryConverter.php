<?php

declare(strict_types=1);

namespace CommunityTranslation\TranslationsConverter;

use Gettext\Translations;

defined('C5_EXECUTE') or die('Access Denied.');

class JsonDictionaryConverter extends BaseConverter
{
    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getHandle()
     */
    public function getHandle(): string
    {
        return 'json_dictionary';
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getName()
     */
    public function getName(): string
    {
        return t('JSON Dictionary');
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getDescription()
     */
    public function getDescription(): string
    {
        return t('JSON Dictionary');
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getFileExtension()
     */
    public function getFileExtension(): string
    {
        return 'json';
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::supportLanguageHeader()
     */
    public function supportLanguageHeader(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::supportPlurals()
     */
    public function supportPlurals(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::serializeTranslations()
     */
    public function serializeTranslations(Translations $translations): string
    {
        return $translations->toJsonDictionaryString();
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::unserializeTranslations()
     */
    public function unserializeTranslations(string $string): Translations
    {
        return Translations::fromJsonDictionaryString($string);
    }
}
