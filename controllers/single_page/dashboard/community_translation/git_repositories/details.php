<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation\GitRepositories;

use CommunityTranslation\Entity\GitRepository as GitRepositoryEntity;
use CommunityTranslation\Repository\GitRepository as GitRepositoryRepository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class Details extends DashboardPageController
{
    public function view(string|int $gitRepositoryID = ''): ?Response
    {
        $gitRepository = null;
        if ($gitRepositoryID === 'new') {
            $gitRepository = new GitRepositoryEntity();
            $this->set('otherGitRepositoriesWithSameHandleExist', false);
        } else {
            $gitRepository = $gitRepositoryID && is_numeric($gitRepositoryID) ? $this->entityManager->find(GitRepositoryEntity::class, (int) $gitRepositoryID) : null;
            if ($gitRepository === null) {
                $this->flash('error', t('Unable to find the specified repository'));

                return $this->buildRedirect('/dashboard/community_translation/git_repositories');
            }
            $this->set('otherGitRepositoriesWithSameHandleExist', $this->getOtherGitRepositoriesWithSameHandle($gitRepository, 1) !== []);
        }
        $this->set('urlResolver', $this->app->make(ResolverManagerInterface::class));
        $this->set('gitRepository', $gitRepository);
        if ($this->request->isMethod(Request::METHOD_POST)) {
            $devBranches = $this->processUserInputDevBranches(false);
        } else {
            $devBranches = [];
            foreach ($gitRepository->getDevBranches() as $branch => $version) {
                $devBranches[] = ['branch' => $branch, 'version' => $version];
            }
        }
        $this->set('devBranches', $devBranches);

        return null;
    }

    public function testTag2verRegex(): JsonResponse
    {
        $rf = $this->app->make(ResponseFactoryInterface::class);
        try {
            if (!$this->token->validate('ct-testregex')) {
                throw new UserMessageException($this->token->getErrorMessage());
            }
            $rx = (string) $this->request->request->get('rx', '');
            if ($rx === '') {
                throw new UserMessageException(t('Please specify the regular expression'));
            }
            $sampleTag = (string) $this->request->request->get('sampleTag', '');
            if ($sampleTag === '') {
                throw new UserMessageException(t('Please specify the sample git tag'));
            }
            $matches = null;
            $errorDescription = '';
            set_error_handler(static function ($errno, $errstr) use (&$errorDescription): void {
                $errorDescription = is_string($errstr) ? trim($errstr) : '';
            });
            try {
                $result = preg_match($rx, $sampleTag, $matches);
            } finally {
                restore_error_handler();
            }
            if ($result === false) {
                if ($errorDescription === '') {
                    throw new UserMessageException(t('The regular expression is malformed'));
                }
                throw new UserMessageException(t('The following error occurred while checking the regular expression: %s', $errorDescription));
            }
            switch ($result) {
                case 0:
                    throw new UserMessageException(t('The regular expression did not match the tag'));
                case 1:
                    break;
                default:
                    throw new UserMessageException(t('Too many regular expression matches'));
            }
            $match = $matches[1] ?? '';
            if ($match === '') {
                throw new UserMessageException(t('The regular expression matched an empty string'));
            }

            return $rf->json(['resultingVersion' => $match]);
        } catch (UserMessageException $x) {
            return $rf->json(['error' => $x->getMessage()]);
        }
    }

    public function save(): ?Response
    {
        $post = $this->request->request;
        $gitRepositoryID = $post->get('repositoryID');
        if ($gitRepositoryID !== 'new') {
            $valn = $this->app->make('helper/validation/numbers');
            if (!$valn->integer($gitRepositoryID, 1)) {
                $this->flash('error', t('Unable to find the specified repository'));

                return $this->buildRedirect('/dashboard/community_translation/git_repositories');
            }
            $gitRepositoryID = (int) $gitRepositoryID;
        }
        $valt = $this->app->make('helper/validation/token');
        if (!$valt->validate('comtra-repository-save')) {
            $this->error->add($valt->getErrorMessage());
        } else {
            if ($gitRepositoryID === 'new') {
                $gitRepository = new GitRepositoryEntity();
            } else {
                $gitRepository = $this->entityManager->find(GitRepositoryEntity::class, $gitRepositoryID);
                if ($gitRepository === null) {
                    $this->flash('error', t('Unable to find the specified repository'));

                    return $this->buildRedirect('/dashboard/community_translation/git_repositories');
                }
            }
            $this->processUserInput($gitRepository);
        }
        if ($this->error->has()) {
            return $this->view($gitRepositoryID);
        }
        if ($gitRepository->getID() === null) {
            $this->entityManager->persist($gitRepository);
        }
        $this->entityManager->createQueryBuilder()
            ->update(GitRepositoryEntity::class, 'gr')
            ->where('gr.packageHandle = :packageHandle')->setParameter('packageHandle', $gitRepository->getPackageHandle())
            ->set('gr.packageName', ':packageName')->setParameter('packageName', $gitRepository->getPackageName())
            ->getQuery()->execute()
        ;
        $this->entityManager->flush();
        $this->flash('message', ($gitRepositoryID === 'new') ? t('The Git Repository has been created') : t('The Git Repository has been updated'));

        return $this->buildRedirect('/dashboard/community_translation/git_repositories');
    }

    public function deleteRepository(string $gitRepositoryID): ?Response
    {
        if (empty($gitRepositoryID)) {
            return $this->buildRedirect('/dashboard/community_translation/git_repositories');
        }
        $valt = $this->app->make('helper/validation/token');
        if (!$valt->validate('comtra-repository-delete' . $gitRepositoryID)) {
            $this->error->add($valt->getErrorMessage());

            return $this->view($gitRepositoryID);
        }
        $gitRepository = is_numeric($gitRepositoryID) ? $this->app->make(GitRepositoryRepository::class)->find((int) $gitRepositoryID) : null;
        if ($gitRepository === null) {
            $this->flash('error', t('Unable to find the specified repository'));

            return $this->buildRedirect('/dashboard/community_translation/git_repositories');
        }
        $em = $this->app->make(EntityManager::class);
        $em->remove($gitRepository);
        $em->flush($gitRepository);
        $this->flash('message', t('The Git Repository has been deleted'));

        return $this->buildRedirect('/dashboard/community_translation/git_repositories');
    }

    private function processUserInput(GitRepositoryEntity $gitRepository): void
    {
        $post = $this->request->request;
        $name = $post->get('name');
        $name = is_string($name) ? trim($name) : '';
        if ($name === '') {
            $this->error->add(t('Please specify the repository mnemonic name'));
        } else {
            $already = $this->app->make(GitRepositoryRepository::class)->findOneBy(['name' => $name]);
            if ($already !== null && $already->getID() !== $gitRepository->getID()) {
                $this->error->add(t("There's already another repository named '%s'", $already->getName()));
            } else {
                $gitRepository->setName($name);
            }
        }
        $packageHandle = $post->get('packageHandle');
        $packageHandle = is_string($packageHandle) ? trim($packageHandle) : '';
        if ($packageHandle === '') {
            $this->error->add(t('Please specify the package handle'));
        } else {
            $gitRepository->setPackageHandle($packageHandle);
        }
        $packageName = $post->get('packageName');
        $packageName = is_string($packageName) ? trim($packageName) : '';
        if ($packageName === '') {
            $this->error->add(t('Please specify the package name'));
        } else {
            $gitRepository->setPackageName($packageName);
        }
        $url = $post->get('url');
        $url = is_string($url) ? trim($url) : '';
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error->add(t('Please specify the repository URL'));
        } else {
            $gitRepository->setURL($url);
        }
        $directoryToParse = $post->get('directoryToParse');
        $directoryToParse = is_string($directoryToParse) ? trim(str_replace(DIRECTORY_SEPARATOR, '/', trim($directoryToParse)), '/') : '';
        $gitRepository->setDirectoryToParse($directoryToParse);
        $directoryForPlaces = $post->get('directoryForPlaces');
        $directoryForPlaces = is_string($directoryForPlaces) ? trim(str_replace(DIRECTORY_SEPARATOR, '/', trim($directoryForPlaces)), '/') : '';
        $gitRepository->setDirectoryForPlaces($directoryForPlaces);
        $parsetags = $post->get('parsetags');
        $saveRegex = true;
        switch (is_string($parsetags) || is_int($parsetags) ? (string) $parsetags : '') {
            case '1':
                $gitRepository->setTagFilters(null);
                $saveRegex = false;
                break;
            case '2':
                $gitRepository->setTagFilters([]);
                break;
            case '3':
                $tagFilters = [];
                for ($i = 1; $i <= 2; $i++) {
                    $tagFilters[] = $post->get("parsetagsOperator{$i}") . ' ' . $post->get("parsetagsVersion{$i}");
                    if (!$post->get('parsetagsAnd2')) {
                        break;
                    }
                }
                $gitRepository->setTagFilters($tagFilters);
                break;
            default:
                $this->error->add(t('Please specify if and how the repository tags should be parsed'));
                break;
        }
        if ($saveRegex) {
            $gitRepository->setTagToVersionRegexp($post->get('tag2verregex'));
        }
        $devBranches = $this->processUserInputDevBranches(true);
        if (!$this->error->has()) {
            $gitRepository->setDevBranches($devBranches);
        }
    }

    private function processUserInputDevBranches(bool $forSave): array
    {
        $result = [];
        $branches = $this->request->request->get('branch');
        $versions = $this->request->request->get('version');
        if (!is_array($branches) || !is_array($versions) || count($branches) !== count($versions)) {
            if ($forSave) {
                $this->error->add(t('Invalid branch/version parameters received'));
            }

            return [];
        }
        $result = [];
        $branches = array_values($branches);
        $versions = array_values($versions);
        foreach ($branches as $i => $branch) {
            $branch = is_string($branch) ? trim($branch) : '';
            $version = is_string($versions[$i] ?? null) ? trim($versions[$i]) : '';
            if ($forSave) {
                if (isset($result[$branch])) {
                    $this->error->add(t('Duplicated branch: %s', $branch));
                } elseif ($branch !== '' && $version !== '') {
                    $result[$branch] = $version;
                } elseif ($branch !== '') {
                    $this->error->add(t('Missing version for branch %s', $branch));
                } elseif ($version !== '') {
                    $this->error->add(t('Missing branch for version %s', $version));
                }
            } else {
                $result[] = ['branch' => $branch, 'version' => $version];
            }
        }

        return $result;
    }

    /**
     * @return \CommunityTranslation\Entity\GitRepository[]
     */
    private function getOtherGitRepositoriesWithSameHandle(GitRepositoryEntity $gitRepository, ?int $maxResults = null): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->from(GitRepositoryEntity::class, 'gr')
            ->select('gr')
            ->andWhere('gr.packageHandle = :packageHandle')
            ->setParameter('packageHandle', $gitRepository->getPackageHandle())
            ->andWhere('gr.id <> :id')
            ->setParameter('id', $gitRepository->getID())
            ->setMaxResults($maxResults)
        ;

        return $qb->getQuery()->execute();
    }
}
