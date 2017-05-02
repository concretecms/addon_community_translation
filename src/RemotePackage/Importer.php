<?php
namespace CommunityTranslation\RemotePackage;

use CommunityTranslation\Entity\RemotePackage as RemotePackageEntity;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Service\VolatileDirectory;
use CommunityTranslation\Translatable\Importer as TranslatableImporter;
use CommunityTranslation\UserException;
use Concrete\Core\Application\Application;
use Concrete\Core\File\Service\Zip as ZipHelper;
use Concrete\Core\Http\Client\Client as HttpClient;
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
     * @throws UserException
     *
     * @param RemotePackageEntity $remotePackage
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
     * @throws UserException
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
        $request->getHeaders()->addHeaderLine('x-concrete5-token', '' . getenv('CONCRETE5_TOKEN'));
        $request->setMethod('GET')->setUri($remotePackage->getArchiveUrl());
        $response = $this->httpClient->send($request);
        $this->httpClient->reset();
        $streamHandle = ($response instanceof \Zend\Http\Response\Stream) ? $response->getStream() : null;
        if ($response->getStatusCode() > 200) {
            $error = t('Failed to download package archive: %s (%d)', $response->getReasonPhrase(), $response->getStatusCode());
            if (is_resource($streamHandle)) {
                fclose($streamHandle);
            }
            unset($temp);
            throw new UserException($error);
        }
        fclose($streamHandle);
        $temp->getFilesystem()->makeDirectory($temp->getPath() . '/unzipped');
        $this->zip->unzip($zipFilename, $temp->getPath() . '/unzipped');
        $temp->getFilesystem()->delete([$zipFilename]);

        return $temp;
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
