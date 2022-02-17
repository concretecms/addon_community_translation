<?php

declare(strict_types=1);

namespace CommunityTranslation\TranslationsConverter;

use Gettext\Translations;

defined('C5_EXECUTE') or die('Access Denied.');

class MoConverter extends BaseConverter
{
    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getHandle()
     */
    public function getHandle(): string
    {
        return 'mo';
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getName()
     */
    public function getName(): string
    {
        return t('Gettext MO format');
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getDescription()
     */
    public function getDescription(): string
    {
        return t('Compiled gettext MO');
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getFileExtension()
     */
    public function getFileExtension(): string
    {
        return 'mo';
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
        \Gettext\Generators\Mo::$includeEmptyTranslations = true;

        return $translations->toMoString();
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::unserializeTranslations()
     */
    public function unserializeTranslations(string $string): Translations
    {
        return Translations::fromMoString($string);
    }
}
