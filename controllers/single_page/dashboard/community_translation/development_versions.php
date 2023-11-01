<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation;

use CommunityTranslation\Entity\GitRepository;
use CommunityTranslation\Entity\Package\Version;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Checker;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class DevelopmentVersions extends DashboardPageController
{
    private ?Page $editGitRepositoryPage;

    public function view(): ?Response
    {
        $this->editGitRepositoryPage = null;
        $page = Page::getByPath('/dashboard/community_translation/git_repositories');
        if ($page && !$page->isError()) {
            if ((new Checker($page))->canViewPage()) {
                $this->editGitRepositoryPage = $page;
            }
        }
        $this->set('devVersions', $this->getDevVersions());

        return null;
    }

    public function deleteDevVersion(): JsonResponse
    {
        if (!$this->token->validate('ct-dever-delete')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $id = $this->request->request->getInt('id');
        $em = $this->app->make(EntityManagerInterface::class);
        $version = $id ? $em->find(Version::class, $id) : null;
        if ($version === null || !$version->isDevVersion()) {
            throw new UserMessageException(t('Unable to find the specified package version'));
        }
        $em->remove($version);
        $em->flush();

        return $this->app->make(ResponseFactoryInterface::class)->json(true);
    }

    private function getDevVersions(): array
    {
        $result = [];
        foreach ($this->listDevVersions() as $version) {
            $result[] = [
                'id' => $version->getID(),
                'package' => $version->getPackage()->getDisplayName(),
                'name' => $version->getDisplayVersion(),
                'version' => $version->getVersion(),
                'gitRepositories' => $this->getGitRepositories($version),
            ];
        }

        return $result;
    }

    /**
     * @return \CommunityTranslation\Entity\Package\Version[]
     */
    private function listDevVersions(): array
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $qb = $em->createQueryBuilder();
        $qb
            ->from(Version::class, 'v')
            ->join('v.package', 'p')
            ->addSelect('v, p')
            ->andWhere($qb->expr()->length('v.version') . ' > :devPrefixLength')
            ->setParameter('devPrefixLength', mb_strlen(Version::DEV_PREFIX))
            ->andWhere($qb->expr()->substring('v.version', 1, mb_strlen(Version::DEV_PREFIX)) . ' = :devPrefix')
            ->setParameter('devPrefix', Version::DEV_PREFIX)
            ->addOrderBy('p.handle', 'ASC')
        ;

        return $qb->getQuery()->execute();
    }

    /**
     * @return \CommunityTranslation\Entity\Package\[]
     */
    private function getGitRepositories(Version $version): array
    {
        $result = [];
        foreach ($this->listGitRepositories($version) as $gitRepository) {
            $result[] = [
                'name' => $gitRepository->getName(),
                'detailsUrl' => $this->editGitRepositoryPage === null ? '' : (string) $this->app->make(ResolverManagerInterface::class)->resolve([$this->editGitRepositoryPage, 'details', (string) $gitRepository->getID()]),
            ];
        }

        return $result;
    }

    /**
     * @return \CommunityTranslation\Entity\GitRepository[]
     */
    private function listGitRepositories(Version $version): array
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $qb = $em->createQueryBuilder();
        $qb
            ->from(GitRepository::class, 'gr')
            ->select('gr')
            ->andWhere('gr.packageHandle = :packageHandle')
            ->setParameter('packageHandle', $version->getPackage()->getHandle())
            ->addOrderBy('gr.name', 'ASC')
        ;
        $result = [];
        foreach ($qb->getQuery()->execute() as $gitRepository) {
            foreach ($gitRepository->getDevBranches() as $mappedVersion) {
                if ($mappedVersion === $version->getVersion()) {
                    $result[] = $gitRepository;
                    continue;
                }
            }
        }

        return $result;
    }
}
