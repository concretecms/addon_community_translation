<?php

declare(strict_types=1);

namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Package as PackageRepository;
use Concrete\Core\User\UserInfo;
use Exception;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Notification category: a new translatable package is available.
 */
class NewTranslatablePackage extends Category
{
    /**
     * @var int
     */
    public const PRIORITY = 5;

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Notification\CategoryInterface::getMailParameters()
     */
    public function getMailParameters(NotificationEntity $notification, UserInfo $recipient): array
    {
        $notificationData = $notification->getNotificationData();
        $pr = $this->app->make(PackageRepository::class);
        $qb = $pr->createQueryBuilder('p');
        $or = $qb->expr()->orX();
        foreach ($notificationData['packageIDs'] as $packageID) {
            $or->add('p.id = ' . (int) $packageID);
        }
        $packages = [];
        if ($or->count() > 0) {
            $em = $qb->getEntityManager();
            foreach ($qb->where($or)->getQuery()->toIterable() as $package) {
                /** @var \CommunityTranslation\Entity\Package $package */
                $packageURL = $this->getBlockPageURL('CommunityTranslation Search Packages', "package/{$package->getHandle()}");
                $packages[] = [
                    'url' => $packageURL,
                    'name' => $package->getDisplayName(),
                ];
                $em->detach($package);
            }
        }
        if ($packages === []) {
            throw new Exception(t('Unable to find any package'));
        }

        return [
            'packages' => $packages,
        ] + $this->getCommonMailParameters($notification, $recipient);
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Notification\Category::getRecipientIDs()
     */
    protected function getRecipientIDs(NotificationEntity $notification): array
    {
        $notificationData = $notification->getNotificationData();

        return [
            $notificationData['userID'],
        ];
    }
}
