<?php

declare(strict_types=1);

namespace CommunityTranslation\Service;

use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use RuntimeException;

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
            Events::prePersist,
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
        ];
    }

    /**
     * Callback method called when a new entity is going to be persisted.
     */
    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof PackageEntity) {
            $alias = $args->getObjectManager()->getRepository(PackageEntity\Alias::class)->findOneBy(['handle' => $entity->getHandle()]);
            if ($alias !== null) {
                throw new RuntimeException(t(
                    "You can't create a package with handle '%1\$s', since it's an alias of the package with handle '%22\$s'",
                    $entity->getHandle(),
                    $alias->getPackage()->getHandle(),
                ));
            }
        } elseif ($entity instanceof PackageEntity\Alias) {
            $package = $args->getObjectManager()->getRepository(PackageEntity::class)->findOneBy(['handle' => $entity->getHandle()]);
            if ($package !== null) {
                throw new RuntimeException(t(
                    "You can't create an alias for the package with handle '%s', since there's already a package with this handle",
                    $entity->getHandle(),
                ));
            }
        }
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
