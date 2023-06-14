<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation;

use CommunityTranslation\Git\Importer;
use CommunityTranslation\Repository\GitRepository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    public function import(): JsonResponse
    {
        if (!$this->token->validate('ct-gitrepositories-import')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $id = $this->request->request->get('id');
        $gitRepository = $id ? $this->app->make(GitRepository::class)->find((int) $id) : null;
        if ($gitRepository === null) {
            throw new UserMessageException(t('Failed to find the requested Git repository'));
        }
        $importer = $this->app->make(Importer::class);
        $importer->import($gitRepository);

        return $this->app->make(ResponseFactoryInterface::class)->json(true);
    }
}
