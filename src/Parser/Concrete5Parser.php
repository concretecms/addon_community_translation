<?php

namespace CommunityTranslation\Parser;

use C5TL\Parser as C5TLParser;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Service\VolatileDirectory;
use CommunityTranslation\UserException;
use Exception;
use Gettext\Translations;

class Concrete5Parser extends Parser
{
    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\ParserInterface::getDisplayName()
     */
    public function getDisplayName()
    {
        return t('concrete5 Parser');
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\ParserInterface::parseDirectory()
     */
    public function parseDirectory($packageHandle, $packageVersion, $path, $relDirectory = '', $searchDictionaryFiles = self::DICTIONARY_ALL)
    {
        if (!$this->filesystem->isDirectory($path)) {
            throw new UserException(t('Unable to find the directory %s', $path));
        }
        if ('' === (string) $relDirectory && '' !== (string) $packageHandle) {
            switch ($packageHandle) {
                case 'concrete5':
                    $relDirectory = 'concrete';
                    break;
                default:
                    $relDirectory = 'packages/' . $packageHandle;
                    break;
            }
        }
        $result = null;
        $pot = new Translations();
        $pot->setLanguage($this->app->make('community_translation/sourceLocale'));
        C5TLParser::clearCache();
        foreach (C5TLParser::getAllParsers() as $parser) {
            /* @var C5TLParser $parser */
            if ($parser->canParseDirectory()) {
                if ($packageHandle !== 'concrete5' || $parser->canParseConcreteVersion($packageVersion)) {
                    $parser->parseDirectory($path, $relDirectory, $pot);
                }
            }
        }
        if (count($pot) > 0) {
            $result = new Parsed();
            $result->setSourceStrings($pot);
        }
        if ($searchDictionaryFiles !== self::DICTIONARY_NONE) {
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
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\Parser::parseDictionaryFile()
     */
    public function parseDictionaryFile($packageHandle, $packageVersion, $path, $kinds)
    {
        if (!$this->filesystem->isFile($path)) {
            throw new UserException(t('Unable to find the file %s', $path));
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
        $result = null;
        if ($translations !== null) {
            $locale = null;
            $localeID = $translations->getLanguage();
            $locale = $localeID ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
            if ($locale === null) {
                if ($kinds & self::DICTIONARY_SOURCE) {
                    $translations->setLanguage($this->app->make('community_translation/sourceLocale'));
                    foreach ($translations as $translation) {
                        $translation->setTranslation('');
                        $translation->setPluralTranslation('');
                    }
                    $result = new Parsed();
                    $result->setSourceStrings($translations);
                }
            } else {
                if ($kinds & (self::DICTIONARY_LOCALIZED_SOURCE | self::DICTIONARY_LOCALIZED_COMPILED)) {
                    $translations->setLanguage($locale->getID());
                    $result = new Parsed();
                    $result->setTranslations($locale, $translations);
                }
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Parser\Parser::parseSourceFile()
     */
    public function parseSourceFile($packageHandle, $packageVersion, $path, $relDirectory = '')
    {
        if (!$this->filesystem->isFile($path)) {
            throw new UserException(t('Unable to find the file %s', $path));
        }
        $tmp = $this->app->make(VolatileDirectory::class);
        $workDir = $tmp->getPath();
        if (!@$this->filesystem->copy($path, $workDir . '/' . basename($path))) {
            unset($tmp);
            throw new UserException(t('Failed to copy a temporary file'));
        }
        $result = $this->parseDirectory($packageHandle, $packageVersion, $workDir, $relDirectory, '');
        unset($tmp);

        return $result;
    }
}
