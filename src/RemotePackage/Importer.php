<?php

declare(strict_types=1);

namespace CommunityTranslation\RemotePackage;

use CommunityTranslation\Entity\GitRepository;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\RemotePackage;
use CommunityTranslation\Entity\RemotePackage as RemotePackageEntity;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Service\VolatileDirectoryCreator;
use CommunityTranslation\Translatable\Importer as TranslatableImporter;
use Concrete\Core\Application\Application;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\File\Service\VolatileDirectory;
use Concrete\Core\File\Service\Zip as ZipService;
use Concrete\Core\Http\Client\Client as HttpClient;
use Doctrine\ORM\EntityManager;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;

defined('C5_EXECUTE') or die('Access Denied.');

final class Importer
{
    private Application $app;

    private HttpClient $httpClient;

    private ZipService $zip;

    private TranslatableImporter $translatableImporter;

    private EntityManager $em;

    private PackageRepository $packageRepo;

    /**
     * Initializes the instance.
     */
    public function __construct(Application $app, HttpClient $httpClient, ZipService $zip, TranslatableImporter $translatableImporter, EntityManager $em)
    {
        $this->app = $app;
        $this->httpClient = $httpClient;
        $this->zip = $zip;
        $this->translatableImporter = $translatableImporter;
        $this->em = $em;
        $this->packageRepo = $em->getRepository(PackageEntity::class);
    }

    /**
     * Import a remote package.
     *
     * @throws \CommunityTranslation\RemotePackage\DownloadException
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function import(RemotePackageEntity $remotePackage): void
    {
        if ($this->em->getRepository(GitRepository::class)->findOneBy(['packageHandle' => $remotePackage->getHandle()]) !== null) {
            throw new UserMessageException(sprintf("It's not possible to download a remote package with handle %s, since there's areaady a package fetched from git with such handle.", $remotePackage->getHandle()));
        }
        $temp = $this->download($remotePackage);
        $rootPath = $this->getRootPath($temp);
        $this->translatableImporter->importDirectory($rootPath, $remotePackage->getHandle(), $remotePackage->getVersion(), '');
        $package = $this->packageRepo->getByHandle($remotePackage->getHandle());
        if ($package === null) {
            return;
        }
        $persist = false;
        if ($remotePackage->getName() !== '' && $remotePackage->getName() !== $package->getName()) {
            $package->setName($remotePackage->getName());
            $persist = true;
        }
        if ($remotePackage->getUrl() !== '' && $remotePackage->getUrl() !== $package->getUrl()) {
            $package->setUrl($remotePackage->getUrl());
            $persist = true;
        }
        if ($persist) {
            $this->em->persist($package);
            $this->em->flush($package);
        }
    }

    /**
     * @throws \CommunityTranslation\RemotePackage\DownloadException
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function download(RemotePackageEntity $remotePackage): VolatileDirectory
    {
        $request = $this->createRequest($remotePackage);
        $response = $this->httpClient->send(
            $request,
            [
                RequestOptions::HTTP_ERRORS => false,
            ]
        );
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = t('Failed to download package archive %s v%s: %s (%d)', $remotePackage->getHandle(), $remotePackage->getVersion(), $response->getReasonPhrase(), $statusCode);
            $headerName = $this->getCustomRequestHeaderName();
            $headerValue = $this->getCustomRequestHeaderValue();
            if ($headerName === '' || $headerValue === '') {
                $message .= "\n(" . t('Not using a custom header') . ')';
            } else {
                $headerDisplayValue = $headerValue[0] . str_repeat('*', strlen($headerValue) - 1);
                if ($headerDisplayValue === $headerValue) {
                    $headerDisplayValue = '*';
                }
                $message .= "\n(" . t('Using a custom header %s (%s)', $headerName, $headerDisplayValue) . ')';
            }
            throw new DownloadException($message, $statusCode);
        }
        if ($response->getBody()->isSeekable()) {
            $response->getBody()->rewind();
        }
        $responseStream = $response->getBody()->detach();
        try {
            $temp = $this->app->make(VolatileDirectoryCreator::class)->createVolatileDirectory();
            $zipFilename = $temp->getPath() . '/downloaded.zip';
            set_error_handler(static function () {}, -1);
            try {
                $zipStream = fopen($zipFilename, 'w');
            } finally {
                restore_exception_handler();
            }
            if ($zipStream === false) {
                throw new UserMessageException('Failed to create a local zip temporary file');
            }
            set_error_handler(static function () {}, -1);
            try {
                $copyResult = stream_copy_to_stream($responseStream, $zipStream);
            } finally {
                fclose($zipStream);
                set_error_handler(static function () {}, -1);
            }
        } finally {
            fclose($responseStream);
        }
        if ($copyResult === 0) {
            throw new UserMessageException('Failed to save the response a local zip temporary file (the response body is empty)');
        }
        if ($copyResult === false) {
            throw new UserMessageException('Failed to save the response a local zip temporary file');
        }
        $temp->getFilesystem()->makeDirectory($temp->getPath() . '/unzipped');
        $this->zip->unzip($zipFilename, $temp->getPath() . '/unzipped');
        $temp->getFilesystem()->delete([$zipFilename]);

        return $temp;
    }

    private function getRootPath(VolatileDirectory $temp): string
    {
        $fs = $temp->getFilesystem();
        $result = $temp->getPath() . '/unzipped';
        for (;;) {
            if ($fs->files($result) !== []) {
                break;
            }
            $dirs = $fs->directories($result);
            if (count($dirs) !== 1) {
                break;
            }
            $result = $dirs[0];
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $result);
    }

    private function createRequest(RemotePackage $remotePackage): RequestInterface
    {
        $headers = [];
        $headerName = $this->getCustomRequestHeaderName();
        if ($headerName !== '') {
            $headerValue = $this->getCustomRequestHeaderValue();
            if ($headerValue !== '') {
                $headers[$headerName] = $headerValue;
            }
        }

        return new Request('GET', $remotePackage->getArchiveUrl(), $headers);
    }

    private function getCustomRequestHeaderName(): string
    {
        $value = $_ENV['CT_REMOTEPACKAGE_HEADER'] ?? null;

        return is_string($value) ? $value : '';
    }

    private function getCustomRequestHeaderValue(): string
    {
        $value = $_ENV['CT_REMOTEPACKAGE_HEADER_VALUE'] ?? null;

        return is_string($value) ? $value : '';
    }
}
