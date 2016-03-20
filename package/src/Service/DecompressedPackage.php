<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

use ZipArchive;
use Concrete\Package\CommunityTranslation\Src\UserException;
use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Application\Application;

class DecompressedPackage implements ApplicationAwareInterface
{
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
     * The full path of the archive file containing the package.
     *
     * @var string
     */
    protected $packageArchive;

    /**
     * Get the full path of the archive file containing the package.
     *
     * @return string
     */
    public function getPackageArchive()
    {
        return $this->packageArchive;
    }

    /**
     * The volatile instance that contains the decompressed contents of the package archive.
     * 
     * @var VolatileDirectory|null
     */
    protected $volatileDirectory = null;

    /**
     * Get the volatile instance that contains the decompressed contents of the package archive.
     *
     * @return VolatileDirectory
     */
    protected function getVolatileDirectory()
    {
        if ($this->volatileDirectory === null) {
            $this->volatileDirectory = $this->app->make('community_translation/tempdir');
        }

        return $this->volatileDirectory;
    }

    /**
     * The working directory contaning the extracted package (null if still not extracted).
     *
     * @var string|null
     */
    protected $extractedWorkDir;

    /**
     * Initializes the instance.
     *
     * @param string $packageArchive The full path of the archive file containing the package.
     * @param VolatileDirectory $volatileDirectory An empty VolatileDirectory instance.
     */
    public function __construct($packageArchive, VolatileDirectory $volatileDirectory = null)
    {
        $this->packageArchive = $packageArchive;
        $this->volatileDirectory = $volatileDirectory;
        $this->extractedWorkDir = null;
    }

    /**
     * Extract the archive (if not already done).
     *
     * @throws UserException
     */
    public function extract()
    {
        if ($this->extractedWorkDir !== null) {
            return;
        }
        if (!is_file($this->packageArchive)) {
            throw new UserException(t('Archive not found: %s', $this->packageArchive));
        }
        $zip = new ZipArchive();
        $zipErr = @$zip->open($this->packageArchive, ZipArchive::CHECKCONS);
        if ($zipErr !== true) {
            try {
                @$zip->close();
            } catch (\Exception $foo) {
            }
            $zip = null;
            switch ($zipErr) {
                case ZipArchive::ER_INCONS:
                    throw new UserException(t('ZIP archive is inconsistent.'));
                case ZipArchive::ER_INVAL:
                    throw new UserException(t('Invalid argument opening ZIP archive.'));
                case ZipArchive::ER_MEMORY:
                    throw new UserException(t('Malloc failure opening ZIP archive.'));
                case ZipArchive::ER_NOENT:
                    throw new UserException(t('No such file opening ZIP archive.'));
                case ZipArchive::ER_NOZIP:
                    throw new UserException(t('Not a zip archive.'));
                case ZipArchive::ER_OPEN:
                    throw new UserException(t('Can\'t open ZIP archive file.'));
                case ZipArchive::ER_READ:
                    throw new UserException(t('Read error opening ZIP archive.'));
                case ZipArchive::ER_SEEK:
                    throw new UserException(t('Seek error opening ZIP archive.'));
                default:
                    throw new UserException(t('Unknown error opening ZIP archive.'));
            }
        }
        try {
            $workDir = $this->getVolatileDirectory()->getPath();
            if (@$zip->extractTo($workDir) !== true) {
                throw new UserException(t('Failed to extract the archive contents'));
            }
            @$zip->close();
            $zip = null;
            $fs = $this->getVolatileDirectory()->getFilesystem();
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
        } catch (\Exception $x) {
            if ($zip !== null) {
                try {
                    @$zip->close();
                } catch (\Exception $foo) {
                }
                $zip = null;
            }
            $this->volatileDirectory = null;
            throw $x;
        }
        $this->extractedWorkDir = $workDir;
    }

    /**
     * Get the working directory contaning the extracted package (we'll extract it if not already done).
     *
     * @throws UserException
     *
     * @return string
     */
    public function getExtractedWorkDir()
    {
        if ($this->extractedWorkDir === null) {
            $this->extract();
        }

        return $this->extractedWorkDir;
    }
}