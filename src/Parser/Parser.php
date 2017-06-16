<?php

namespace CommunityTranslation\Parser;

use CommunityTranslation\Service\DecompressedPackage;
use CommunityTranslation\UserException;
use Concrete\Core\Application\Application;
use Illuminate\Filesystem\Filesystem;

abstract class Parser implements ParserInterface
{
    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * The Filesystem instance to use.
     *
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param Application $app
     * @param Filesystem $filesystem
     */
    public function __construct(Application $app, Filesystem $filesystem)
    {
        $this->app = $app;
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\ParserInterface::parse()
     */
    public function parse($packageHandle, $packageVersion, $path, $relDirectory = '', $searchDictionaryFiles = self::DICTIONARY_ALL)
    {
        if (is_object($path) && ($path instanceof DecompressedPackage)) {
            $result = $this->parseDirectory($packageHandle, $packageVersion, $path->getExtractedWorkDir(), $relDirectory, $searchDictionaryFiles);
        } elseif ($this->filesystem->isFile($path)) {
            $result = $this->parseFile($packageHandle, $packageVersion, $path, $relDirectory, $searchDictionaryFiles);
        } elseif ($this->filesystem->isDirectory($path)) {
            $result = $this->parseDirectory($packageHandle, $packageVersion, $path, $relDirectory, $searchDictionaryFiles);
        } else {
            throw new UserException(t('Unable to find the file/directory %s', $path));
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\ParserInterface::parseFile()
     */
    public function parseFile($packageHandle, $packageVersion, $path, $relDirectory = '', $searchDictionaryFiles = self::DICTIONARY_ALL)
    {
        $zip = $this->app->make(DecompressedPackage::class, ['packageArchive' => $path, 'volatileDirectory' => null]);
        try {
            $zip->extract();
        } catch (UserException $foo) {
            $zip = null;
        }
        if ($zip !== null) {
            $result = $this->parseDirectory($packageHandle, $packageVersion, $zip->getExtractedWorkDir(), $relDirectory, $searchDictionaryFiles);
        } else {
            $result = null;
            if ($searchDictionaryFiles !== self::DICTIONARY_NONE) {
                $result = $this->parseDictionaryFile($packageHandle, $packageVersion, $path, $searchDictionaryFiles);
            }
            if ($result === null) {
                $result = $this->parseSourceFile($packageHandle, $packageVersion, $path, $relDirectory);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\ParserInterface::parseZip()
     */
    public function parseZip($packageHandle, $packageVersion, $path, $relDirectory = '', $searchDictionaryFiles = self::DICTIONARY_ALL)
    {
        $zip = $this->app->make(DecompressedPackage::class, ['packageArchive' => $path, 'volatileDirectory' => null]);
        $zip->extract();
        $result = $this->parseDirectory($packageHandle, $packageVersion, $zip->getExtractedWorkDir(), $relDirectory, $searchDictionaryFiles);
        unset($zip);

        return $result;
    }

    /**
     * Extract translations from a dictionary file.
     *
     * @param string $packageHandle The package handle being parsed
     * @param string $packageVersion The package version being parsed
     * @param string $path
     * @param int $kinds One or more of self::DICTIONARY_SOURCE, self::DICTIONARY_LOCALIZED_SOURCE, self::DICTIONARY_LOCALIZED_COMPILED
     *
     * @throws UserException
     *
     * @return Parsed|null
     */
    abstract public function parseDictionaryFile($packageHandle, $packageVersion, $path, $kinds);

    /**
     * Extract translations from a source file (.php, .xml, ...).
     *
     * @param string $packageHandle The package handle being parsed
     * @param string $packageVersion The package version being parsed
     * @param string $path
     * @param string $relDirectory
     *
     * @throws UserException
     *
     * @return Parsed|null
     */
    abstract public function parseSourceFile($packageHandle, $packageVersion, $path, $relDirectory = '');
}
