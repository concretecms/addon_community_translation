<?php

declare(strict_types=1);

namespace CommunityTranslation\TranslationsConverter;

use Gettext\Translations;

defined('C5_EXECUTE') or die('Access Denied.');

class JedConverter extends BaseConverter
{
    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getHandle()
     */
    public function getHandle(): string
    {
        return 'jed';
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getName()
     */
    public function getName(): string
    {
        return t('JED format');
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getDescription()
     */
    public function getDescription(): string
    {
        return t('Gettext Style i18n for Modern JavaScript Apps');
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
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::supportPlurals()
     */
    public function supportPlurals(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::serializeTranslations()
     */
    public function serializeTranslations(Translations $translations): string
    {
        return $translations->toJedString();
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::unserializeTranslations()
     */
    public function unserializeTranslations(string $string): Translations
    {
        return Translations::fromJedString($string);
    }
}
