<?php

declare(strict_types=1);

namespace CommunityTranslation\Parser;

use CommunityTranslation\Service\DecompressedPackage;
use Concrete\Core\Application\Application;
use Concrete\Core\Error\UserMessageException;
use Illuminate\Filesystem\Filesystem;

defined('C5_EXECUTE') or die('Access Denied.');

abstract class Parser implements ParserInterface
{
    /**
     * The application object.
     */
    protected Application $app;

    /**
     * The Filesystem instance to use.
     */
    protected Filesystem $filesystem;

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
    public function parse(string $packageHandle, string $packageVersion, string $path, string $relDirectory = '', int $searchDictionaryFiles = self::DICTIONARY_ALL): ?Parsed
    {
        if ($path instanceof DecompressedPackage) {
            return $this->parseDirectory($packageHandle, $packageVersion, $path->getExtractedWorkDir(), $relDirectory, $searchDictionaryFiles);
        }
        if ($this->filesystem->isFile($path)) {
            return $this->parseFile($packageHandle, $packageVersion, $path, $relDirectory, $searchDictionaryFiles);
        }
        if ($this->filesystem->isDirectory($path)) {
            return $this->parseDirectory($packageHandle, $packageVersion, $path, $relDirectory, $searchDictionaryFiles);
        }
        throw new UserMessageException(t('Unable to find the file/directory %s', $path));
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\ParserInterface::parseFile()
     */
    public function parseFile(string $packageHandle, string $packageVersion, string $path, string $relDirectory = '', int $searchDictionaryFiles = self::DICTIONARY_ALL): ?Parsed
    {
        $zip = $this->app->make(DecompressedPackage::class, ['packageArchive' => $path, 'volatileDirectory' => null]);
        try {
            $zip->extract();
        } catch (UserMessageException $foo) {
            $zip = null;
        }
        if ($zip !== null) {
            return $this->parseDirectory($packageHandle, $packageVersion, $zip->getExtractedWorkDir(), $relDirectory, $searchDictionaryFiles);
        }
        $result = null;
        if ($searchDictionaryFiles !== self::DICTIONARY_NONE) {
            $result = $this->parseDictionaryFile($packageHandle, $packageVersion, $path, $searchDictionaryFiles);
        }
        if ($result === null) {
            $result = $this->parseSourceFile($packageHandle, $packageVersion, $path, $relDirectory);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\ParserInterface::parseZip()
     */
    public function parseZip(string $packageHandle, string $packageVersion, string $path, string $relDirectory = '', int $searchDictionaryFiles = self::DICTIONARY_ALL): ?Parsed
    {
        $zip = $this->app->make(DecompressedPackage::class, ['packageArchive' => $path, 'volatileDirectory' => null]);
        $zip->extract();

        return $this->parseDirectory($packageHandle, $packageVersion, $zip->getExtractedWorkDir(), $relDirectory, $searchDictionaryFiles);
    }

    /**
     * Extract translations from a dictionary file.
     *
     * @param string $packageHandle The package handle being parsed
     * @param string $packageVersion The package version being parsed
     * @param int $kinds One or more of self::DICTIONARY_SOURCE, self::DICTIONARY_LOCALIZED_SOURCE, self::DICTIONARY_LOCALIZED_COMPILED
     *
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return \CommunityTranslation\Parser\Parsed|null
     */
    abstract protected function parseDictionaryFile(string $packageHandle, string $packageVersion, string $path, int $kinds): ?Parsed;

    /**
     * Extract translations from a source file (.php, .xml, ...).
     *
     * @param string $packageHandle The package handle being parsed
     * @param string $packageVersion The package version being parsed
     * @param string $relDirectory
     *
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return \CommunityTranslation\Parser\Parsed|null
     */
    abstract protected function parseSourceFile(string $packageHandle, string $packageVersion, string $path, string $relDirectory = ''): ?Parsed;
}
