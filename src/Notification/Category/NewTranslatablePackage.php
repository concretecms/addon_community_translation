<?php

namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Package as PackageRepository;
use Concrete\Core\User\UserInfo;
use Exception;

/**
 * Notification category: a new translatable package is available.
 */
class NewTranslatablePackage extends Category
{
    /**
     * @var int
     */
    const PRIORITY = 5;

    /**
     * {@inheritdoc}
     *
     * @see Category::getRecipientIDs()
     */
    protected function getRecipientIDs(NotificationEntity $notification)
    {
        $notificationData = $notification->getNotificationData();

        return [
            $notificationData['userID'],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see Category::getMailParameters()
     */
    public function getMailParameters(NotificationEntity $notification, UserInfo $recipient)
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
            foreach ($qb->where($or)->getQuery()->iterate() as $packageRow) {
                $package = $packageRow[0];
                $packageURL = $this->getBlockPageURL('CommunityTranslation Search Packages', 'package/' . $package->getHandle());
                $packages[] = [
                    'url' => $packageURL,
                    'name' => $package->getDisplayName(),
                ];
                $qb->getEntityManager()->detach($package);
            }
        }
        if (count($packages) === 0) {
            throw new Exception(t('Unable to find any package'));
        }

        return [
            'packages' => $packages,
        ] + $this->getCommonMailParameters($notification, $recipient);
    }
}
