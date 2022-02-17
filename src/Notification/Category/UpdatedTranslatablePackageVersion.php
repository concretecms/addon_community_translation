<?php

declare(strict_types=1);

namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use Concrete\Core\User\UserInfo;
use Exception;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Notification category: a version of a translatable package has been updated.
 */
class UpdatedTranslatablePackageVersion extends Category
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
        $pvr = $this->app->make(PackageVersionRepository::class);
        $qb = $pvr->createQueryBuilder('pv');
        $or = $qb->expr()->orX();
        foreach ($notificationData['packageVersionIDs'] as $packageVersionID) {
            $or->add('pv.id = ' . (int) $packageVersionID);
        }
        $packageVersions = [];
        if ($or->count() > 0) {
            $em = $qb->getEntityManager();
            foreach ($qb->where($or)->getQuery()->iterate() as $packageVersionRow) {
                $packageVersion = $packageVersionRow[0];
                $packageVersionURL = $this->getBlockPageURL('CommunityTranslation Search Packages', "package/{$packageVersion->getPackage()->getHandle()}/{$packageVersion->getVersion()}");
                $packageVersions[] = [
                    'url' => $packageVersionURL,
                    'name' => $packageVersion->getDisplayName(),
                ];
                $em->detach($packageVersion);
            }
        }
        if ($packageVersions === []) {
            throw new Exception(t('Unable to find any package versions'));
        }

        return [
            'packageVersions' => $packageVersions,
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
