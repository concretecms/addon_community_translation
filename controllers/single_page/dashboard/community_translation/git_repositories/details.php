<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation\GitRepositories;

use CommunityTranslation\Entity\GitRepository as GitRepositoryEntity;
use CommunityTranslation\Repository\GitRepository as GitRepositoryRepository;
use Concrete\Core\Page\Controller\DashboardPageController;
use Doctrine\ORM\EntityManager;

class Details extends DashboardPageController
{
    public function view($gitRepositoryID)
    {
        $gitRepository = null;
        if ($gitRepositoryID === 'new') {
            $gitRepository = GitRepositoryEntity::create();
        } else {
            $gitRepositoryID = (int) $gitRepositoryID;
            $gitRepository = $this->entityManager->find(GitRepositoryEntity::class, $gitRepositoryID);
            if ($gitRepository === null) {
                $this->flash('error', t('Unable to find the specified repository'));
                $this->redirect('/dashboard/community_translation/git_repositories');
            }
        }
        $this->set('gitRepository', $gitRepository);
    }

    public function save()
    {
        $post = $this->request->request;
        $gitRepositoryID = $post->get('repositoryID');
        if ($gitRepositoryID !== 'new') {
            $valn = $this->app->make('helper/validation/numbers');
            if (!$valn->integer($gitRepositoryID)) {
                $this->flash('error', t('Unable to find the specified repository'));
                $this->redirect('/dashboard/community_translation/git_repositories');
            }
            $gitRepositoryID = (int) $gitRepositoryID;
        }
        $valt = $this->app->make('helper/validation/token');
        if (!$valt->validate('comtra-repository-save')) {
            $this->error->add($valt->getErrorMessage());
        } else {
            if ($gitRepositoryID !== 'new') {
                $gitRepository = $this->entityManager->find(GitRepositoryEntity::class, $gitRepositoryID);
                if ($gitRepository === null) {
                    $this->flash('error', t('Unable to find the specified repository'));
                    $this->redirect('/dashboard/community_translation/git_repositories');
                }
            } else {
                $gitRepository = GitRepositoryEntity::create();
            }
            if ($gitRepository->setName($post->get('name'))->getName() === '') {
                $this->error->add(t('Please specify the repository mnemonic name'));
            } else {
                $already = $this->app->make(GitRepositoryRepository::class)->findOneBy(['name' => $gitRepository->getName()]);
                if ($already !== null && $already->getID() !== $gitRepository->getID()) {
                    $this->error->add(t("There's already another repository named '%s'", $gitRepository->getName()));
                }
            }
            if ($gitRepository->setPackageHandle($post->get('packageHandle'))->getPackageHandle() === '') {
                $this->error->add(t('Please specify the package handle'));
            }
            if ($gitRepository->setURL($post->get('url'))->getURL() === '') {
                $this->error->add(t('Please specify the repository URL'));
            }
            $gitRepository
                ->setDirectoryToParse($post->get('directoryToParse'))
                ->setDirectoryForPlaces($post->get('directoryForPlaces'))
            ;
            switch ($post->get('parsetags')) {
                case '1':
                    $gitRepository->setTagFilters(null);
                    break;
                case '2':
                    $gitRepository->setTagFilters([]);
                    break;
                case '3':
                    $tagFilters = [];
                    for ($i = 1; $i <= 2; ++$i) {
                        $tagFilters[] = $post->get("parsetagsOperator$i") . ' ' . $post->get("parsetagsVersion$i");
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
            $gitRepository->setTagToVersionRegexp($post->get('tag2verregex'));
            $branches = $post->get('branch');
            $versions = $post->get('version');
            if (!is_array($branches) || !is_array($versions) || count($branches) !== count($versions)) {
                $this->error->add(t('Invalid branch/version parameters received'));
            } else {
                $branches = array_values($branches);
                $versions = array_values($versions);
                $devBranches = [];
                foreach ($branches as $i => $branch) {
                    $branch = trim($branch);
                    $version = trim($versions[$i]);
                    if (isset($devBranches[$branch])) {
                        $this->error->add(t('Duplicated branch: %s', $branch));
                    } elseif ($branch !== '' && $version !== '') {
                        $devBranches[$branch] = $version;
                    } elseif ($branch !== '') {
                        $this->error->add(t('Missing version for branch %s', $branch));
                    } elseif ($version !== '') {
                        $this->error->add(t('Missing branch for version %s', $version));
                    }
                }
                $gitRepository->setDevBranches($devBranches);
            }
            if (!$this->error->has()) {
                if ($gitRepository->getID() === null) {
                    $this->entityManager->persist($gitRepository);
                }
                $this->entityManager->flush();
                $this->flash('message', ($gitRepositoryID === 'new') ? t('The Git Repository has been created') : t('The Git Repository has been updated'));
                $this->redirect('/dashboard/community_translation/git_repositories');
            }
        }
        $this->view($gitRepositoryID);
    }

    public function deleteRepository($gitRepositoryID)
    {
        $valt = $this->app->make('helper/validation/token');
        if (!$valt->validate('comtra-repository-delete' . $gitRepositoryID)) {
            $this->error->add($valt->getErrorMessage());
        } else {
            $gitRepository = $this->app->make(GitRepositoryRepository::class)->find($gitRepositoryID);
            if ($gitRepository === null) {
                $this->flash('error', t('Unable to find the specified repository'));
                $this->redirect('/dashboard/community_translation/git_repositories');
            }
            $em = $this->app->make(EntityManager::class);
            $em->remove($gitRepository);
            $em->flush($gitRepository);
            $this->flash('message', t('The Git Repository has been deleted'));
            $this->redirect('/dashboard/community_translation/git_repositories');
        }
        $this->view($gitRepositoryID);
    }
}
