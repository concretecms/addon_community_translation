<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

use Illuminate\Filesystem\Filesystem;
use Concrete\Package\CommunityTranslation\Src\UserException;
use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Application\Application;

class VolatileDirectory implements ApplicationAwareInterface
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
     * The used Filesystem instance.
     *
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * The path of this volatile directory.
     *
     * @var string|null
     */
    protected $path = null;

    /**
     * Initializes the instance.
     *
     * @param string|null $parentDirectory The parent directory that will contain this volatile directory (if not set we'll detect it).
     * @param Filesystem $filesystem The Filesystem instance to use (we'll create a new instance if not set).
     *
     * @throws UserException
     */
    public function __construct($parentDirectory = null, Filesystem $filesystem = null)
    {
        $this->filesystem = ($filesystem === null) ? new Filesystem() : $filesystem;
        $parentDirectory = is_string($parentDirectory) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $parentDirectory), '/') : '';
        if ($parentDirectory === '') {
            $config = \Package::getByHandle('community_translation')->getFileConfig();
            $parentDirectory = $config->get('options.tempDir');
            $parentDirectory = is_string($parentDirectory) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $parentDirectory), '/') : '';
            if ($parentDirectory === '') {
                $fh = $this->app->make('helper/file');
                /* @var \Concrete\Core\File\Service\File $fh */
                $parentDirectory = $fh->getTemporaryDirectory();
                $parentDirectory = is_string($parentDirectory) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $parentDirectory), '/') : '';
            }
        }
        if ($parentDirectory === '') {
            throw new UserException(t('Unable to retrieve the temporary directory.'));
        }
        if (!$this->filesystem->isWritable($parentDirectory)) {
            throw new UserException(t('The temporary directory is not writable.'));
        }
        $path = @tempnam($parentDirectory, 'VD');
        @$this->filesystem->delete(array($path));
        @$this->filesystem->makeDirectory($path);
        if (!$this->filesystem->isDirectory($path)) {
            throw new UserException(t('Unable to create a temporary directory.'));
        }
        $this->path = $path;
    }

    /**
     * Get the used Filesystem instance.
     *
     * @return Filesystem
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * Get the path of this volatile directory.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Clear and delete this volatile directory.
     */
    public function __destruct()
    {
        if ($this->path !== null) {
            try {
                $this->filesystem->deleteDirectory($this->path);
            } catch (\Exception $foo) {
            }
            $this->path = null;
        }
    }
}
