<?php

declare(strict_types=1);

namespace CommunityTranslation\Translation;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Repository\Stats as StatsRepository;
use CommunityTranslation\TranslationsConverter\ConverterInterface as TranslationsConverter;
use Concrete\Core\Application\Application;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Error\UserMessageException;
use Exception;
use Illuminate\Filesystem\Filesystem;

defined('C5_EXECUTE') or die('Access Denied.');

final class FileExporter
{
    private Application $app;

    private Filesystem $fs;

    private ?string $rootCacheDirectory = null;

    public function __construct(Application $app, Filesystem $fs)
    {
        $this->app = $app;
        $this->fs = $fs;
    }

    public function setRootCacheDirectory(string $rootCacheDirectory): self
    {
        $rootCacheDirectory = self::normalizeDirectory($rootCacheDirectory);
        $this->rootCacheDirectory = $rootCacheDirectory === '' ? null : $rootCacheDirectory;

        return $this;
    }

    public function getRootCacheDirectory(): string
    {
        if ($this->rootCacheDirectory !== null) {
            return $this->rootCacheDirectory;
        }
        $config = $this->app->make(Repository::class);
        $dir = self::normalizeDirectory($config->get('community_translation::paths.tempDir'));
        if ($dir === '') {
            $fh = $this->app->make('helper/file');
            $dir = self::normalizeDirectory($fh->getTemporaryDirectory());
            if ($dir === '') {
                throw new Exception(t('Unable to retrieve the temporary directory.'));
            }
        }
        $dir .= '/translations-cache';
        $this->rootCacheDirectory = $dir;

        return $this->rootCacheDirectory;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function getSerializedTranslationsFile(PackageVersionEntity $packageVersion, LocaleEntity $locale, TranslationsConverter $format): string
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
            $serializedTranslations = $format->serializeTranslations($translations);
            unset($translations);
            set_error_handler(static function () {}, -1);
            try {
                $saved = $this->fs->put($file, $serializedTranslations, true);
            } finally {
                restore_error_handler();
            }
            if ($saved === false) {
                throw new UserMessageException(t('Failed to create a cache file'));
            }
        }

        return $file;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function getSerializedTranslations(PackageVersionEntity $packageVersion, LocaleEntity $locale, TranslationsConverter $format): string
    {
        $file = $this->getSerializedTranslationsFile($packageVersion, $locale, $format);

        $result = $this->fs->get($file);
        if ($result === false) {
            throw new UserMessageException(t('Failed to read a cache file'));
        }

        return $result;
    }

    private function getCacheDirectory(PackageVersionEntity $packageVersion, LocaleEntity $locale, bool $create = true): string
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
     * @param string|mixed $dir
     */
    private static function normalizeDirectory($dir): string
    {
        return is_string($dir) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $dir), '/') : '';
    }
}
