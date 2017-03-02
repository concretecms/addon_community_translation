<?php
namespace CommunityTranslation\TranslationsConverter;

use Gettext\Translations;

class JsonDictionaryConverter extends BaseConverter implements ConverterInterface
{
    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getHandle()
     */
    public function getHandle()
    {
        return 'json_dictionary';
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getName()
     */
    public function getName()
    {
        return t('JSON Dictionary');
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getDescription()
     */
    public function getDescription()
    {
        return t('JSON Dictionary');
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
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::supportPlurals()
     */
    public function supportPlurals()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::convertTranslationsToString()
     */
    public function convertTranslationsToString(Translations $translations)
    {
        return $translations->toJsonDictionaryString();
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::convertStringToTranslations()
     */
    public function convertStringToTranslations($string)
    {
        return Translations::fromJsonDictionaryString($string);
    }
}
