<?php

namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use Concrete\Core\User\UserInfo;
use Exception;

/**
 * Notification category: a version of a translatable package has been updated.
 */
class UpdatedTranslatablePackageVersion extends Category
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
        $pvr = $this->app->make(PackageVersionRepository::class);
        $qb = $pvr->createQueryBuilder('pv');
        $or = $qb->expr()->orX();
        foreach ($notificationData['packageVersionIDs'] as $packageVersionID) {
            $or->add('pv.id = ' . (int) $packageVersionID);
        }
        $packageVersions = [];
        if ($or->count() > 0) {
            foreach ($qb->where($or)->getQuery()->iterate() as $packageVersionRow) {
                $packageVersion = $packageVersionRow[0];
                $packageVersionURL = $this->getBlockPageURL('CommunityTranslation Search Packages', 'package/' . $packageVersion->getPackage()->getHandle() . '/' . $packageVersion->getVersion());
                $packageVersions[] = [
                    'url' => $packageVersionURL,
                    'name' => $packageVersion->getDisplayName(),
                ];
                $qb->getEntityManager()->detach($packageVersion);
            }
        }
        if (count($packageVersions) === 0) {
            throw new Exception(t('Unable to find any package versions'));
        }

        return [
            'packageVersions' => $packageVersions,
        ] + $this->getCommonMailParameters($notification, $recipient);
    }
}
