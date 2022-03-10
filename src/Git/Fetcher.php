<?php

declare(strict_types=1);

namespace CommunityTranslation\Git;

use CommunityTranslation\Entity\GitRepository;
use Concrete\Core\Application\Application;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\File\Service\File as FileService;
use Concrete\Core\Foundation\Environment\FunctionInspector;
use Illuminate\Filesystem\Filesystem;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

final class Fetcher
{
    private Application $app;

    /**
     * The associated Repository.
     */
    private GitRepository $gitRepository;

    /**
     * The Filesystem instance to use.
     */
    private Filesystem $filesystem;

    /**
     * The working tree directory.
     */
    private string $worktreeDirectory = '';

    private string $gitDirectory = '';

    private string $tempDir = '';

    /**
     * Initializes the instance.
     */
    public function __construct(Application $app, GitRepository $gitRepository)
    {
        $this->app = $app;
        $this->gitRepository = $gitRepository;
        $this->filesystem = new Filesystem();
    }

    /**
     * Set the Filesystem instance to be used.
     *
     * @return $this
     */
    public function setFilesystem(Filesystem $filesystem): self
    {
        $this->filesystem = $filesystem;

        return $this;
    }

    /**
     * Get the used Filesystem instance.
     */
    public function getFilesystem(): Filesystem
    {
        return $this->filesystem;
    }

    public function setTempDir(string $value): self
    {
        $this->tempDir = $value;

        return $this;
    }

