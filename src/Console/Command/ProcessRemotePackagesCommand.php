<?php

declare(strict_types=1);

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\RemotePackage as RemotePackageEntity;
use CommunityTranslation\RemotePackage\DownloadException;
use CommunityTranslation\RemotePackage\Importer as RemotePackageImporter;
use CommunityTranslation\Repository\RemotePackage as RemotePackageRepository;
use Concrete\Core\Error\UserMessageException;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Generator;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class ProcessRemotePackagesCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
ct:remote-packages
    {--m|max-failures=3 : The maximum number of failures before giving up with processing a package }
    {--t|try-unapproved= : Try to process unapproved packages that are not newer than a specified number of days }
EOT
    ;

    private int $maxFailures;

    private ?DateTimeImmutable $tryUnapprovedDateLimit;

    private EntityManager $em;

    private RemotePackageRepository $repo;

    private Connection $connection;

    private RemotePackageImporter $remotePackageImporter;

    public function handle(EntityManager $em, RemotePackageImporter $remotePackageImporter): int
    {
        $someSuccess = false;
        $someError = false;
        $mutexReleaser = null;
        $this->createLogger();
        try {
            $mutexReleaser = $this->acquireMutex();
            $this->em = $em;
            $this->repo = $this->em->getRepository(RemotePackageEntity::class);
            $this->connection = $this->em->getConnection();
            $this->remotePackageImporter = $remotePackageImporter;
            $this->readOptions();
            $numProcessed = 0;
            foreach ($this->getRemotePackagesToBeProcesses() as $remotePackage) {
                try {
                    $this->processRemotePackage($remotePackage, true);
                    $someSuccess = true;
                    $numProcessed++;
                } catch (Throwable $x) {
                    $this->logger->error($this->formatThrowable($x));
                    $someError++;
                }
            }
            $this->logger->debug(sprintf('Number of approved packages processed: %d', $numProcessed));
            if ($this->tryUnapprovedDateLimit !== null) {
                $numProcessed = 0;
                foreach ($this->getUnapprovedRemotePackageHandlesToTry() as $tryPackageHandle) {
                    try {
                        if ($this->tryProcessRemotePackage($tryPackageHandle) === true) {
                            $someSuccess = true;
                            $numProcessed++;
                        }
                    } catch (Throwable $x) {
                        $this->logger->error($this->formatThrowable($x));
                        $someError++;
                    }
                }
                $this->logger->debug(sprintf('Number of unapproved packages processed: %d', $numProcessed));
            }
        } catch (Throwable $x) {
            $this->logger->error($this->formatThrowable($x));
            $someError = true;
        } finally {
            if ($mutexReleaser !== null) {
                try {
                    $mutexReleaser();
                } catch (Throwable $x) {
                }
            }
        }
        if ($someError && $someSuccess) {
            return 1;
        }
        if ($someError) {
            return 2;
        }

        return 0;
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
            ->setDescription('Process the queued remote packages')
            ->setHelp(
                <<<'EOT'
Returns codes:
  0 operation completed successfully
  1 errors occurred but some package has been processed
  2 errors occurred and no package has been processed
EOT
            )
        ;
    }

    private function readOptions(): void
    {
        $maxFailures = $this->input->getOption('max-failures');
        $maxFailures = preg_match('/^\d+$/', $maxFailures) ? (int) $maxFailures : 0;
        if ($maxFailures < 1) {
            throw new UserMessageException('Invalid value of the max-failures option');
        }
        $this->maxFailures = $maxFailures;
        $tryUapprovedMaxAge = $this->input->getOption('try-unapproved');
        if ($tryUapprovedMaxAge === null) {
            $this->tryUnapprovedDateLimit = null;
        } else {
            $tryUapprovedMaxAge = preg_match('/^\d+$/', $tryUapprovedMaxAge) ? (int) $tryUapprovedMaxAge : -1;
            if ($tryUapprovedMaxAge < 0) {
                throw new UserMessageException('Invalid value of the try-unapproved option');
            }
            $this->tryUnapprovedDateLimit = new DateTimeImmutable("-{$tryUapprovedMaxAge} days");
        }
    }

    /**
     * @return \CommunityTranslation\Entity\RemotePackage[]
     */
    private function getRemotePackagesToBeProcesses(): Generator
    {
        $criteria = new Criteria();
            $criteria
                ->andWhere($criteria->expr()->eq('approved', true))
                ->andWhere($criteria->expr()->isNull('processedOn'))
                ->andWhere($criteria->expr()->lt('failCount', $this->maxFailures))
                ->orderBy(['createdOn' => 'ASC', 'id' => 'ASC'])
                ->setMaxResults(1)
        ;
        for (;;) {
            $remotePackage = $this->repo->matching($criteria)->first();
            if ($remotePackage === false || $remotePackage === null) {
                return;
            }
            yield $remotePackage;
        }
    }

    /**
     * @return string[]
     */
    private function getUnapprovedRemotePackageHandlesToTry(): Generator
    {
        $qb = $this->repo->createQueryBuilder('rp');
        $expr = $qb->expr();
        $qb
            ->distinct()
            ->select('rp.handle')
            ->where($expr->isNull('rp.processedOn'))
            ->andWhere($expr->eq('rp.approved', 0))
            ->andWhere($expr->gte('rp.createdOn', ':minCreatedOn'))
            ->setParameter('minCreatedOn', $this->tryUnapprovedDateLimit->format($this->connection->getDatabasePlatform()->getDateTimeFormatString()))
        ;
        $iterator = $qb->getQuery()->toIterable();
        foreach ($iterator as $row) {
            yield $row['handle'];
        }
    }

    private function processRemotePackage(RemotePackageEntity $remotePackage, bool $recordFailures): void
    {
        $this->logger->debug(sprintf('Processing package %s v%s', $remotePackage->getHandle(), $remotePackage->getVersion()));
        $remotePackage->setProcessedOn(new DateTimeImmutable());
        $this->em->persist($remotePackage);
        $this->em->flush($remotePackage);
        try {
            $this->connection->transactional(function () use ($remotePackage) {
                $this->remotePackageImporter->import($remotePackage);
            });
        } catch (Throwable $error) {
            $remotePackage->setProcessedOn(null);
            if ($recordFailures) {
                $remotePackage
                    ->setFailCount($remotePackage->getFailCount() + 1)
                    ->setLastErrorFromThrowable($error)
                ;
            }
            $this->em->persist($remotePackage);
            $this->em->flush($remotePackage);
            if ($error instanceof DownloadException && $error->getHttpCode() === 404) {
                $this->logger->debug(sprintf('  NOT FOUND!'));
            } else {
                throw $error;
            }
        }
    }

    private function tryProcessRemotePackage(string $remotePackageHandle): bool
    {
        $this->logger->debug(sprintf('Trying unapproved package with handle %s', $remotePackageHandle));
        $remotePackage = $this->repo->findOneBy(
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

            return false;
        }
        try {
            $this->processRemotePackage($remotePackage, false);
        } catch (Throwable $error) {
            $this->logger->debug(sprintf(' - FAILED: %s', trim($error->getMessage())));

            return false;
        }
        $this->logger->debug(' - SUCCEEDED: marking the package as approved');
        $qb = $this->repo->createQueryBuilder('rp');
        $qb
            ->update()
            ->set('rp.approved', true)
            ->where($qb->expr()->eq('rp.handle', ':handle'))
            ->setParameter('handle', $remotePackageHandle)
        ;
        $qb->getQuery()->execute();

        return true;
    }
}
