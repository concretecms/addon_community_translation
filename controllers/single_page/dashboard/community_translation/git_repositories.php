<?php

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation;

use CommunityTranslation\Repository\GitRepository;
use Concrete\Core\Page\Controller\DashboardPageController;

class GitRepositories extends DashboardPageController
{
    public function view()
    {
        $repositories = $this->app->make(GitRepository::class)->findBy([], ['name' => 'ASC']);
        $this->set('repositories', $repositories);
    }
}
