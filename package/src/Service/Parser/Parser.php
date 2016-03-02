<?php
namespace Concrete\Package\CommunityTranslation\Src\Service\Parser;

use Concrete\Core\Application\Application;
use Concrete\Core\Application\ApplicationAwareInterface;
use ZipArchive;
use Illuminate\Filesystem\Filesystem;
use Concrete\Package\CommunityTranslation\Src\UserException;

class Parser implements ApplicationAwareInterface
{
    const GETTEXT_NONE = 0;
    const GETTEXT_POT = 1;
    const GETTEXT_PO = 2;
    const GETTEXT_MO = 4;
    const GETTEXT_ALL = -1;

    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * Set the application object.
     *
     * @param Application $application
     */
    public function setApplication(Application $app)
    {
        $this->app = $app;
    }

    /**
     * The Filesystem instance to use.
     *
     * @var Filesystem|null
     */
    protected $filesystem = null;

    /**
     * Set the Filesystem instance to use.
     *
     * @param Filesystem $filesystem
     */
    public function setFilesystem(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Get the Filesystem instance to use.
     *
     * @return Filesystem
     */
    public function getFilesystem()
    {
        if ($this->filesystem === null) {
            $this->filesystem = new Filesystem();
        }

        return $this->filesystem;
    }

    /**
     * Extract translations from a directory, a source file, a gettext file or a zip archive.
     *
     * @param string|ZipArchive $path
     * @param string $relDirectory
     * @param int $searchGettextFiles One or more of the Parser::GETTEXT_... constants
     *
     * @throws UserException
     *
     * @return Parsed|null
     */
    public function parse($path, $relDirectory = '', $searchGettextFiles = self::GETTEXT_ALL)
    {
        $fs = $this->getFilesystem();
        if (is_object($path) && $path instanceof ZipArchive) {
            $result = $this->parseZip($path, $relDirectory, $searchGettextFiles);
        } elseif ($fs->isFile($path)) {
            $result = $this->parseFile($path, $relDirectory, $searchGettextFiles);
        } elseif ($fs->isDirectory($path)) {
            $result = $this->parseDirectory($path, $relDirectory, $searchGettextFiles);
        } else {
            throw new UserException(t('Unable to find the file/directory %s', $path));
        }

        return $result;
    }

    /**
     * Extract translations from a source file, a gettext file or a zip archive.
     *
     * @param string|ZipArchive $path
     * @param string $relDirectory
     * @param int $searchGettextFiles One or more of the Parser::GETTEXT_... constants
     *
     * @throws UserException
     *
     * @return Parsed|null
     */
    public function parseFile($path, $relDirectory = '', $searchGettextFiles = self::GETTEXT_ALL)
    {
        $fs = $this->getFilesystem();
        if (is_object($path) && $path instanceof ZipArchive) {
            $zip = $path;
        } else {
            if (!$fs->isFile($path)) {
                throw new UserException(t('Unable to find the file %s', $path));
            }
            $zip = null;
            try {
                $zip = new ZipArchive();
                if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
                    try {
                        $zip->close();
                    } catch (\Exception $foo) {
                    }
                    $zip = null;
                }
            } catch (\Exception $foo) {
                $zip = null;
            }
        }
        if ($zip !== null) {
            $result = $this->parseZip($zip, $relDirectory, $searchGettextFiles);
        } else {
            $result = null;
            if ($searchGettextFiles !== self::GETTEXT_NONE) {
                $result = $this->parseGettextFile($path, $searchGettextFiles);
            }
            if ($result === null) {
                $result = $this->parseSourceFile($zip, $relDirectory);
            }
        }

        return $result;
    }

    /**
     * Extract translations from a zip archive.
     *
     * @param ZipArchive $zip
     * @param string $relDirectory
     * @param int $searchGettextFiles One or more of the Parser::GETTEXT_... constants
     *
     * @throws UserException
     *
     * @return Parsed|null
     */
    public function parseZip(ZipArchive $zip, $relDirectory = '', $searchGettextFiles = self::GETTEXT_ALL)
    {
        $tmp = $this->app->make('community_translation/tempdir');
        $workDir = $tmp->getPath();
        if (@$zip->extractTo($workDir) !== true) {
            unset($tmp);
            throw new UserException(t('Failed to extract the archive contents'));
        }
        $fs = $this->getFilesystem();
        $dirs = array_filter($fs->directories($workDir), function ($dn) {
            return strpos(basename($dn), '.') !== 0;
        });
        if (count($dirs) === 1) {
            $someFile = false;
            foreach ($fs->files($workDir) as $fn) {
                if (strpos(basename($fn), '.') !== 0) {
                    $someFile = true;
                    break;
                }
            }
            if ($someFile === false) {
                $workDir = $dirs[0];
            }
        }
        $result = $this->parseDirectory($workDir, $relDirectory, $searchGettextFiles);
        unset($tmp);

        return $result;
    }

