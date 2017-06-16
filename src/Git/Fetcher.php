<?php

namespace CommunityTranslation\Git;

use CommunityTranslation\Entity\GitRepository;
use Concrete\Core\Application\Application;
use Concrete\Core\Error\UserMessageException;
use Exception;
use Illuminate\Filesystem\Filesystem;

class Fetcher
{
    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * The associated Repository.
     *
     * @var GitRepository
     */
    protected $gitRepository;

    /**
     * The Filesystem instance to use.
     *
     * @var Filesystem|null
     */
    protected $filesystem = null;

    /**
     * The working tree directory.
     *
     * @var string|null
     */
    protected $worktreeDirectory = null;

    /**
     * The git directory.
     *
     * @var string|null
     */
    protected $gitDirectory = null;

    /**
     * Initializes the instance.
     *
     * @param GitRepository $gitRepository
     */
    public function __construct(Application $app, GitRepository $gitRepository)
    {
        $this->app = $app;
        $this->gitRepository = $gitRepository;
    }

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
     * Get the working tree directory.
     *
     * @throws UserMessageException
     *
     * @return string
     */
    protected function getWorktreeDirectory()
    {
        if ($this->worktreeDirectory === null) {
            $config = $this->app->make('community_translation/config');
            $dir = $config->get('options.tempDir');
            $dir = is_string($dir) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $dir), '/') : '';
            if ($dir === '') {
                $fh = $this->app->make('helper/file');
                $dir = $fh->getTemporaryDirectory();
                $dir = is_string($dir) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $dir), '/') : '';
                if ($dir === '') {
                    throw new UserMessageException(t('Unable to retrieve the temporary directory.'));
                }
            }
            $fs = $this->getFilesystem();
            $dir = $dir . '/git-repositories';
            if (!@$fs->isDirectory($dir)) {
                @$fs->makeDirectory($dir, DIRECTORY_PERMISSIONS_MODE_COMPUTED, true);
                if (!@$fs->isDirectory($dir)) {
                    throw new UserMessageException(t('Unable to create a temporary directory.'));
                }
            }
            $file = $dir . '/index.html';
            if (!$fs->isFile($file)) {
                if (@$fs->put($file, '') === false) {
                    throw new UserMessageException(t('Error initializing a temporary directory.'));
                }
            }
            $file = $dir . '/.htaccess';
            if (!$fs->isFile($file)) {
                if (@$fs->put($file, <<<'EOT'
Order deny,allow
Deny from all
php_flag engine off
EOT
                    ) === false) {
                    throw new UserMessageException(t('Error initializing a temporary directory.'));
                }
            }
            $handle = strtolower($this->gitRepository->getURL());
            $handle = preg_replace('/[^a-z0-9\-_\.]+/', '_', $handle);
            $handle = preg_replace('/_+/', '_', $handle);
            $dir .= '/' . $handle;
            $this->worktreeDirectory = $dir;
        }

        return $this->worktreeDirectory;
    }

    /**
     * Get the git directory.
     *
     * @throws UserMessageException
     *
     * @return string
     */
    protected function getGitDirectory()
    {
        if ($this->gitDirectory === null) {
            $this->gitDirectory = $this->getWorktreeDirectory() . '/.git';
        }

        return $this->gitDirectory;
    }

    /**
     * Return the directory containing the files to be parsed.
     *
     * @throws UserMessageException
     *
     * @return string
     */
    public function getRootDirectory()
    {
        $dir = $this->getWorktreeDirectory();
        $relativeRoot = trim(str_replace(DIRECTORY_SEPARATOR, '/', $this->gitRepository->getDirectoryToParse()), '/');
        if ($relativeRoot !== '') {
            $dir .= '/' . $relativeRoot;
        }

        return $dir;
    }

    /**
     * Initializes the git repository (if the local directory exists it will be erased).
     *
     * @throws UserMessageException
     */
    public function initialize()
    {
        $directory = $this->getWorktreeDirectory();
        $fs = $this->getFilesystem();
        if (@$fs->isDirectory($directory)) {
            @$fs->cleanDirectory($directory);
            if (count($fs->files($directory)) > 0 || count($fs->directories($directory)) > 0) {
                throw new UserMessageException(t('Failed to empty directory %s', $directory));
            }
        } else {
            if (@$fs->makeDirectory($directory, DIRECTORY_PERMISSIONS_MODE_COMPUTED) !== true) {
                throw new UserMessageException(t('Failed to create directory %s', $directory));
            }
        }
        try {
            $cmd = 'clone --quiet --no-checkout --origin origin';
            $cmd .= ' ' . escapeshellarg($this->gitRepository->getURL()) . ' ' . escapeshellarg(str_replace('/', DIRECTORY_SEPARATOR, $directory));
            $this->runGit($cmd);
        } catch (Exception $x) {
            try {
                $fs->deleteDirectory($directory);
            } catch (Exception $foo) {
            }
            throw $x;
        }
    }

    /**
     * Initializes or update the git repository.
     *
     * @throws UserMessageException
     */
    public function update()
    {
        $fs = $this->getFilesystem();
        if ($fs->isDirectory($this->getGitDirectory())) {
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
     * @throws UserMessageException
     */
    public function switchToBranch($name)
    {
        $this->runGit('checkout --quiet --force ' . escapeshellarg("remotes/origin/$name"));
    }

    /**
     * Checkout the specified tag.
     *
     * @param string $tag
     *
     * @throws UserMessageException
     */
    public function switchToTag($tag)
    {
        $this->runGit('checkout --quiet --force ' . escapeshellarg("tags/$tag"));
    }

    /**
     * Returns the list of tags and their associated versions (keys are the tags, values are the versions).
     *
     * @throws UserMessageException
     *
     * @return array Keys: tag, values: version
     */
    public function getTaggedVersions()
    {
        $tagFilters = $this->gitRepository->getTagFiltersExpanded();

        $taggedVersions = [];
        if ($tagFilters !== null) {
            foreach ($this->runGit('tag --list') as $tag) {
                $matchResult = @preg_match($this->gitRepository->getTagToVersionRegexp(), $tag, $matches);
                if ($matchResult === false) {
                    throw new UserMessageException(t('Invalid regular expression for git repository %s', $this->gitRepository->getName()));
                }
                if ($matchResult > 0) {
                    $version = $matches[1];
                    $ok = true;
                    foreach ($tagFilters as $tagFilter) {
                        if (version_compare($version, $tagFilter['version'], $tagFilter['operator']) === false) {
                            $ok = false;
                            break;
                        }
                    }
                    if ($ok) {
                        $taggedVersions[$tag] = $version;
                    }
                }
            }
        }

        uasort($taggedVersions, 'version_compare');

        return $taggedVersions;
    }

    /**
     * Execute a git command.
     *
     * @param string $cmd
     * @param bool $setDirectories
     *
     * @throws UserMessageException
     *
     * @return string[]
     */
    private function runGit($cmd, $setDirectories = true)
    {
        static $execAvailable;
        if (!isset($execAvailable)) {
            $safeMode = @ini_get('safe_mode');
            if (!empty($safeMode)) {
                throw new UserMessageException(t("Safe-mode can't be on"));
            }
            if (!function_exists('exec')) {
                throw new UserMessageException(t('exec() function is missing'));
            }
            if (in_array('exec', array_map('trim', explode(',', strtolower(@ini_get('disable_functions')))))) {
                throw new UserMessageException(t('exec() function is disabled'));
            }
            $execAvailable = true;
        }
        $line = 'git';
        if ($setDirectories) {
            $line .= ' -C ' . escapeshellarg(str_replace('/', DIRECTORY_SEPARATOR, $this->getWorktreeDirectory()));
        }
        $line .= ' ' . $cmd . ' 2>&1';
        $rc = 1;
        $output = [];
        @exec($line, $output, $rc);
        if ($rc !== 0) {
            throw new UserMessageException(t('Command failed with return code %1$s: %2$s', $rc, implode("\n", $output)));
        }

        return $output;
    }
}
