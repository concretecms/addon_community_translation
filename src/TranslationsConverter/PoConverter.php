<?php

namespace CommunityTranslation\TranslationsConverter;

use Gettext\Translations;

class PoConverter extends BaseConverter implements ConverterInterface
{
    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getHandle()
     */
    public function getHandle()
    {
        return 'po';
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getName()
     */
    public function getName()
    {
        return t('Gettext PO format');
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getDescription()
     */
    public function getDescription()
    {
        return t('Uncompiled gettext PO');
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getFileExtension()
     */
    public function getFileExtension()
    {
        return 'po';
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
        return $translations->toPoString();
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::convertStringToTranslations()
     */
    public function convertStringToTranslations($string)
    {
        return Translations::fromPoString($string);
    }
}
