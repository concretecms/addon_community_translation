<?php
namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\RemotePackage as RemotePackageEntity;
use CommunityTranslation\RemotePackage\Importer as RemotePackageImporter;
use CommunityTranslation\Repository\RemotePackage as RemotePackageRepository;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Exception;
use Throwable;

class ProcessRemotePackagesCommand extends Command
{
    protected function configure()
    {
        $errExitCode = static::RETURN_CODE_ON_FAILURE;
        $this
            ->setName('ct:remote-packages')
            ->setDescription('Process the  queued remote packages')
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
        $em = $this->app->make(EntityManager::class);
        /* @var EntityManager $em */
        $connection = $em->getConnection();
        $repo = $em->getRepository(RemotePackageEntity::class);
        /* @var RemotePackageRepository $repo */
        $importer = $this->app->make(RemotePackageImporter::class);
        /* @var RemotePackageImporter $importer */
        $n = 0;
        for (; ;) {
            $remotePackage = $repo->findOneBy(['approved' => true, 'processedOn' => null], ['createdOn' => 'ASC', 'id' => 'ASC']);
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
            $remotePackage->setProcessedOn(null);
            $em->persist($remotePackage);
            $em->flush($remotePackage);
            throw $error;
        }
    }
}
