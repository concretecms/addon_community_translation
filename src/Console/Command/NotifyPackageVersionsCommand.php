<?php

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\PackageSubscription as PackageSubscriptionEntity;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Repository\PackageSubscription as PackageSubscriptionRepository;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Concrete\Core\User\UserList;
use DateTime;
use Doctrine\ORM\EntityManager;
use Exception;
use Symfony\Component\Console\Input\InputOption;

class NotifyPackageVersionsCommand extends Command
{
    const RETURN_CODE_ON_FAILURE = 3;

    /**
     * @var DateTime|null
     */
    private $timeLimit;

    /**
     * @var EntityManager|null
     */
    private $em;

    /**
     * @var \Concrete\Core\Database\Connection\Connection|null
     */
    private $connection;

    /**
     * @var UserInfoRepository
     */
    private $userInfoRepository;

    /**
     * @var PackageRepository
     */
    private $packageRepository;

    /**
     * @var NotificationRepository
     */
    private $notificationRepository;

    /**
     * The default maximum age (in days) of the new packages/package versions that may raise notifications.
     *
     * @var int
     */
    const DEFAULT_MAX_AGE = 3;

    protected function configure()
    {
        $errExitCode = static::RETURN_CODE_ON_FAILURE;
        $this
            ->setName('ct:notify-packages')
            ->setDescription('Prepare notifications about new packages, new package versions and updated packages')
            ->addOption('max-age', 'a', InputOption::VALUE_REQUIRED, 'The maximum age (in days) of the packages and package versions', static::DEFAULT_MAX_AGE)
            ->setHelp(<<<EOT
This command prepare notifications about new packages, new package versions and package versions with updated strings.

Returns codes:
  0 operation completed successfully
  $errExitCode errors occurred
EOT
            )
        ;
    }

    protected function executeWithLogger()
    {
        $this->readParameters();
        if ($this->acquireLock(0) === false) {
            throw new Exception('Failed to acquire lock');
        }
        $this->initializeProcessing();
        $this->notifyNewPackages();
        $this->notifyPackageVersions();
        $this->finishProcessing();
        $this->releaseLock();
    }

    private function readParameters()
    {
        $maxAge = (int) $this->input->getOption('max-age');
        if ($maxAge <= 0) {
            throw new Exception('Invalid value of the max-age parameter (it must be an integer greater than 0)');
        }
        $this->timeLimit = new DateTime("-$maxAge days");
    }

    private function initializeProcessing()
    {
        $this->em = $this->app->make(EntityManager::class);
        $this->connection = $this->em->getConnection();
        $this->connection->beginTransaction();
        $this->userInfoRepository = $this->app->make(UserInfoRepository::class);
        $this->packageRepository = $this->app->make(PackageRepository::class);
        $this->notificationRepository = $this->app->make(NotificationRepository::class);
    }

    private function finishProcessing()
    {
        $this->connection->commit();
        $this->connection = null;
    }

    private function notifyNewPackages()
    {
        foreach ($this->getUsersForNewPackages() as $userInfo) {
            foreach ($this->getNewPackagesForUser($userInfo) as $package) {
                $this->notifyNewPackageTo($userInfo, $package);
            }
        }
    }

    /**
     * @return UserInfo[]|Generator
     */
    private function getUsersForNewPackages()
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
     * @return UserInfo[]|Generator
     */
    private function getNewPackagesForUser(UserInfo $userInfo)
    {
        $qb = $this->packageRepository->createQueryBuilder('p');
        $qb
            ->leftJoin(PackageSubscriptionEntity::class, 's', 'WITH', 'p.id = s.package AND :user = s.user')->setParameter('user', $userInfo->getEntityObject())
            ->where($qb->expr()->isNull('s.user'))
            ->andWhere('p.createdOn >= :timeLimit')->setParameter('timeLimit', $this->timeLimit);
        $q = $qb->getQuery();
        foreach ($q->iterate() as $packageRows) {
            yield $packageRows[0];
            $this->em->detach($packageRows[0]);
        }
    }

    private function notifyNewPackageTo(UserInfo $userInfo, PackageEntity $package)
    {
        $pse = PackageSubscriptionEntity::create($userInfo->getEntityObject(), $package, false);
        $this->em->persist($pse);
        $this->em->flush($pse);
        $this->em->detach($pse);
        $this->notificationRepository->newTranslatablePackage($package->getID(), $userInfo->getUserID());
    }

    private function notifyPackageVersions()
    {
        foreach ($this->getNotifyPackageVersionsData() as $data) {
            list($user, $newPackageVersion, $updatedPackageVersion) = $data;
            if ($newPackageVersion !== null) {
                $this->notifyNewPackageVersionTo($user, $newPackageVersion);
            }
            if ($updatedPackageVersion !== null) {
                $this->notifyUpdatedPackageVersionTo($user, $updatedPackageVersion);
            }
        }
    }

    /**
     * @return array[]|Generator
     */
    private function getNotifyPackageVersionsData()
    {
        $dateHelper = $this->app->make('date');
        /* @var \Concrete\Core\Localization\Service\Date $dateHelper */
        $timeLimitSQL = $dateHelper->toDB($this->timeLimit);
        $packageSubscriptionRepository = $this->app->make(PackageSubscriptionRepository::class);
        /* @var PackageSubscriptionRepository $packageSubscriptionRepository */
        $packageVersionRepository = $this->app->make(PackageVersionRepository::class);
        /* @var PackageVersionRepository $packageVersionRepository */
        $packageSubscription = null;
        $lastNewPackageVersionNotified = null;
        $lastUpdatedPackageVersionNotified = null;
        $rs = $this->em->getConnection()->executeQuery('
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
		AND ? <= pv1.createdOn
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
		AND ? <= pv2.updatedOn
		AND ps.sendNotificationsAfter < pv2.updatedOn
WHERE
	pv1.package IS NOT NULL
	or pvs.packageVersion IS NOT NULL
ORDER BY
	ps.user,
	ps.package,
	notifyNewPackageVersion,
	notifyUpdatedPackageVersion
', [$timeLimitSQL, $timeLimitSQL]);
        /* @var \Concrete\Core\Database\Driver\PDOStatement $rs */
        while (($row = $rs->fetch()) !== false) {
            /* @var PackageSubscriptionEntity $packageSubscription */
            if ($packageSubscription === null || $packageSubscription->getUser()->getUserID() != $row['user'] || $packageSubscription->getPackage()->getID() != $row['package']) {
                $this->em->clear(PackageSubscriptionEntity::class);
                $packageSubscription = $packageSubscriptionRepository->find(['user' => $row['user'], 'package' => $row['package']]);
                $packageSubscription->setSendNotificationsAfter(new DateTime());
                $this->em->persist($packageSubscription);
                $this->em->flush($packageSubscription);
                $lastNewPackageVersionNotified = null;
                $lastUpdatedPackageVersionNotified = null;
            }
            /* @var PackageVersionEntity $lastNewPackageVersionNotified */
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
        $rs->closeCursor();
    }

    private function notifyNewPackageVersionTo(UserEntity $user, PackageVersionEntity $packageVersion)
    {
        $this->notificationRepository->newTranslatablePackageVersion($packageVersion->getID(), $user->getUserID());
    }

    private function notifyUpdatedPackageVersionTo(UserEntity $user, PackageVersionEntity $packageVersion)
    {
        $this->notificationRepository->updatedTranslatablePackageVersion($packageVersion->getID(), $user->getUserID());
    }
}
