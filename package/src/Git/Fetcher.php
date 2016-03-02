<?php
namespace Concrete\Package\CommunityTranslation\Src\Git;

use Concrete\Core\Application\Application;
use Concrete\Core\Package\Package;
use Illuminate\Filesystem\Filesystem;
use Concrete\Package\CommunityTranslation\Src\UserException;

class Fetcher implements \Concrete\Core\Application\ApplicationAwareInterface
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
     * The associated Repository.
     *
     * @var Repository
     */
    protected $repository;

    /**
     * The Filesystem instance to use.
     *
     * @var Filesystem|null
     */
    protected $filesystem;

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
     * The working directory.
     *
     * @var string
     */
    protected $directory;

    /**
     * Get the working directory.
     *
     * @return string
     *
     * @throws UserException
     */
    protected function getDirectory()
    {
        if ($this->directory === null) {
            $config = Package::getByHandle('community_translation')->getFileConfig();
            $dir = $config->get('options.tempDir');
            $dir = is_string($dir) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $dir), '/') : '';
            if ($dir === '') {
                $fh = $this->app->make('helper/file');
                /* @var \Concrete\Core\File\Service\File $fh */
                $dir = $fh->getTemporaryDirectory();
                $dir = is_string($dir) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $dir), '/') : '';
                if ($dir === '') {
                    throw new UserException(t('Unable to retrieve the temporary directory.'));
                }
            }
            $fs = $this->getFilesystem();
            $dir = $dir.'/translate-repositories';
            if (!@$fs->isDirectory($dir)) {
                @$fs->makeDirectory($dir, DIRECTORY_PERMISSIONS_MODE_COMPUTED, true);
                if (!@$fs->isDirectory($dir)) {
                    throw new UserException(t('Unable to create a temporary directory.'));
                }
            }
            $file = $dir.'/index.html';
            if (!$fs->isFile($file)) {
                if (@$fs->put($file, '') === false) {
                    throw new UserException(t('Error initializing a temporary directory.'));
                }
            }
            $file = $dir.'/.htaccess';
            if (!$fs->isFile($file)) {
                if (@$fs->put($file, <<<EOT
Order deny,allow
Deny from all
php_flag engine off
EOT
                    ) === false) {
                    throw new UserException(t('Error initializing a temporary directory.'));
                }
            }
            $handle = strtolower($this->repository->getURL());
            $handle = preg_replace('/[^a-z0-9\-_\.]+/', '_', $handle);
            $handle = preg_replace('/_+/', '_', $handle);
            $dir .= '/'.$handle;
            $this->directory = $dir;
        }

        return $this->directory;
    }

    /**
     * Return the directory containing the files to be parsed.
     *
     * @return string
     */
    public function getWebDirectory()
    {
        $dir = $this->getDirectory();
        $web = trim(str_replace(DIRECTORY_SEPARATOR, '/', $this->repository->getWebRoot()), '/');
        if ($web !== '') {
            $dir .= '/'.$web;
        }

        return $dir;
    }

    /**
     * Initializes the instance.
     *
     * @param Repository $repository
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
        $this->filesystem = null;
        $this->directory = null;
    }

    /**
     * Initializes the git repository (if the local directory exists it will be erased), checking out the first development branch (if defined) or the repository default one.
     *
     * @throws UserException
     */
    public function initialize()
    {
        $directory = $this->getDirectory();
        $fs = $this->getFilesystem();
        if (@$fs->isDirectory($directory)) {
            @$fs->cleanDirectory($directory);
            if (count($fs->files($directory)) > 0 || count($fs->directories($directory)) > 0) {
                throw new UserException(t('Failed to empty directory %s', $directory));
            }
        } else {
            @$fs->makeDirectory($directory, DIRECTORY_PERMISSIONS_MODE_COMPUTED);
            if (!@$fs->isDirectory($directory)) {
                throw new UserException(t('Failed to create directory %s', $directory));
            }
        }
        try {
            $cmd = 'clone --quiet --no-checkout';
            $cmd .= ' '.escapeshellarg($this->repository->getURL()).' '.escapeshellarg(str_replace('/', DIRECTORY_SEPARATOR, $directory));
            $this->runGit($cmd, false);
        } catch (\Exception $x) {
            try {
                $fs->deleteDirectory($directory);
            } catch (\Exception $foo) {
            }
            throw $x;
        }
    }

    /**
     * Initializes or update the git repository.
     *
     * @throws UserException
     */
    public function update()
    {
        $directory = $this->getDirectory();
        $fs = $this->getFilesystem();
        if ($fs->isDirectory($directory) && $fs->isDirectory($directory.'/.git')) {
            $this->runGit('fetch origin --tags');
        } else {
            $this->initialize();
        }
    }

    /**
     * Checkout the specified remote branch.
     *
     * @param string $name
     *
     * @throws UserException
     */
    public function switchToBranch($name)
    {
        $this->runGit('checkout '.escapeshellarg('remotes/origin/'.$name));
    }

    /**
     * Checkout the specified tag.
     *
     * @param string $tag
     *
     * @throws UserException
     */
    public function switchToTag($tag)
    {
        $this->runGit('checkout '.escapeshellarg("tags/$tag"));
    }

    /**
     * Returns the list of tags and their associated versions (keys are the tags, values are the versions).
     *
     * @return array
     */
    public function getTaggedVersions()
    {
        $tagsFilter = $this->repository->getTagsFilterExpanded();

        $taggedVersions = array();
        foreach ($this->runGit('tag --list') as $tag) {
            $version = trim($tag);
            if (preg_match('/^v\.?\s*(\d.+)$/', $version, $m)) {
                $version = $m[1];
            }
            if (preg_match('/^\d+(\.\d+)+$/', $version)) {
                if ($tagsFilter === null || version_compare($version, $tagsFilter['version'], $tagsFilter['operator'])) {
                    $taggedVersions[$tag] = $version;
                }
            }
        }

        return $taggedVersions;
    }

    /**
     * Execute a git command.
     *
     * @param string $cmd
     * @param bool $setDirectories
     *
     * @return string[]
     *
     * @throws UserException
     */
    private function runGit($cmd, $setDirectories = true)
    {
        static $execAvailable;
        if (!isset($execAvailable)) {
            $safeMode = @ini_get('safe_mode');
            if (!empty($safeMode)) {
                throw new UserException(t("Safe-mode can't be on"));
            }
            if (!function_exists('exec')) {
                throw new UserException(t("exec() function is missing"));
            }
            if (in_array('exec', array_map('trim', explode(',', strtolower(@ini_get('disable_functions')))))) {
                throw new UserException(t("exec() function is disabled"));
            }
            $execAvailable = true;
        }
        $line = 'git';
        if ($setDirectories) {
            $dir = $this->getDirectory();
            $line .= ' --git-dir='.escapeshellarg(str_replace('/', DIRECTORY_SEPARATOR, $dir.'/.git'));
            $line .= ' --work-tree='.escapeshellarg(str_replace('/', DIRECTORY_SEPARATOR, $dir));
        }
        $line .= ' '.$cmd.' 2>&1';
        $rc = 1;
        $output = array();
        @exec($line, $output, $rc);
        if ($rc !== 0) {
            throw new UserException(t('Command failed with return code %1$s: %2$s', $rc, implode("\n", $output)));
        }

        return $output;
    }
}
