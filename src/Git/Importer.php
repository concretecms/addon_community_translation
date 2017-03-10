<?php
namespace CommunityTranslation\Git;

use CommunityTranslation\Entity\GitRepository as GitRepositoryEntity;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Translatable\Importer as TranslatableImporter;
use Concrete\Core\Application\Application;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;

class Importer
{
    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * The entity manager instance.
     *
     * @var EntityManager
     */
    protected $em;

    /**
     * A logger that receive messages.
     *
     * @var LoggerInterface|null
     */
    protected $logger = null;

    /**
     * @param Application $application
     */
    public function __construct(Application $app, EntityManager $em)
    {
        $this->app = $app;
        $this->em = $em;
    }

    /**
     * Set a logger that receive messages.
     *
     * @param LoggerInterface|null $logger
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Import the strings from a git repository.
     */
    public function import(GitRepositoryEntity $gitRepository)
    {
        $importer = $this->app->make(TranslatableImporter::class);
        $fetcher = $this->app->make(Fetcher::class, ['gitRepository' => $gitRepository]);
        $packagesRepo = $this->app->make(PackageRepository::class);
        $package = $packagesRepo->findOneBy(['handle' => $gitRepository->getPackageHandle()]);
        if ($package === null) {
            if ($this->logger !== null) {
                $this->logger->notice(t('Creating new package with handle %s', $gitRepository->getPackageHandle()));
            }
            $package = PackageEntity::create($gitRepository->getPackageHandle());
            $this->em->persist($package);
            $this->em->flush($package);
        }
        if ($this->logger !== null) {
            $this->logger->debug(t('Cloning/fetching repository'));
        }
        $fetcher->update();
        if ($this->logger !== null) {
            $this->logger->debug(t('Listing tags'));
        }
        $taggedVersions = $fetcher->getTaggedVersions();
        $skippedTags = [];
        foreach ($taggedVersions as $tag => $version) {
            if ($gitRepository->getDetectedVersion($version) === null) {
                $gitRepository->addDetectedVersion($version, 'tag', $tag);
                $this->em->persist($gitRepository);
                $this->em->flush($gitRepository);
            }
            $packageVersion = null;
            foreach ($package->getVersions() as $pv) {
                if ($pv->getVersion() === $version) {
                    $packageVersion = $pv;
                    break;
                }
            }
            if ($packageVersion === null) {
                if ($this->logger !== null) {
                    $this->logger->debug(t('Checking out tag %s', $tag, $version));
                }
                $fetcher->switchToTag($tag);
                if ($this->logger !== null) {
                    $this->logger->info(t('Extracting strings from tag %1$s for version %2$s', $tag, $version));
                }
                $importer->importDirectory($fetcher->getRootDirectory(), $package->getHandle(), $version, '');
            } else {
                if ($this->logger !== null) {
                    $this->logger->debug(t('Tag already imported: %1$s (version: %2$s)', $tag, $version));
                }
            }
        }
        foreach ($gitRepository->getDevBranches() as $branch => $version) {
            if ($gitRepository->getDetectedVersion($version) === null) {
                $gitRepository->addDetectedVersion($version, 'branch', $branch);
                $this->em->persist($gitRepository);
                $this->em->flush($gitRepository);
            }
            if ($this->logger !== null) {
                $this->logger->debug(t('Checking out development branch %s', $branch));
            }
            $fetcher->switchToBranch($branch);
            if ($this->logger !== null) {
                $this->logger->info(t('Extracting strings from development branch %1$s for version %2$s', $branch, $version));
            }
            $importer->importDirectory($fetcher->getRootDirectory(), $package->getHandle(), $version, '');
        }
    }
}
