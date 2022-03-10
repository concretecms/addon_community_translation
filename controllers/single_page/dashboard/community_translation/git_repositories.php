<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation;

use CommunityTranslation\Repository\GitRepository;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class GitRepositories extends DashboardPageController
{
    public function view(): ?Response
    {
        $repositories = $this->app->make(GitRepository::class)->findBy([], ['name' => 'ASC']);
        $this->set('urlResolver', $this->app->make(ResolverManagerInterface::class));
        $this->set('repositories', $repositories);

        return null;
    }
}
