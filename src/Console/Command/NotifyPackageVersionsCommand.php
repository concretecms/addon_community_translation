<?php
namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\PackageSubscription as PackageSubscriptionEntity;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Concrete\Core\User\UserList;
use DateTime;
use Doctrine\ORM\EntityManager;
use Exception;

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
     * PackageRepository.
     */
    private $packageRepository;

    /**
     * NotificationRepository.
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
        $this->notifyNewPackageVersions();
        $this->notifyUpdatedPackageVersions();
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

    private function notifyNewPackageVersions()
    {
        throw new Exception('@todo');
    }

    private function notifyUpdatedPackageVersions()
    {
        throw new Exception('@todo');
    }
}
