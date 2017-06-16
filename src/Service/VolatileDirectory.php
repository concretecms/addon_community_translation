<?php

namespace CommunityTranslation\Service;

use Concrete\Core\Application\Application;
use Concrete\Core\Error\UserMessageException;
use Illuminate\Filesystem\Filesystem;

class VolatileDirectory
{
    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

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
     * @param Application $app
     * @param Filesystem $filesystem the Filesystem instance to use (we'll create a new instance if not set)
     * @param string|null $parentDirectory the parent directory that will contain this volatile directory (if not set we'll detect it)
     *
     * @throws UserMessageException
     */
    public function __construct(Application $app, Filesystem $filesystem, $parentDirectory = null)
    {
        $this->app = $app;
        $this->filesystem = $filesystem;
        $parentDirectory = is_string($parentDirectory) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $parentDirectory), '/') : '';
        if ($parentDirectory === '') {
            $config = $this->app->make('community_translation/config');
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
            throw new UserMessageException(t('Unable to retrieve the temporary directory.'));
        }
        if (!$this->filesystem->isWritable($parentDirectory)) {
            throw new UserMessageException(t('The temporary directory is not writable.'));
        }
        $path = @tempnam($parentDirectory, 'VD');
        @$this->filesystem->delete([$path]);
        @$this->filesystem->makeDirectory($path);
        if (!$this->filesystem->isDirectory($path)) {
            throw new UserMessageException(t('Unable to create a temporary directory.'));
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
