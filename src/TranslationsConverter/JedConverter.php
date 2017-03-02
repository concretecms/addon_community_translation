<?php
namespace CommunityTranslation\TranslationsConverter;

use Gettext\Translations;

class JedConverter extends BaseConverter implements ConverterInterface
{
    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getHandle()
     */
    public function getHandle()
    {
        return 'jed';
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getName()
     */
    public function getName()
    {
        return t('JED format');
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getDescription()
     */
    public function getDescription()
    {
        return t('Gettext Style i18n for Modern JavaScript Apps');
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getFileExtension()
     */
    public function getFileExtension()
    {
        return 'json';
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::supportLanguageHeader()
     */
    public function supportLanguageHeader()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::supportPlurals()
     */
    public function supportPlurals()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::convertTranslationsToString()
     */
    public function convertTranslationsToString(Translations $translations)
    {
        return $translations->toJedString();
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::convertStringToTranslations()
     */
    public function convertStringToTranslations($string)
    {
        return Translations::fromJedString($string);
    }
}
