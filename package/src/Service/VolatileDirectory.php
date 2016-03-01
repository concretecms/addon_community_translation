<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

use Illuminate\Filesystem\Filesystem;
use Concrete\Package\CommunityTranslation\Src\Exception;

class VolatileDirectory
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    protected $path = null;

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
            throw new Exception(t('Unable to retrieve the temporary directory.'));
        }
        if (!$this->filesystem->isWritable($parentDirectory)) {
            throw new Exception(t('The temporary directory is not writable.'));
        }
        $path = @tempnam($parentDirectory, 'VD');
        @$this->filesystem->delete(array($path));
        @$this->filesystem->makeDirectory($path);
        if (!$this->filesystem->isDirectory($path)) {
            throw new Exception(t('Unable to create a temporary directory.'));
        }
        $this->path = $path;
    }

    public function getPath()
    {
        return $this->path;
    }

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
