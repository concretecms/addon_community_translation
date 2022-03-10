<?php

declare(strict_types=1);

namespace CommunityTranslation\Parser;

use Concrete\Core\Error\UserMessageException;

defined('C5_EXECUTE') or die('Access Denied.');

interface ParserInterface
{
    /**
     * Do not parse translation dictionaries (eg. gettext .po/.mo/.pot files).
     *
     * @var int
     */
    public const DICTIONARY_NONE = 0x0000;

    /**
     * Parse source translation dictionaries (eg. gettext .pot files).
     *
     * @var int
     */
    public const DICTIONARY_SOURCE = 0x0001;

    /**
     * Parse uncompiled translated dictionaries (eg. gettext .po files).
     *
     * @var int
     */
    public const DICTIONARY_LOCALIZED_SOURCE = 0x0002;

    /**
     * Parse compiled translated dictionaries (eg. gettext .mo files).
     *
     * @var int
     */
    public const DICTIONARY_LOCALIZED_COMPILED = 0x0004;

    /**
     * Parse all dictionaries (eg. gettext .po/.mo/.pot files).
     *
     * @var int
     */
    public const DICTIONARY_ALL = 0xFFFF;

    /**
     * Get a display name that identifies this parser.
     *
     * @return string
     */
    public function getDisplayName(): string;

    /**
     * Extract translations from a directory, a source file, a dictionary file or a zip archive.
     *
     * @param string $packageHandle The package handle being parsed
     * @param string $packageVersion The package version being parsed
     * @param string $path The path to the directory/file to be parsed (or a DecompressedPackage instance)
     * @param string $relDirectory The relative path to be used in translation comments
     * @param int $searchDictionaryFiles One or more of the ParserInterface::DICTIONARY_... constants
     *
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return \CommunityTranslation\Parser\Parsed|null Returns null if no string has been found
     */
    public function parse(string $packageHandle, string $packageVersion, string $path, string $relDirectory = '', int $searchDictionaryFiles = self::DICTIONARY_ALL): ?Parsed;

    /**
     * Extract translations from a source file, a translation dictionary or a zip archive.
     *
     * @param string $packageHandle The package handle being parsed
     * @param string $packageVersion The package version being parsed
     * @param string $path The path to the file to be parsed
     * @param string $relDirectory The relative path to be used in translation comments
     * @param int $searchDictionaryFiles One or more of the ParserInterface::DICTIONARY_... constants
     *
     * @throws UserMessageException
     *
     * @return \CommunityTranslation\Parser\Parsed|null Returns null if no string has been found
     */
    public function parseFile(string $packageHandle, string $packageVersion, string $path, string $relDirectory = '', int $searchDictionaryFiles = self::DICTIONARY_ALL): ?Parsed;

    /**
     * Extract translations from a zip archive.
     *
     * @param string $packageHandle The package handle being parsed
     * @param string $packageVersion The package version being parsed
     * @param string $path The path to the zip archive to be parsed
     * @param string $relDirectory The relative path to be used in translation comments
     * @param int $searchDictionaryFiles One or more of the ParserInterface::DICTIONARY_... constants
     *
     * @throws UserMessageException
     *
     * @return \CommunityTranslation\Parser\Parsed|null Returns null if no string has been found
     */
    public function parseZip(string $packageHandle, string $packageVersion, string $path, string $relDirectory = '', int $searchDictionaryFiles = self::DICTIONARY_ALL): ?Parsed;

    /**
     * Extract translations from a directory.
     *
     * @param string $packageHandle The package handle being parsed
     * @param string $packageVersion The package version being parsed
     * @param string $relDirectory The relative path to be used in translation comments
     * @param string $path The path to the directory to be parsed
     * @param string $relDirectory The relative path to be used in translation comments
     * @param int $searchDictionaryFiles One or more of the ParserInterface::DICTIONARY_... constants
     *
     * @throws UserMessageException
     *
     * @return \CommunityTranslation\Parser\Parsed|null Returns null if no string has been found
     */
    public function parseDirectory(string $packageHandle, string $packageVersion, string $path, string $relDirectory = '', int $searchDictionaryFiles = self::DICTIONARY_ALL): ?Parsed;
}
