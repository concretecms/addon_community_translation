<?php

declare(strict_types=1);

namespace CommunityTranslation\Git;

use CommunityTranslation\Entity\GitRepository as GitRepositoryEntity;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Translatable\Importer as TranslatableImporter;
use Concrete\Core\Application\Application;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

defined('C5_EXECUTE') or die('Access Denied.');

final class Importer
{
    private Application $app;

    private EntityManager $em;

    private LoggerInterface $logger;

    public function __construct(Application $app, EntityManager $em)
    {
        $this->app = $app;
        $this->em = $em;
        $this->logger = new NullLogger();
    }

    /**
     * Set a logger that receive messages.
     *
     * @return $this
     */
    public function setLogger(?LoggerInterface $logger): self
    {
        $this->logger = $logger ?? new NullLogger();

        return $this;
    }

    /**
     * Import the strings from a git repository.
     */
    public function import(GitRepositoryEntity $gitRepository): void
    {
        $importer = $this->app->make(TranslatableImporter::class);
        $fetcher = $this->app->make(Fetcher::class, ['gitRepository' => $gitRepository]);
        $packagesRepo = $this->app->make(PackageRepository::class);
        $package = $packagesRepo->getByHandle($gitRepository->getPackageHandle());
        if ($package === null) {
            $this->logger->notice(t('Creating new package with handle %s', $gitRepository->getPackageHandle()));
            $package = new PackageEntity($gitRepository->getPackageHandle(), $gitRepository->getName());
            $this->em->persist($package);
        }
        $package->setFromGitRepository(true);
        $this->em->flush();
        $this->logger->debug(t('Cloning/fetching repository'));
        $fetcher->update();
        $this->logger->debug(t('Listing tags'));
        $taggedVersions = $fetcher->getTaggedVersions();
        $gitRepository->resetDetectedVersions();
        foreach ($taggedVersions as $tag => $version) {
            if ($gitRepository->getDetectedVersion($version) === null) {
                $gitRepository->addDetectedVersion($version, 'tag', $tag);
            }
        }
        $this->em->persist($gitRepository);
        $this->em->flush($gitRepository);
        foreach ($taggedVersions as $tag => $version) {
            $packageVersion = null;
            foreach ($package->getVersions() as $pv) {
                if ($pv->getVersion() === $version) {
                    $packageVersion = $pv;
                    break;
                }
            }
            if ($packageVersion === null) {
                $this->logger->debug(t('Checking out tag %s', $tag, $version));
                $fetcher->switchToTag($tag);
                $this->logger->info(t('Extracting strings from tag %1$s for version %2$s', $tag, $version));
                if ($importer->importDirectory($fetcher->getRootDirectory(), $package->getHandle(), $version, '')) {
                    $this->logger->debug(t('New translatable strings have been found'));
                } else {
                    $this->logger->debug(t('No new translatable strings have been found'));
                }
            } else {
                $this->logger->debug(t('Tag already imported: %1$s (version: %2$s)', $tag, $version));
            }
        }
        foreach ($gitRepository->getDevBranches() as $branch => $version) {
            if ($gitRepository->getDetectedVersion($version) === null) {
                $gitRepository->addDetectedVersion($version, 'branch', $branch);
                $this->em->persist($gitRepository);
                $this->em->flush($gitRepository);
            }
            $this->logger->debug(t('Checking out development branch %s', $branch));
            $fetcher->switchToBranch($branch);
            $this->logger->info(t('Extracting strings from development branch %1$s for version %2$s', $branch, $version));
            if ($importer->importDirectory($fetcher->getRootDirectory(), $package->getHandle(), $version, '')) {
                $this->logger->debug(t('New translatable strings have been found'));
            } else {
                $this->logger->debug(t('No new translatable strings have been found'));
            }
        }
    }
}
