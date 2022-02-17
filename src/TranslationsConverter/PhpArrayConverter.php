<?php

declare(strict_types=1);

namespace CommunityTranslation\TranslationsConverter;

use Concrete\Core\Error\UserMessageException;
use Gettext\Translations;

defined('C5_EXECUTE') or die('Access Denied.');

class PhpArrayConverter extends BaseConverter
{
    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getHandle()
     */
    public function getHandle(): string
    {
        return 'php_array';
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getName()
     */
    public function getName(): string
    {
        return t('PHP Array');
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getDescription()
     */
    public function getDescription(): string
    {
        return t('PHP Array');
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::getFileExtension()
     */
    public function getFileExtension(): string
    {
        return 'php';
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
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::canUnserializeTranslations()
     */
    public function canUnserializeTranslations(): bool
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
        return $translations->toPhpArrayString();
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\TranslationsConverter\ConverterInterface::unserializeTranslations()
     */
    public function unserializeTranslations(string $string): Translations
    {
        throw new UserMessageException(t('The "%s" converter is not able to unserialize translations', $this->getName()));
    }
}
