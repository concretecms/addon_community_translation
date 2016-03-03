<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\System\CommunityTranslation;

use Concrete\Core\Page\Controller\DashboardPageController;

class GitRepositories extends DashboardPageController
{
    public function view()
    {
        $this->set('repositories', $this->app->make('community_translation/git')->findAll());
    }
}
