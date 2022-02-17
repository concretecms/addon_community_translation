<?php

declare(strict_types=1);

namespace CommunityTranslation\Service;

use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\File\Service\File as FileService;
use Concrete\Core\File\Service\VolatileDirectory;
use Illuminate\Filesystem\Filesystem;

defined('C5_EXECUTE') or die('Access Denied.');

final class VolatileDirectoryCreator
{
    private Repository $config;

    private FileService $fileService;

    private Filesystem $fs;

    public function __construct(Repository $config, FileService $fileService, Filesystem $fs)
    {
        $this->config = $config;
        $this->fileService = $fileService;
        $this->fs = $fs;
    }

    public function createVolatileDirectory(): VolatileDirectory
    {
        return new VolatileDirectory($this->fs, $this->getParentDirectory());
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function getParentDirectory(): string
    {
        $parentDirectory = $this->checkParentDirectory($this->config->get('community_translation::paths.tempDir'));
        if ($parentDirectory !== '') {
            return $parentDirectory;
        }
        $parentDirectory = $this->checkParentDirectory($this->fileService->getTemporaryDirectory());
        if ($parentDirectory !== '') {
            return $parentDirectory;
        }
        throw new UserMessageException(t('Unable to retrieve the temporary directory.'));
    }

    private function checkParentDirectory($mixed): string
    {
        $path = is_string($mixed) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $mixed), '/') : '';

        return $path !== '' && $this->fs->isDirectory($path) && $this->fs->isWritable($path) ? $path : '';
    }
}
