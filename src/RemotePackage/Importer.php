<?php

namespace CommunityTranslation\RemotePackage;

use CommunityTranslation\Entity\RemotePackage as RemotePackageEntity;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Service\VolatileDirectory;
use CommunityTranslation\Translatable\Importer as TranslatableImporter;
use Concrete\Core\Application\Application;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\File\Service\Zip as ZipHelper;
use Concrete\Core\Http\Client\Client as HttpClient;
use Exception;
use Zend\Http\Request;

class Importer
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var ZipHelper
     */
    protected $zip;

    /**
     * @var TranslatableImporter
     */
    protected $translatableImporter;

    /**
     * @var PackageRepository
     */
    protected $packageRepo;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * Initializes the instance.
     *
     * @param Application $app
     * @param HttpClient $httpClient
     * @param ZipHelper $zip
     */
    public function __construct(Application $app, HttpClient $httpClient, ZipHelper $zip, TranslatableImporter $translatableImporter, PackageRepository $packageRepo)
    {
        $this->app = $app;
        $this->httpClient = $httpClient;
        $this->zip = $zip;
        $this->translatableImporter = $translatableImporter;
        $this->packageRepo = $packageRepo;
        $this->em = $this->packageRepo->createQueryBuilder('p')->getEntityManager();
    }

    /**
     * Import a remote package.
     *
     *
     * @param RemotePackageEntity $remotePackage
     *
     * @throws UserMessageException
     */
    public function import(RemotePackageEntity $remotePackage)
    {
        $temp = $this->download($remotePackage);
        $rootPath = $this->getRootPath($temp);
        $this->translatableImporter->importDirectory($rootPath, $remotePackage->getHandle(), $remotePackage->getVersion(), '');
        $package = $this->packageRepo->findOneBy(['handle' => $remotePackage->getHandle()]);
        if ($package !== null) {
            /* @var \CommunityTranslation\Entity\Package $package */
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
    }

    /**
     * @param RemotePackageEntity $remotePackage
     *
     * @throws UserMessageException
     *
     * @return VolatileDirectory
     */
    private function download(RemotePackageEntity $remotePackage)
    {
        $temp = $this->app->make(VolatileDirectory::class);
        /* @var VolatileDirectory $temp */
        $zipFilename = $temp->getPath() . '/downloaded.zip';
        $this->httpClient->reset();
        $this->httpClient->setOptions([
            'storeresponse' => false,
            'outputstream' => $zipFilename,
        ]);
        $request = new Request();

        if (($header = (string) getenv('CT_REMOTEPACKAGE_HEADER')) &&
            $value = (string) getenv('CT_REMOTEPACKAGE_HEADER_VALUE')) {
            $request->getHeaders()->addHeaderLine($header, $value);
        }
        $request->setMethod('GET')->setUri($remotePackage->getArchiveUrl());
        $response = $this->httpClient->send($request);
        $this->httpClient->reset();
        $streamHandle = ($response instanceof \Zend\Http\Response\Stream) ? $response->getStream() : null;
        if ($response->getStatusCode() > 200) {
            $error = t('Failed to download package archive $s v%s: %s (%d)', $remotePackage->getHandle(), $remotePackage->getVersion(), $response->getReasonPhrase(), $response->getStatusCode());
            if (is_resource($streamHandle)) {
                fclose($streamHandle);
            }
            unset($temp);
            throw new DownloadException($error, $response->getStatusCode());
        }
        fclose($streamHandle);

        $contentEncodingHeader = $response->getHeaders()->get('Content-Encoding');
        if (!empty($contentEncodingHeader)) {
            $contentEncoding = trim((string) $contentEncodingHeader->getFieldValue());
            if ($contentEncoding !== '') {
                $decodedZipFilename = $temp->getPath() . '/downloaded-decoded.zip';
                switch (strtolower($contentEncoding)) {
                    case 'gzip':
                        $this->decodeGzip($zipFilename, $decodedZipFilename);
                        break;
                    case 'deflate':
                        $this->decodeDeflate($zipFilename, $decodedZipFilename);
                        break;
                    case 'plainbinary':
                    default:
                        $decodedZipFilename = '';
                        break;
                }
                if ($decodedZipFilename !== '') {
                    $temp->getFilesystem()->delete([$zipFilename]);
                    $zipFilename = $decodedZipFilename;
                }
            }
        }

        $temp->getFilesystem()->makeDirectory($temp->getPath() . '/unzipped');
        $this->zip->unzip($zipFilename, $temp->getPath() . '/unzipped');
        $temp->getFilesystem()->delete([$zipFilename]);

        return $temp;
    }

    /**
     * @param string $fromFilename
     * @param string $toFilename
     *
     * @throws UserMessageException
     */
    private function decodeGzip($fromFilename, $toFilename)
    {
        if (!function_exists('gzopen')) {
            throw new UserMessageException(t(/*i18n: %s is a compression method, like gzip*/'The PHP zlib extension is required in order to decode "%s" encodings.', 'gzip'));
        }
        try {
            $hFrom = @gzopen($fromFilename, 'rb');
            if ($hFrom === false) {
                throw new UserMessageException(t('Failed to open the file to be decoded with gzip.'));
            }
            $hTo = @fopen($toFilename, 'wb');
            if ($hTo === false) {
                throw new UserMessageException(t('Failed to create the file that contain gzip-decoded data.'));
            }
            while (!gzeof($hFrom)) {
                $data = @gzread($hFrom, 32768);
                if (!is_string($data) || $data === '') {
                    throw new UserMessageException(t('Failed to decode the gzip data.'));
                }
                if (@fwrite($hTo, $data) === false) {
                    throw new UserMessageException(t('Failed to write decoded gzip data.'));
                }
            }
        } catch (Exception $x) {
            if (isset($hTo) && is_resource($hTo)) {
                @fclose($hTo);
            }
            if (isset($hFrom) && is_resource($hFrom)) {
                @gzclose($hFrom);
            }
            throw $x;
        }
        fclose($hTo);
        gzclose($hFrom);
    }

    /**
     * @param string $fromFilename
     * @param string $toFilename
     *
     * @throws UserMessageException
     */
    private function decodeDeflate($fromFilename, $toFilename)
    {
        if (!function_exists('gzuncompress')) {
            throw new UserMessageException(t(/*i18n: %s is a compression method, like gzip*/'The PHP zlib extension is required in order to decode "%s" encodings.', 'deflate'));
        }
        $compressed = @file_get_contents($fromFilename);
        if ($compressed === false) {
            throw new UserMessageException(t('Failed to read the file to be decoded with inflate.'));
        }
        $isGZip = false;
        if (isset($compressed[1])) {
            $zlibHeader = unpack('n', substr($compressed, 0, 2));
            if ($zlibHeader[1] % 31 === 0) {
                $isGZip = true;
            }
        }
        if ($isGZip) {
            $decompressed = @gzuncompress($compressed);
            if ($decompressed === false) {
                throw new UserMessageException(t('Failed to decode the ZLIB compressed data.'));
            }
        } else {
            $decompressed = @gzinflate($compressed);
            if ($decompressed === false) {
                throw new UserMessageException(t('Failed to inflate the deflated data.'));
            }
        }
        if (@file_put_contents($toFilename, $decompressed) === false) {
            throw new UserMessageException(t('Failed to decompress  the file to be decoded with inflate.'));
        }
        throw new UserMessageException(t('Failed to write the deflated data.'));
    }

    /**
     * @param VolatileDirectory $temp
     *
     * @return string
     */
    private function getRootPath(VolatileDirectory $temp)
    {
        $fs = $temp->getFilesystem();
        $result = $temp->getPath() . '/unzipped';
        for (; ;) {
            if (count($fs->files($result)) !== 0) {
                break;
            }
            $dirs = $fs->directories($result);
            if (count($dirs) !== 1) {
                break;
            }
            $result = $dirs[0];
        }

        return $result;
    }
}
