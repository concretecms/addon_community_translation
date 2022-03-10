<?php

declare(strict_types=1);

namespace CommunityTranslation\Service;

use Concrete\Core\Error\UserMessageException;
use Concrete\Core\File\Service\VolatileDirectory;
use Concrete\Core\File\Service\Zip as ZipService;
use Exception;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

final class DecompressedPackage
{
    private ZipService $zipService;

    private VolatileDirectoryCreator $volatileDirectoryCreator;

    /**
     * The full path of the archive file containing the package.
     */
    private string $packageArchive;

    /**
     * The volatile instance that contains the decompressed contents of the package archive.
     */
    private ?VolatileDirectory $volatileDirectory;

    /**
     * The working directory contaning the extracted package (empty string if not yet extracted).
     */
    private string $extractedWorkDir = '';

    /**
     * @param string $packageArchive the full path of the archive file containing the package
     * @param \Concrete\Core\File\Service\VolatileDirectory|null $volatileDirectory an empty VolatileDirectory instance
     */
    public function __construct(ZipService $zipService, VolatileDirectoryCreator $volatileDirectoryCreator, string $packageArchive, ?VolatileDirectory $volatileDirectory = null)
    {
        $this->zipService = $zipService;
        $this->volatileDirectoryCreator = $volatileDirectoryCreator;
        $this->packageArchive = $packageArchive;
        $this->volatileDirectory = $volatileDirectory;
    }

    /**
     * Get the full path of the archive file containing the package.
     */
    public function getPackageArchive(): string
    {
        return $this->packageArchive;
    }

    /**
     * Extract the archive (if not already done).
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function extract(): void
    {
        if ($this->extractedWorkDir !== '') {
            return;
        }
        if (!is_file($this->packageArchive)) {
            throw new UserMessageException(t('Archive not found: %s', $this->packageArchive));
        }
        $deleteVolatileDirectory = true;
        try {
            $extractedWorkDir = $this->getVolatileDirectory()->getPath();
            try {
                $this->zipService->unzip($this->packageArchive, $extractedWorkDir);
            } catch (Exception $x) {
                throw new UserMessageException($x->getMessage());
            }
            $fs = $this->getVolatileDirectory()->getFilesystem();
            $dirs = array_filter(
                $fs->directories($extractedWorkDir),
                static function (string $dirname): bool {
                    return strpos(basename($dirname), '.') !== 0;
                }
            );
            if (count($dirs) === 1) {
                $someFiles = false;
                foreach ($fs->files($extractedWorkDir) as $fn) {
                    if (strpos(basename($fn), '.') !== 0) {
                        $someFiles = true;
                        break;
                    }
                }
                if ($someFiles === false) {
                    $extractedWorkDir = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $dirs[0]), '/');
                }
            }
            $deleteVolatileDirectory = false;
        } finally {
            if ($deleteVolatileDirectory) {
                try {
                    $this->volatileDirectory = null;
                } catch (Throwable $x) {
                }
            }
        }
        $this->extractedWorkDir = $extractedWorkDir;
    }

    /**
     * Get the working directory contaning the extracted package (we'll extract it if not already done).
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function getExtractedWorkDir(): string
    {
        if ($this->extractedWorkDir === '') {
            $this->extract();
        }

        return $this->extractedWorkDir;
    }

    /**
     * Re-create the source archive with the contents of the extracted directory.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function repack(): void
    {
        $this->extract();
        try {
            $this->zipService->zip($this->getVolatileDirectory()->getPath(), $this->packageArchive, ['includeDotFiles' => true]);
        } catch (Exception $x) {
            throw new UserMessageException($x->getMessage());
        }
    }

    /**
     * Get the volatile instance that contains the decompressed contents of the package archive.
     */
    private function getVolatileDirectory(): VolatileDirectory
    {
        if ($this->volatileDirectory === null) {
            $this->volatileDirectory = $this->volatileDirectoryCreator->createVolatileDirectory();
        }

        return $this->volatileDirectory;
    }
}