    public function getTempDir(): string
    {
        if ($this->tempDir !== '') {
            return $this->tempDir;
        }
        $config = $this->app->make(Repository::class);
        $tempDir = $config->get('community_translation::paths.tempDir');
        $tempDir = is_string($tempDir) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $tempDir), '/') : '';
        if ($tempDir !== '') {
            $this->tempDir = $tempDir;

            return $this->tempDir;
        }
        $tempDir = $this->app->make(FileService::class)->getTemporaryDirectory();
        $tempDir = is_string($tempDir) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $tempDir), '/') : '';
        if ($tempDir !== '') {
            $this->tempDir = $tempDir;

            return $this->tempDir;
        }
        throw new UserMessageException(t('Unable to retrieve the temporary directory.'));
    }

    /**
     * Return the directory containing the files to be parsed.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function getRootDirectory(): string
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
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function initialize(): void
    {
        $directory = $this->getWorktreeDirectory();
        if ($this->filesystem->isDirectory($directory)) {
            set_error_handler(static function () {}, -1);
            try {
                $this->filesystem->cleanDirectory($directory);
                if ($this->filesystem->files($directory) !== [] || $this->filesystem->directories($directory) !== []) {
                    throw new UserMessageException(t('Failed to empty directory %s', $directory));
                }
            } finally {
                restore_error_handler();
            }
        } else {
            set_error_handler(static function () {}, -1);
            try {
                if (!$this->filesystem->makeDirectory($directory, DIRECTORY_PERMISSIONS_MODE_COMPUTED)) {
                    throw new UserMessageException(t('Failed to create directory %s', $directory));
                }
            } finally {
                restore_error_handler();
            }
        }
        try {
            $cmd = 'clone --quiet --no-checkout --origin origin';
            $cmd .= ' ' . escapeshellarg($this->gitRepository->getURL()) . ' ' . escapeshellarg(str_replace('/', DIRECTORY_SEPARATOR, $directory));
            $this->runGit($cmd);
        } catch (Throwable $x) {
            set_error_handler(static function () {}, -1);
            try {
                $this->filesystem->deleteDirectory($directory);
            } catch (Throwable $foo) {
            } finally {
                restore_error_handler();
            }
            throw $x;
        }
    }

    /**
     * Initializes or update the git repository.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function update(): void
    {
        if ($this->filesystem->isDirectory($this->getGitDirectory())) {
            $this->runGit('fetch origin --tags');
        } else {
            $this->initialize();
        }
    }

    /**
     * Checkout the specified remote branch.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function switchToBranch(string $name): void
    {
        $this->runGit('checkout --quiet --force ' . escapeshellarg("remotes/origin/{$name}"));
    }

    /**
     * Checkout the specified tag.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function switchToTag(string $tag): void
    {
        $this->runGit('checkout --quiet --force ' . escapeshellarg("tags/{$tag}"));
    }

    /**
     * Returns the list of tags and their associated versions (keys are the tags, values are the versions).
     *
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return array Keys: tag, values: version
     */
    public function getTaggedVersions(): array
    {
        $tagFilters = $this->gitRepository->getTagFiltersExpanded();
        if ($tagFilters === null) {
            return [];
        }
        $taggedVersions = [];
        $matches = null;
        foreach ($this->runGit('tag --list') as $tag) {
            set_error_handler(static function () {}, -1);
            $matchResult = preg_match($this->gitRepository->getTagToVersionRegexp(), $tag, $matches);
            restore_error_handler();
            if ($matchResult === false) {
                throw new UserMessageException(t('Invalid regular expression for git repository %s', $this->gitRepository->getName()));
            }
            if ($matchResult === 0) {
                continue;
            }
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
        uasort($taggedVersions, 'version_compare');

        return $taggedVersions;
    }

    /**
     * Execute a git command.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return string[]
     */
    private function runGit(string $cmd, bool $setDirectories = true): array
    {
        static $execAvailable;
        if ($execAvailable === null) {
            $fi = $this->app->make(FunctionInspector::class);
            $execAvailable = $fi->functionAvailable('exec');
        }
        if (!$execAvailable) {
            throw new UserMessageException(t('exec() function is missing'));
        }
        $line = 'git';
        if ($setDirectories) {
            $line .= ' -C ' . escapeshellarg(str_replace('/', DIRECTORY_SEPARATOR, $this->getWorktreeDirectory()));
        }
        $line .= ' ' . $cmd . ' 2>&1';
        $rc = 1;
        $output = [];
        exec($line, $output, $rc);
        if ($rc !== 0) {
            throw new UserMessageException(t('Command failed with return code %1$s: %2$s', $rc, implode("\n", $output)));
        }

        return $output;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function createWorktreeParentDirectory(): string
    {
        $result = null;
        $tmp = "{$this->getTempDir()}/git-repositories";
        set_error_handler(static function () {}, -1);
        try {
            if (!$this->filesystem->isDirectory($tmp)) {
                $this->filesystem->makeDirectory($tmp, DIRECTORY_PERMISSIONS_MODE_COMPUTED, true);
            }
            if ($this->filesystem->isDirectory($tmp)) {
                $tmp = realpath($tmp);
                if ($tmp !== false) {
                    $result = str_replace(DIRECTORY_SEPARATOR, '/', $tmp);
                }
            }
        } finally {
            restore_error_handler();
        }
        if ($result === null) {
            throw new UserMessageException(t('Unable to create a temporary directory.'));
        }

        return $result;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function createIndexFile(string $filePath, string $fileContent): void
    {
        set_error_handler(static function () {}, -1);
        try {
            if ($this->filesystem->isFile($filePath)) {
                return;
            }
            if ($this->filesystem->put($filePath, $fileContent) === false) {
                return;
            }
        } finally {
            restore_error_handler();
        }
        throw new UserMessageException(t('Error initializing a temporary directory.'));
    }

    /**
     * Get the working tree directory.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function getWorktreeDirectory(): string
    {
        if ($this->worktreeDirectory !== '') {
            return $this->worktreeDirectory;
        }
        $dir = $this->createWorktreeParentDirectory();
        $this->createIndexFile("{$dir}/index.html", '');
        $this->createIndexFile(
            "{$dir}/.htaccess",
            <<<'EOT'
Order deny,allow
Deny from all
php_flag engine off

EOT
        );
        $handle = strtolower($this->gitRepository->getURL());
        $handle = preg_replace('/[^a-z0-9\-_\.]+/', '_', $handle);
        $handle = preg_replace('/_+/', '_', $handle);
        $dir .= '/' . $handle;
        $this->worktreeDirectory = $dir;

        return $this->worktreeDirectory;
    }

    /**
     * Get the git directory.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function getGitDirectory(): string
    {
        if ($this->gitDirectory === '') {
            $this->gitDirectory = $this->getWorktreeDirectory() . '/.git';
        }

        return $this->gitDirectory;
    }
}
