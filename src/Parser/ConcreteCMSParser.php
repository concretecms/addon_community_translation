<?php

declare(strict_types=1);

namespace CommunityTranslation\Parser;

use C5TL\Parser as C5TLParser;
use CommunityTranslation\Entity\Package\Version;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Service\SourceLocale;
use CommunityTranslation\Service\VolatileDirectoryCreator;
use Concrete\Core\Error\UserMessageException;
use Exception;
use Gettext\Translations;

defined('C5_EXECUTE') or die('Access Denied.');

class ConcreteCMSParser extends Parser
{
    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\ParserInterface::getDisplayName()
     */
    public function getDisplayName(): string
    {
        return t('ConcreteCMS Parser');
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\ParserInterface::parseDirectory()
     */
    public function parseDirectory(string $packageHandle, string $packageVersion, string $path, string $relDirectory = '', int $searchDictionaryFiles = self::DICTIONARY_ALL): ?Parsed
    {
        $sourceLocale = $this->app->make(SourceLocale::class)->getRequiredSourceLocale();
        if (!$this->filesystem->isDirectory($path)) {
            throw new UserMessageException(t('Unable to find the directory %s', $path));
        }
        if ($relDirectory === '' && $packageHandle !== '') {
            switch ($packageHandle) {
                case 'concrete5':
                    $relDirectory = 'concrete';
                    break;
                default:
                    $relDirectory = "packages/{$packageHandle}";
                    break;
            }
        }
        $pot = new Translations();
        $pot->setLanguage($sourceLocale->getID());
        $pot->setPluralForms($sourceLocale->getPluralCount(), $sourceLocale->getPluralFormula());
        C5TLParser::clearCache();
        $m = null;
        if (preg_match('/^' . preg_quote(Version::DEV_PREFIX, '/') . '(\d+(?:\.\d+)*)/', $packageVersion, $m)) {
            $checkVersion = $m[1] . '.99.99';
        } else {
            $checkVersion = $packageVersion;
        }
        foreach (C5TLParser::getAllParsers() as $parser) {
            if ($parser->canParseDirectory()) {
                if ($packageHandle !== 'concrete5' || $parser->canParseConcreteVersion($checkVersion)) {
                    $parser->parseDirectory($path, $relDirectory, $pot);
                }
            }
        }
        if (count($pot) > 0) {
            $result = new Parsed();
            $result->setSourceStrings($pot);
        } else {
            $result = null;
        }
        if ($searchDictionaryFiles === self::DICTIONARY_NONE) {
            return $result;
        }
        foreach ($this->filesystem->allFiles($path) as $file) {
            $kind = self::DICTIONARY_NONE;
            switch (strtolower($file->getExtension())) {
                case 'pot':
                    if ($searchDictionaryFiles & self::DICTIONARY_SOURCE) {
                        $kind = self::DICTIONARY_SOURCE;
                    }
                    break;
                case 'po':
                    if ($searchDictionaryFiles & self::DICTIONARY_LOCALIZED_SOURCE) {
                        $kind = self::DICTIONARY_LOCALIZED_SOURCE;
                    }
                    break;
                case 'mo':
                    if ($searchDictionaryFiles & self::DICTIONARY_LOCALIZED_COMPILED) {
                        $kind = self::DICTIONARY_LOCALIZED_COMPILED;
                    }
                    break;
            }
            if ($kind === self::DICTIONARY_NONE) {
                continue;
            }
            $parsed = $this->parseDictionaryFile($packageHandle, $packageVersion, $file->getPathname(), $kind);
            if ($parsed !== null) {
                if ($result === null) {
                    $result = $parsed;
                } else {
                    $result->mergeWith($parsed);
                }
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\Parser::parseDictionaryFile()
     */
    protected function parseDictionaryFile(string $packageHandle, string $packageVersion, string $path, int $kinds): ?Parsed
    {
        if (!$this->filesystem->isFile($path)) {
            throw new UserMessageException(t('Unable to find the file %s', $path));
        }
        $translations = null;
        if ($kinds & (self::DICTIONARY_SOURCE | self::DICTIONARY_LOCALIZED_SOURCE)) {
            try {
                $translations = Translations::fromPoFile($path);
                if (count($translations) === 0) {
                    $translations = null;
                }
            } catch (Exception $x) {
                $translations = null;
            }
        }
        if ($translations === null && ($kinds & self::DICTIONARY_LOCALIZED_COMPILED)) {
            try {
                $translations = Translations::fromMoFile($path);
                if (count($translations) === 0) {
                    $translations = null;
                }
            } catch (Exception $x) {
                $translations = null;
            }
        }
        if ($translations === null) {
            return null;
        }
        /** @var \Gettext\Translations $translations */
        $localeID = $translations->getLanguage();
        $locale = is_string($localeID) && $localeID !== '' ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
        if ($locale === null) {
            if (!($kinds & self::DICTIONARY_SOURCE)) {
                return null;
            }
            $sourceLocale = $this->app->make(SourceLocale::class)->getRequiredSourceLocale();
            $translations->setLanguage($sourceLocale->getID());
            $translations->setPluralForms($sourceLocale->getPluralCount(), $sourceLocale->getPluralFormula());
            foreach ($translations as $translation) {
                /** @var \Gettext\Translation $translation */
                $translation->setTranslation('');
                $translation->deletePluralTranslation();
            }
            $result = new Parsed();

            return $result->setSourceStrings($translations);
        }
        if (!($kinds & (self::DICTIONARY_LOCALIZED_SOURCE | self::DICTIONARY_LOCALIZED_COMPILED))) {
            return null;
        }
        $translations->setLanguage($locale->getID());
        $translations->setPluralForms($locale->getPluralCount(), $locale->getPluralFormula());
        $result = new Parsed();
        $result->setTranslations($locale, $translations);

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\Parser::parseSourceFile()
     */
    protected function parseSourceFile(string $packageHandle, string $packageVersion, string $path, string $relDirectory = ''): ?Parsed
    {
        if (!$this->filesystem->isFile($path)) {
            throw new UserMessageException(t('Unable to find the file %s', $path));
        }
        $tmp = $this->app->make(VolatileDirectoryCreator::class)->createVolatileDirectory();
        $workDir = $tmp->getPath();
        set_error_handler(static function () {}, -1);
        try {
            $copied = $this->filesystem->copy($path, $workDir . '/' . basename($path));
        } finally {
            restore_error_handler();
        }
        if (!$copied) {
            throw new UserMessageException(t('Failed to copy a temporary file'));
        }

        return $this->parseDirectory($packageHandle, $packageVersion, $workDir, $relDirectory, '');
    }
}
