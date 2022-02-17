<?php

declare(strict_types=1);

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\GitRepository as GitRepositoryEntity;
use CommunityTranslation\Git\Importer;
use Concrete\Core\Error\UserMessageException;
use Doctrine\ORM\EntityManager;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class ProcessGitRepositoriesCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
ct:git-repository
    {--r|repository=* : Limit the process to one or more repositories }
EOT
    ;

    private EntityManager $em;

    private Importer $importer;

    /**
     * @var \CommunityTranslation\Entity\GitRepository[]
     */
    private array $gitRepositories;

    public function handle(EntityManager $em, Importer $importer): int
    {
        $someError = false;
        $someSuccess = true;
        $mutexReleaser = null;
        $this->createLogger();
        try {
            $mutexReleaser = $this->acquireMutex();
            $this->em = $em;
            $this->importer = $importer;
            $importer->setLogger($this->logger);
            $this->readOptions();
            foreach ($this->gitRepositories as $gitRepository) {
                try {
                    $this->importGitRepository($gitRepository);
                    $someSuccess = true;
                } catch (Throwable $x) {
                    $this->logger->error($this->formatThrowable($x));
                    $someError = true;
                }
            }
            $this->logger->info('Processing completed');
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
            ->setDescription('Extract the translatable strings from the git repositories')
            ->setHelp(
                <<<'EOT'
Returns codes:
  0 operation completed successfully
  1 errors occurred but some repository has been processed
  2 errors occurred and no repository has been processed
EOT
            )
        ;
    }

    private function readOptions(): void
    {
        $repo = $this->em->getRepository(GitRepositoryEntity::class);
        $allGitRepositories = $repo->findAll();
        if ($allGitRepositories === []) {
            throw new UserMessageException('No git repository defined');
        }
        $filter = $this->input->getOption('repository');
        if ($filter === []) {
            $this->gitRepositories = $allGitRepositories;
        } else {
            $filterMap = [];
            foreach ($filter as $f) {
                $k = mb_strtoupper($f);
                if (isset($filterMap[$k])) {
                    throw new UserMessageException("Duplicated repository name specified in the --repository option: {$f}");
                }
                $filterMap[$k] = $f;
            }
            $gitRepositoriesMap = [];
            foreach ($allGitRepositories as $gitRepository) {
                $gitRepositoriesMap[mb_strtoupper($gitRepository->getName())] = $gitRepository;
            }
            $foundKeys = array_intersect(array_keys($filterMap), array_keys($gitRepositoriesMap));
            $missingKeys = array_diff(array_keys($filterMap), $foundKeys);
            if ($missingKeys !== []) {
                $missingFilters = [];
                foreach ($missingKeys as $missingKey) {
                    $missingFilters[] = $filterMap[$missingKey];
                }
                throw new UserMessageException("Unable to find these reposotories specified in the --repository option:\n- " . implode("\n- ", $missingFilters));
            }
            $gitRepositories = [];
            foreach ($foundKeys as $foundKey) {
                $gitRepositories[] = $gitRepositoriesMap[$foundKey];
            }
            $this->gitRepositories = $gitRepositories;
        }
    }

    private function importGitRepository(GitRepositoryEntity $gitRepository): void
    {
        $this->logger->info(sprintf('Processing repository %s', $gitRepository->getName()));
        $this->importer->import($gitRepository);
    }
}
