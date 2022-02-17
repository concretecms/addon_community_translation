<?php

declare(strict_types=1);

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\PackageSubscription as PackageSubscriptionEntity;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Concrete\Core\User\UserList;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Generator;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class NotifyPackageVersionsCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
ct:notify-packages
    {--a|max-age=3 : The maximum age (in days) of the packages and package versions }

EOT
    ;

    private EntityManager $em;

    private DateTimeImmutable $timeLimit;

    private UserInfoRepository $userInfoRepository;

    private PackageRepository $packageRepository;

    private NotificationRepository $notificationRepository;

    public function handle(EntityManager $em, UserInfoRepository $userInfoRepository): int
    {
        $this->createLogger();
        $mutexReleaser = null;
        try {
            $mutexReleaser = $this->acquireMutex();
            $this->em = $em;
            $this->userInfoRepository = $userInfoRepository;
            $this->packageRepository = $em->getRepository(PackageEntity::class);
            $this->notificationRepository = $em->getRepository(NotificationEntity::class);
            $this->readParameters();
            $this->em->transactional(function () {
                $start = time();
                $newPackagesCount = $this->notifyNewPackages();
                $end = time();
                $elapsed = $end - $start;
                $start = time();
                $this->logger->info("{$newPackagesCount} notifications created for new packages (time required: {$elapsed} seconds}");
                [$newVersionsCount, $updatedVersionsCount] = $this->notifyPackageVersions();
                $elapsed = $end - $start;
                $this->logger->info("{$newVersionsCount} notifications created for new package versions, {$updatedVersionsCount} notifications created for updated package versions (time required: {$elapsed} seconds}");
            });

            return static::SUCCESS;
        } catch (Throwable $x) {
            $this->logger->error($this->formatThrowable($x));

            return static::FAILURE;
        } finally {
            if ($mutexReleaser !== null) {
                try {
                    $mutexReleaser();
                } catch (Throwable $x) {
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::configureUsingFluentDefinition()
     */
    protected function configureUsingFluentDefinition()
    {
        parent::configureUsingFluentDefinition();
        $this
            ->setDescription('Prepare notifications about new packages, new package versions and updated packages')
            ->setHelp(
                <<<'EOT'
This command prepare notifications about new packages, new package versions and package versions with updated strings.

Returns codes:
  0 operation completed successfully
  1 errors occurred
EOT
            )
        ;
    }

    private function readParameters(): void
    {
        $maxAge = (string) $this->input->getOption('max-age');
        $maxAge = preg_match('/^\d+$/', $maxAge) ? (int) $maxAge : -1;
        if ($maxAge <= 0) {
            throw new UserMessageException('Invalid value of the max-age parameter (it must be an integer greater than 0)');
        }
        $this->timeLimit = new DateTimeImmutable("-{$maxAge} days");
    }

    private function notifyNewPackages(): int
    {
        $qb = $this->packageRepository->createQueryBuilder('p');
        $qb
            ->leftJoin(PackageSubscriptionEntity::class, 's', 'WITH', 'p.id = s.package AND :user = s.user')
            ->andWhere($qb->expr()->isNull('s.user'))
            ->andWhere('p.createdOn >= :timeLimit')
            ->setParameter('timeLimit', $this->timeLimit->format($this->em->getConnection()->getDatabasePlatform()->getDateTimeFormatString()))
        ;
        $q = $qb->getQuery();
        $result = 0;
        foreach ($this->getUsersForNewPackages() as $userInfo) {
            foreach ($this->getNewPackagesForUser($q, $userInfo) as $package) {
                $this->notifyNewPackageTo($userInfo, $package);
                $result++;
            }
        }

        return $result;
    }

    /**
     * @return \Concrete\Core\User\UserInfo[]
     */
    private function getUsersForNewPackages(): Generator
    {
        $userList = new UserList();
        $userList->filterByAttribute('notify_new_packages', 1);
        foreach ($userList->getResultIDs() as $uID) {
            $userInfo = $this->userInfoRepository->getByID($uID);
            if ($userInfo !== null) {
                yield $userInfo;
                $this->em->detach($userInfo);
            }
        }
    }

    /**
     * @return \CommunityTranslation\Entity\Package[]
     */
    private function getNewPackagesForUser(Query $q, UserInfo $userInfo): Generator
    {
        $iterable = $q->setParameter('user', $userInfo->getEntityObject())->toIterable();
        foreach ($iterable as $package) {
            yield $package;
            $this->em->detach($package);
        }
    }

    private function notifyNewPackageTo(UserInfo $userInfo, PackageEntity $package): void
    {
        $pse = new PackageSubscriptionEntity($userInfo->getEntityObject(), $package, false);
        $this->em->persist($pse);
        $this->em->flush($pse);
        $this->em->detach($pse);
        $this->notificationRepository->newTranslatablePackage($package->getID(), (int) $userInfo->getUserID());
    }

    private function notifyPackageVersions(): array
    {
        $result = [
            0,
            0,
        ];
        foreach ($this->getNotifyPackageVersionsData() as [$user, $newPackageVersion, $updatedPackageVersion]) {
            if ($newPackageVersion !== null) {
                $this->notifyNewPackageVersionTo($user, $newPackageVersion);
                $result[0]++;
            }
            if ($updatedPackageVersion !== null) {
                $this->notifyUpdatedPackageVersionTo($user, $updatedPackageVersion);
                $result[1]++;
            }
        }

        return $result;
    }

    /**
     * @return array[]
     */
    private function getNotifyPackageVersionsData(): Generator
    {
        $packageSubscriptionRepository = $this->em->getRepository(PackageSubscriptionEntity::class);
        $packageVersionRepository = $this->em->getRepository(PackageVersionEntity::class);
        $packageSubscription = null;
        /**
         * @var \CommunityTranslation\Entity\PackageSubscription|null $packageSubscription
         */
        $lastNewPackageVersionNotified = null;
        /**
         * @var \CommunityTranslation\Entity\Package\Version|null $lastUpdatedPackageVersionNotified
         */
        $lastUpdatedPackageVersionNotified = null;
        $cn = $this->em->getConnection();
        $rs = $cn->executeQuery(
            <<<'EOT'
SELECT DISTINCT
    ps.user,
    ps.package,
    pv1.id AS notifyNewPackageVersion,
    pvs.packageVersion AS notifyUpdatedPackageVersion
FROM
    CommunityTranslationPackageSubscriptions AS ps
-- new package versions
    LEFT JOIN
        CommunityTranslationPackageVersions AS pv1
            ON
                ps.package = pv1.package
                AND ps.notifyNewVersions = 1
                AND :timeLimit <= pv1.createdOn
                AND ps.sendNotificationsAfter < pv1.createdOn
-- updated package versions
    LEFT JOIN
        CommunityTranslationPackageVersions AS pv2
            ON
                ps.package = pv2.package
    LEFT JOIN
        CommunityTranslationPackageVersionSubscriptions AS pvs
            ON
                ps.user = pvs.user
                AND pv2.id = pvs.packageVersion
                AND 1 = pvs.notifyUpdates
                AND :timeLimit <= pv2.updatedOn
                AND ps.sendNotificationsAfter < pv2.updatedOn
WHERE
    pv1.package IS NOT NULL
    OR pvs.packageVersion IS NOT NULL
ORDER BY
    ps.user,
    ps.package,
    notifyNewPackageVersion,
    notifyUpdatedPackageVersion
EOT
            ,
            [
                'timeLimit' => $this->timeLimit->format($cn->getDatabasePlatform()->getDateTimeFormatString()),
            ]
        );
        while (($row = $rs->fetchAssociative()) !== false) {
            if ($packageSubscription === null || $packageSubscription->getUser()->getUserID() != $row['user'] || $packageSubscription->getPackage()->getID() != $row['package']) {
                $this->em->clear(PackageSubscriptionEntity::class);
                $packageSubscription = $packageSubscriptionRepository->find(['user' => $row['user'], 'package' => $row['package']]);
                $packageSubscription->setSendNotificationsAfter(new DateTimeImmutable());
                $this->em->persist($packageSubscription);
                $this->em->flush($packageSubscription);
                $lastNewPackageVersionNotified = null;
                $lastUpdatedPackageVersionNotified = null;
            }
            if ($row['notifyNewPackageVersion'] && ($lastNewPackageVersionNotified === null || $lastNewPackageVersionNotified->getID() != $row['notifyNewPackageVersion'])) {
                $newPackageVersion = $packageVersionRepository->find($row['notifyNewPackageVersion']);
                $lastNewPackageVersionNotified = $newPackageVersion;
            } else {
                $newPackageVersion = null;
            }
            if ($row['notifyUpdatedPackageVersion'] && ($lastUpdatedPackageVersionNotified === null || $lastUpdatedPackageVersionNotified->getID() != $row['notifyUpdatedPackageVersion'])) {
                $updatedPackageVersion = $packageVersionRepository->find($row['notifyUpdatedPackageVersion']);
                $lastUpdatedPackageVersionNotified = $newPackageVersion;
            } else {
                $updatedPackageVersion = null;
            }
            yield [$packageSubscription->getUser(), $newPackageVersion, $updatedPackageVersion];
        }
    }

    private function notifyNewPackageVersionTo(UserEntity $user, PackageVersionEntity $packageVersion): void
    {
        $this->notificationRepository->newTranslatablePackageVersion($packageVersion->getID(), (int) $user->getUserID());
    }

    private function notifyUpdatedPackageVersionTo(UserEntity $user, PackageVersionEntity $packageVersion): void
    {
        $this->notificationRepository->updatedTranslatablePackageVersion($packageVersion->getID(), (int) $user->getUserID());
    }
}
