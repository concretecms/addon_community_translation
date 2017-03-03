<?php
namespace CommunityTranslation\TranslationsConverter;

use CommunityTranslation\UserException;
use Gettext\Translations;

class PhpArrayConverter extends BaseConverter implements ConverterInterface
{
    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getHandle()
     */
    public function getHandle()
    {
        return 'php_array';
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getName()
     */
    public function getName()
    {
        return t('PHP Array');
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getDescription()
     */
    public function getDescription()
    {
        return t('PHP Array');
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::getFileExtension()
     */
    public function getFileExtension()
    {
        return 'php';
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
     * @see ConverterInterface::canUnserializeTranslations()
     */
    public function canUnserializeTranslations()
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
        return $translations->toPhpArrayString();
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::convertStringToTranslations()
     */
    public function convertStringToTranslations($string)
    {
        throw new UserException(t('The "%s" converter is not able to unserialize translations', $this->getName()));
    }
}
