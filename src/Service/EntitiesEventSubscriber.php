<?php

declare(strict_types=1);

namespace CommunityTranslation\Service;

use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

defined('C5_EXECUTE') or die('Access Denied.');

class EntitiesEventSubscriber implements EventSubscriber
{
    /**
     * {@inheritdoc}
     *
     * @see \Doctrine\Common\EventSubscriber::getSubscribedEvents()
     */
    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
        ];
    }

    /**
     * Callback method called when a new entity has been saved.
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof PackageVersionEntity) {
            $this->refreshPackageLatestVersion($args->getObjectManager(), $entity->getPackage());
        }
    }

    /**
     * Callback method called when a modified entity is going to be saved.
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof PackageVersionEntity) {
            $em = $args->getObjectManager();
            /** @var \Doctrine\ORM\EntityManager $em */
            $unitOfWork = $em->getUnitOfWork();
            $changeSet = $unitOfWork->getEntityChangeSet($entity);
            if (in_array('version', $changeSet)) {
                $this->refreshPackageLatestVersion($em, $entity->getPackage());
            }
        }
    }

    /**
     * Callback method called when an entity has been deleted.
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof PackageVersionEntity) {
            $this->refreshPackageLatestVersion($args->getObjectManager(), $entity->getPackage());
        }
    }

    /**
     * Set/reset the latest version of a package.
     */
    public function refreshPackageLatestVersion(EntityManager $em, PackageEntity $package): void
    {
        $em->refresh($package);
        $versions = $package->getSortedVersions();
        $latestVersion = array_pop($versions);
        if ($package->getLatestVersion() !== $latestVersion) {
            $package->setLatestVersion($latestVersion);
            $em->flush($package);
        }
    }
}
