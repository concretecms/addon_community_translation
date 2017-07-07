<?php

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\RemotePackage as RemotePackageEntity;
use CommunityTranslation\RemotePackage\Importer as RemotePackageImporter;
use CommunityTranslation\Repository\RemotePackage as RemotePackageRepository;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Exception;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class ProcessRemotePackagesCommand extends Command
{
    protected function configure()
    {
        $errExitCode = static::RETURN_CODE_ON_FAILURE;
        $this
            ->setName('ct:remote-packages')
            ->setDescription('Process the  queued remote packages')
            ->addOption('max-failures', 'm', InputOption::VALUE_REQUIRED, 'The maximum number of failures before giving up with processing a package', 3)
            ->addOption('try-unapproved', 't', InputOption::VALUE_REQUIRED, 'Try to process unapproved packages that are not newer than a specified number of days')
            ->setHelp(<<<EOT
Returns codes:
  0 operation completed successfully
  $errExitCode errors occurred
  2 errors occurred but some repository has been processed
EOT
            )
        ;
    }

    protected function executeWithLogger()
    {
        $this->acquireLock();
        $maxFailures = $this->input->getOption('max-failures');
        $maxFailures = is_numeric($maxFailures) ? (int) $maxFailures : 0;
        if ($maxFailures < 1) {
            throw new Exception('Invalid value of max-failures option');
        }
        $tryUnapprovedDateLimit = null;
        $tryUapprovedMaxAge = $this->input->getOption('try-unapproved');
        if ($tryUapprovedMaxAge !== null) {
            $tryUapprovedMaxAge = is_numeric($tryUapprovedMaxAge) ? (int) $tryUapprovedMaxAge : -1;
            if ($tryUapprovedMaxAge < 0) {
                throw new Exception('Invalid value of try-unapproved option');
            }
            $tryUnapprovedDateLimit = new DateTime("-{$tryUapprovedMaxAge} days");
        }
        $em = $this->app->make(EntityManager::class);
        /* @var EntityManager $em */
        $connection = $em->getConnection();
        $repo = $em->getRepository(RemotePackageEntity::class);
        /* @var RemotePackageRepository $repo */
        $importer = $this->app->make(RemotePackageImporter::class);
        /* @var RemotePackageImporter $importer */
        $n = 0;
        $expr = $em->getExpressionBuilder();
        $criteria = new Criteria();
        $criteria
            ->andWhere($criteria->expr()->eq('approved', true))
            ->andWhere($criteria->expr()->isNull('processedOn', true))
            ->andWhere($criteria->expr()->lt('failCount', $maxFailures))
            ->orderBy(['createdOn' => 'ASC', 'id' => 'ASC'])
            ->setMaxResults(1)
        ;
        for (; ;) {
            $remotePackage = $repo->matching($criteria)->first();
            if ($remotePackage === false) {
                break;
            }
            $this->processRemotePackage($connection, $repo, $importer, $remotePackage, true);
            ++$n;
        }
        $this->logger->debug(sprintf('Number of approved packages processed: %d', $n));
        if ($tryUnapprovedDateLimit !== null) {
            $n = 0;
            foreach ($this->getPackageHandlesToTry($repo, $tryUnapprovedDateLimit) as $tryPackageHandle) {
                if ($this->tryProcessRemotePackage($connection, $repo, $importer, $tryPackageHandle) === true) {
                    ++$n;
                }
            }
            $this->logger->debug(sprintf('Number of unapproved packages approved: %d', $n));
        }
        $this->releaseLock();
    }

    /**
     * @param RemotePackageRepository $repo
     * @param DateTime $dateLimit
     *
     * @return string[]|\Generator
     */
    private function getPackageHandlesToTry(RemotePackageRepository $repo, DateTime $dateLimit)
    {
        $qb = $repo->createQueryBuilder('rp');
        $expr = $qb->expr();
        $qb
            ->distinct()
            ->select('rp.handle')
            ->where($expr->isNull('rp.processedOn'))
            ->andWhere($expr->eq('rp.approved', 0))
            ->andWhere($expr->gte('rp.createdOn', ':minCreatedOn'))->setParameter('minCreatedOn', $dateLimit)
        ;
        foreach ($qb->getQuery()->iterate() as $tryPackage) {
            $row = array_pop($tryPackage);
            yield $row['handle'];
        }
    }

    /**
     * @param Connection $connection
     * @param RemotePackageRepository $repo
     * @param RemotePackageImporter $importer
     * @param RemotePackageEntity $remotePackage
     * @param bool $recordFailures
     *
     * @throws Exception
     * @throws Throwable
     */
    private function processRemotePackage(Connection $connection, RemotePackageRepository $repo, RemotePackageImporter $importer, RemotePackageEntity $remotePackage, $recordFailures)
    {
        $this->logger->debug(sprintf('Processing package %s v%s', $remotePackage->getHandle(), $remotePackage->getVersion()));
        $em = $repo->createQueryBuilder('r')->getEntityManager();
        $remotePackage->setProcessedOn(new DateTime());
        $em->persist($remotePackage);
        $em->flush($remotePackage);
        $connection->beginTransaction();
        $error = null;
        try {
            $importer->import($remotePackage);
            $connection->commit();
        } catch (Exception $x) {
            $error = $x;
        } catch (Throwable $x) {
            $error = $x;
        }
        if ($error !== null) {
            try {
                $connection->rollBack();
            } catch (Exception $foo) {
            }
            $remotePackage->setProcessedOn(null);
            if ($recordFailures) {
                $remotePackage
                    ->setFailCount($remotePackage->getFailCount() + 1)
                    ->setLastError($error->getMessage())
                ;
            }
            $em->persist($remotePackage);
            $em->flush($remotePackage);
            throw $error;
        }
    }

    /**
     * @param Connection $connection
     * @param RemotePackageRepository $repo
     * @param RemotePackageImporter $importer
     * @param string $remotePackageHandle
     *
     * @return bool
     */
    private function tryProcessRemotePackage(Connection $connection, RemotePackageRepository $repo, RemotePackageImporter $importer, $remotePackageHandle)
    {
        $result = false;
        $this->logger->debug(sprintf('Trying unapproved package with handle %s', $remotePackageHandle));
        $remotePackage = $repo->findOneBy(
            [
                'handle' => $remotePackageHandle,
                'processedOn' => null,
                'approved' => 0,
            ],
            [
                'createdOn' => 'DESC',
            ]
        );
        if ($remotePackage === null) {
            $this->logger->debug(' - FAILED: entity not found (???)');
        } else {
            $error = null;
            try {
                $this->processRemotePackage($connection, $repo, $importer, $remotePackage, false);
            } catch (Exception $x) {
                $error = $x;
            } catch (Throwable $x) {
                $error = $x;
            }
            if ($error !== null) {
                $this->logger->debug(sprintf(' - FAILED: %s', trim($error->getMessage())));
            } else {
                $this->logger->debug(' - SUCCEEDED: marking the package as approved');
                $qb = $repo->createQueryBuilder('rp');
                $qb
                    ->update()
                    ->set('rp.approved', true)
                    ->where($qb->expr()->eq('rp.handle', ':handle'))->setParameter('handle', $remotePackageHandle)
                    ->getQuery()->execute();
                $result = true;
            }
        }

        return $result;
    }
}
