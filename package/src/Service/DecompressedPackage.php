<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

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
        try {
            $workDir = $this->getVolatileDirectory()->getPath();
            try {
                $this->app->make('helper/zip')->unzip($this->packageArchive, $workDir);
            } catch (\Exception $x) {
                throw new UserException($x->getMessage());
            }
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

    /**
     * Re-create the source archive with the contents of the extracted directory.
     *
     * @throws UserException
     */
    public function repack()
    {
        $this->extract();
        try {
            $this->app->make('helper/zip')->zip($this->getVolatileDirectory()->getPath(), $this->packageArchive, array('includeDotFiles' => true));
        } catch (\Exception $x) {
            throw new UserException($x->getMessage());
        }
    }
}
