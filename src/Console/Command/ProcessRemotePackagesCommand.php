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
            if ($remotePackage === null) {
                break;
            }
            $this->processRemotePackage($connection, $repo, $importer, $remotePackage);
            ++$n;
        }
        $this->releaseLock();
        $this->logger->debug(sprintf('Number of packages processed: %d', $n));
    }

    private function processRemotePackage(Connection $connection, RemotePackageRepository $repo, RemotePackageImporter $importer, RemotePackageEntity $remotePackage)
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
            $remotePackage
                ->setProcessedOn(null)
                ->setFailCount($remotePackage->getFailCount() + 1)
                ->setLastError($error->getMessage())
            ;
            $em->persist($remotePackage);
            $em->flush($remotePackage);
            throw $error;
        }
    }
}
