<?php
namespace CommunityTranslation\TranslationsConverter;

use CommunityTranslation\UserException;
use Gettext\Translations;
use Illuminate\Filesystem\Filesystem;

abstract class BaseConverter implements ConverterInterface
{
    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @param Filesystem $fs
     */
    public function __construct(Filesystem $fs)
    {
        $this->fs = $fs;
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::canSerializeTranslations()
     */
    public function canSerializeTranslations()
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
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::saveTranslationsToFile()
     */
    public function saveTranslationsToFile(Translations $translations, $filename)
    {
        if (!$this->canSerializeTranslations()) {
            throw new UserException(t('The "%s" converter is not able to serialize translations', $this->getName()));
        }
        $serialized = $this->convertTranslationsToString($translations);
        if (@$this->fs->put($filename, $serialized) === false) {
            throw new UserException(t('Failed to save translations to file'));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see ConverterInterface::loadTranslationsFromFile()
     */
    public function loadTranslationsFromFile($filename)
    {
        if (!$this->canSerializeTranslations()) {
            throw new UserException(t('The "%s" converter is not able to unserialize translations', $this->getName()));
        }

        if (!$this->fs->isFile($filename)) {
            throw new UserException(t('File not found'));
        }

        $contents = @$this->fs->get($filename);
        if ($contents === false) {
            throw new UserException(t('Unable to read a file'));
        }

        return $this->convertStringToTranslations($contents);
    }
}
