<?php

namespace CommunityTranslation\Service;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Repository\Stats as StatsRepository;
use CommunityTranslation\Translation\Exporter;
use CommunityTranslation\TranslationsConverter\ConverterInterface as TranslationsConverter;
use Concrete\Core\Application\Application;
use Concrete\Core\Error\UserMessageException;
use Exception;
use Illuminate\Filesystem\Filesystem;

class TranslationsFileExporter
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var string|null
     */
    protected $rootCacheDirectory;

    /**
     * @param Application $app
     * @param Filesystem $fs
     */
    public function __construct(Application $app, Filesystem $fs)
    {
        $this->app = $app;
        $this->fs = $fs;
        $this->rootCacheDirectory = null;
    }

    /**
     * @param string $dir
     *
     * @return $dir
     */
    private static function normalizeDirectory($dir)
    {
        return is_string($dir) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $dir), '/') : '';
    }

    /**
     * @param string $rootCacheDirectory
     */
    public function setRootCacheDirectory($rootCacheDirectory)
    {
        $rootCacheDirectory = self::normalizeDirectory($rootCacheDirectory);
        $this->rootCacheDirectory = ($rootCacheDirectory === '') ? null : $rootCacheDirectory;
    }

    /**
     * @return string
     */
    public function getRootCacheDirectory()
    {
        if ($this->rootCacheDirectory === null) {
            $config = $this->app->make('community_translation/config');
            $dir = self::normalizeDirectory($config->get('options.tempDir'));
            if ($dir === '') {
                $fh = $this->app->make('helper/file');
                $dir = self::normalizeDirectory($fh->getTemporaryDirectory());
                if ($dir === '') {
                    throw new Exception(t('Unable to retrieve the temporary directory.'));
                }
            }
            $dir .= '/translations-cache';
            $this->rootCacheDirectory = $dir;
        }

        return $this->rootCacheDirectory;
    }

    /**
     * @param PackageVersionEntity $packageVersion
     * @param LocaleEntity $locale
     * @param bool $create
     */
    protected function getCacheDirectory(PackageVersionEntity $packageVersion, LocaleEntity $locale, $create = true)
    {
        $fullInt = str_pad((string) $packageVersion->getID(), strlen((string) PHP_INT_MAX), '0', STR_PAD_LEFT);
        $fullInt = strrev($fullInt);
        $parts = [$this->getRootCacheDirectory(), $locale->getID()];
        $parts = array_merge($parts, str_split($fullInt, 2));

        $dir = implode('/', $parts);
        if ($create && !$this->fs->isDirectory($dir)) {
            if ($this->fs->makeDirectory($dir, DIRECTORY_PERMISSIONS_MODE_COMPUTED, true, true) !== true) {
                throw new UserMessageException(t('Failed to create a cache directory'));
            }
        }

        return $dir;
    }

    /**
     * @param PackageVersionEntity $packageVersion
     * @param LocaleEntity $locale
     * @param TranslationsConverter $format
     *
     * @throws UserMessageException
     *
     * @return string
     */
    public function getSerializedTranslationsFile(PackageVersionEntity $packageVersion, LocaleEntity $locale, TranslationsConverter $format)
    {
        $fileMTime = null;
        $file = $this->getCacheDirectory($packageVersion, $locale, true) . '/data.' . $format->getHandle();
        if ($this->fs->isFile($file)) {
            $fileMTime = $this->fs->lastModified($file) ?: null;
        }
        if ($fileMTime === null) {
            $refreshCache = true;
        } else {
            $refreshCache = false;
            $stats = $this->app->make(StatsRepository::class)->getOne($packageVersion, $locale);
            $lastUpdated = $stats->getLastUpdated();
            if ($lastUpdated !== null && $lastUpdated->getTimestamp() > $fileMTime) {
                $refreshCache = true;
            }
        }
        if ($refreshCache) {
            $translations = $this->app->make(Exporter::class)->forPackage($packageVersion, $locale);
            $serializedTranslations = $format->convertTranslationsToString($translations);
            unset($translations);
            if (@$this->fs->put($file, $serializedTranslations, true) === false) {
                throw new UserMessageException(t('Failed to create a cache file'));
            }
        }

        return $file;
    }

    /**
     * @param PackageVersionEntity $packageVersion
     * @param LocaleEntity $locale
     * @param TranslationsConverter $format
     *
     * @throws UserMessageException
     *
     * @return string
     */
    public function getSerializedTranslations(PackageVersionEntity $packageVersion, LocaleEntity $locale, TranslationsConverter $format)
    {
        $file = $this->getSerializedTranslationsFile($packageVersion, $locale, $format);

        $result = $this->fs->get($file);
        if ($result === false) {
            throw new UserMessageException(t('Failed to read a cache file'));
        }

        return $result;
    }
}
