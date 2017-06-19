<?php

namespace CommunityTranslation\Service;

use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;

class EntitiesEventSubscriber implements EventSubscriber
{
    /**
     * {@inheritdoc}
     *
     * @see EventSubscriber::getSubscribedEvents()
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
     *
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        if ($entity instanceof PackageVersionEntity) {
            $this->refreshPackageLatestVersion($args->getObjectManager(), $entity->getPackage());
        }
    }

    /**
     * Callback method called when a modified entity is going to be saved.
     *
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        if ($entity instanceof PackageVersionEntity) {
            $em = $args->getObjectManager();
            /* @var \Doctrine\ORM\EntityManager $em */
            $unitOfWork = $em->getUnitOfWork();
            $changeSet = $unitOfWork->getEntityChangeSet($entity);
            if (in_array('version', $changeSet)) {
                $this->refreshPackageLatestVersion($em, $entity->getPackage());
            }
        }
    }

    /**
     * Callback method called when an entity has been deleted.
     *
     * @param LifecycleEventArgs $args
     */
    public function postRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();
        if ($entity instanceof PackageVersionEntity) {
            $this->refreshPackageLatestVersion($args->getObjectManager(), $entity->getPackage());
        }
    }

    /**
     * Set/reset the latest version of a package.
     *
     * @param EntityManager $em
     * @param PackageEntity $package
     */
    public function refreshPackageLatestVersion(EntityManager $em, PackageEntity $package)
    {
        $versions = $package->getSortedVersions();
        $latestVersion = empty($versions) ? null : array_pop($versions);
        if ($package->getLatestVersion() !== $latestVersion) {
            $package->setLatestVersion($latestVersion);
            $em->persist($package);
            $em->flush($package);
        }
    }
}
