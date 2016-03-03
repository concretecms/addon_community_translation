<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\System\CommunityTranslation\GitRepositories;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\CommunityTranslation\Src\Git\Repository;

class Details extends DashboardPageController
{
    public function view($repositoryID = null)
    {
        $repositoryID = @intval($repositoryID);
        $repository = ($repositoryID <= 0) ? null : $this->app->make('community_translation/git')->find($repositoryID);
        if ($repository === null) {
            $this->flash('error', t('Unable to find the specified repository'));
            $this->redirect('/dashboard/system/community_translation/git_repositories');
        }
        $this->set('repository', $repository);
    }

    public function add()
    {
        $this->set('repository', null);
    }

    public function save()
    {
        $repositoryID = $this->post('repositoryID');
        if ($repositoryID !== 'new') {
            $valn = $this->app->make('helper/validation/numbers');
            if (!$valn->integer($repositoryID)) {
                $this->flash('error', t('Unable to find the specified repository'));
                $this->redirect('/dashboard/system/community_translation/git_repositories');
            }
            $repositoryID = (int) $repositoryID;
        }
        $valt = $this->app->make('helper/validation/token');
        if (!$valt->validate('comtra-repository-save')) {
            $this->error->add($valt->getErrorMessage());
        } else {
            if ($repositoryID !== 'new') {
                $repository = $this->app->make('community_translation/git')->find($repositoryID);
                if ($repository === null) {
                    $this->flash('error', t('Unable to find the specified repository'));
                    $this->redirect('/dashboard/system/community_translation/git_repositories');
                }
            } else {
                $repository = new Repository();
            }
            $repository->setName($this->post('name'));
            if ($repository->getName() === '') {
                $this->error->add(t('Please specify the repository mnemonic name'));
            }
            $repository->setPackage($this->post('package'));
            $repository->setURL($this->post('url'));
            if ($repository->getURL() === '') {
                $this->error->add(t('Please specify the repository URL'));
            }
            $repository->setWebRoot($this->post('webroot'));
            switch ($this->post('parsetags')) {
                case '1':
                    $repository->setTagsFilter('< 0');
                    break;
                case '2':
                    $repository->setTagsFilter('');
                    break;
                case '3':
                    $repository->setTagsFilter($this->post('parsetagsOperator').' '.$this->post('parsetagsVersion'));
                    if ($repository->getTagsFilterExpanded() === null) {
                        $this->error->add(t('Please specify a valid version filter'));
                    }
                    break;
                default:
                    $this->error->add(t('Please specify if and how the repository tags should be parsed'));
                    break;
            }
            $branches = $this->post('branch');
            $versions = $this->post('version');
            if (!is_array($branches) || !is_array($versions) || count($branches) !== count($versions)) {
                $this->error->add(t('Invalid branch/version parameters received'));
            } else {
                $branches = array_values($branches);
                $versions = array_values($versions);
                $devBranches = array();
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
                $repository->setDevBranches($devBranches);
            }
            if (!$this->error->has()) {
                $already = $this->app->make('community_translation/git')->findOneBy(array('grName' => $repository->getName()));
                if ($already !== null && $already->getID() !== $repository->getID()) {
                    $this->error->add(t("There's already another repository named '%s'", $repository->getName()));
                } else {
                    $em = $this->app->make('community_translation/em');
                    $em->persist($repository);
                    $em->flush();
                    $this->flash('message', ($repositoryID === 'new') ? t('The Git Repository has been created') : t('The Git Repository has been updated'));
                    $this->redirect('/dashboard/system/community_translation/git_repositories');
                }
            }
        }
        if ($repositoryID === 'new') {
            $this->add();
        } else {
            $this->view($repositoryID);
        }
    }

    public function deleteRepository($repositoryID)
    {
        $valt = $this->app->make('helper/validation/token');
        if (!$valt->validate('comtra-repository-delete'.$repositoryID)) {
            $this->error->add($valt->getErrorMessage());
        } else {
            $repository = $this->app->make('community_translation/git')->find($repositoryID);
            if ($repository === null) {
                $this->flash('error', t('Unable to find the specified repository'));
                $this->redirect('/dashboard/system/community_translation/git_repositories');
            }
            $em = $this->app->make('community_translation/em');
            $em->remove($repository);
            $em->flush();
            $this->flash('message', t('The Git Repository has been deleted'));
            $this->redirect('/dashboard/system/community_translation/git_repositories');
        }
        $this->view($repositoryID);
    }
}