    /**
     * Extract translations from a directory.
     *
     * @param string $path
     * @param string $relDirectory
     * @param int $searchGettextFiles One or more of the Parser::GETTEXT_... constants
     *
     * @throws UserException
     *
     * @return Parsed|null
     */
    public function parseDirectory($path, $relDirectory = '', $searchGettextFiles = self::GETTEXT_ALL)
    {
        $fs = $this->getFilesystem();
        if (!$fs->isDirectory($path)) {
            throw new UserException(t('Unable to find the directory %s', $path));
        }
        $result = null;
        $pot = new \Gettext\Translations();
        $pot->setLanguage('en_US');
        \C5TL\Parser::clearCache();
        foreach (\C5TL\Parser::getAllParsers() as $parser) {
            /* @var \C5TL\Parser $parser */
            if ($parser->canParseDirectory()) {
                $parser->parseDirectory($path, $relDirectory, $pot);
            }
        }
        if (count($pot) > 0) {
            $result = new Parsed();
            $result->setPot($pot);
        }
        if ($searchGettextFiles !== self::GETTEXT_NONE) {
            foreach ($fs->allFiles($path) as $file) {
                $kind = self::GETTEXT_NONE;
                switch (strtolower($file->getExtension())) {
                    case 'pot':
                        if ($searchGettextFiles & self::GETTEXT_POT) {
                            $kind = self::GETTEXT_POT;
                        }
                        break;
                    case 'po':
                        if ($searchGettextFiles & self::GETTEXT_PO) {
                            $kind = self::GETTEXT_PO;
                        }
                        break;
                    case 'mo':
                        if ($searchGettextFiles & self::GETTEXT_MO) {
                            $kind = self::GETTEXT_MO;
                        }
                        break;
                }
                if ($kind === self::GETTEXT_NONE) {
                    continue;
                }
                $parsed = $this->parseGettextFile($file->getPathname(), $kind);
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
     * Extract translations from gettext .pot/.po file.
     *
     * @param string $path
     * @param int $kinds One or more of self::GETTEXT_POT, self::GETTEXT_PO, self::GETTEXT_MO
     *
     * @throws UserException
     *
     * @return Parsed|null
     */
    public function parseGettextFile($path, $kinds)
    {
        $fs = $this->getFilesystem();
        if (!$fs->isFile($path)) {
            throw new UserException(t('Unable to find the file %s', $path));
        }
        $translations = null;
        if ($kinds & (self::GETTEXT_POT | self::GETTEXT_PO)) {
            try {
                $translations = \Gettext\Translations::fromPoFile($path);
                if (count($translations) === 0) {
                    $translations = null;
                }
            } catch (\Exception $x) {
                $translations = null;
            }
        }
        if ($translations === null && ($kinds & self::GETTEXT_MO)) {
            try {
                $translations = \Gettext\Translations::fromMoFile($path);
                if (count($translations) === 0) {
                    $translations = null;
                }
            } catch (\Exception $x) {
                $translations = null;
            }
        }
        $result = null;
        if ($translations !== null) {
            $locale = null;
            $localeID = $translations->getLanguage();
            if ($localeID) {
                $locale = $this->app->make('community_translation/locale')->findOneBy(array('lID' => $localeID, 'lIsSource' => false, 'lIsApproved' => true));
            }
            if ($locale === null) {
                if ($kinds & self::GETTEXT_POT) {
                    $translations->setLanguage('en_US');
                    foreach ($translations as $translation) {
                        $translation->setTranslation('');
                        $translation->setPluralTranslation('');
                    }
                    $result = new Parsed();
                    $result->setPot($translations);
                }
            } else {
                if ($kinds & (self::GETTEXT_PO | self::GETTEXT_MO)) {
                    $translations->setLanguage($locale->getID());
                    $result = new Parsed();
                    $result->setPo($locale, $translations);
                }
            }
        }

        return $result;
    }

    /**
     * Extract translations from a source file (.php, .xml, ...).
     *
     * @param string $path
     *
     * @throws UserException
     *
     * @return Parsed|null
     */
    public function parseSourceFile($path, $relDirectory = '')
    {
        $fs = $this->getFilesystem();
        if (!$fs->isFile($path)) {
            throw new UserException(t('Unable to find the file %s', $path));
        }
        $tmp = $this->app->make('community_translation/tempdir');
        $workDir = $tmp->getPath();
        if (!@$fs->copy($path, $workDir.'/'.basename($path))) {
            unset($tmp);
            throw new UserException(t('Failed to copy a temporary file'));
        }
        $result = $this->parseDirectory($workDir, $relDirectory, '');
        unset($tmp);

        return $result;
    }
}
