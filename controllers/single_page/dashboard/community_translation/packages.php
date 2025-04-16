<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation;

use CommunityTranslation\Entity\Package;
use CommunityTranslation\Entity\Package\Version;
use Concrete\Core\Database\Query\LikeBuilder;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class Packages extends DashboardPageController
{
    public function view(): ?Response
    {
        $this->addHeaderItem(
            <<<'EOT'
            <style>
            #app table table tbody>tr:last-child td {
                border-bottom-width: 0;
            }
            </style>
            EOT
        );

        return null;
    }

    public function search(): JsonResponse
    {
        if (!$this->token->validate('comtra-pkgs-' . __FUNCTION__)) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $searchWords = preg_split('/\s+/', $this->request->request->get('searchText', ''), -1, PREG_SPLIT_NO_EMPTY);
        if ($searchWords === []) {
            throw new UserMessageException(t('Please specify the search criteria'));
        }
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb
            ->from(Package::class, 'p')
            ->leftJoin('p.versions', 'v')
            ->select('v, p')
            ->addOrderBy('p.name', 'ASC')
            ->addOrderBy('p.handle', 'ASC')
        ;
        $likeBuilder = $this->app->make(LikeBuilder::class);
        $searchParamNames = [];
        foreach ($searchWords as $searchWord) {
            $searchParamName = 'word' . count($searchParamNames);
            $qb->setParameter($searchParamName, $likeBuilder->escapeForLike($searchWord), Types::STRING);
            $searchParamNames[] = $searchParamName;
        }
        $and = $qb->expr()->andX();
        foreach ($searchParamNames as $searchParamName) {
            $or = $qb->expr()->orX();
            foreach (['handle', 'name'] as $fieldName) {
                $or = $or->add($qb->expr()->like("p.{$fieldName}", ":{$searchParamName}"));
            }
            $and = $and->add($or);
        }
        $qb->andWhere($and);
        $result = [];
        foreach ($qb->getQuery()->execute() as $package) {
            $result[] = $this->serializePackage($package, true);
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($result);
    }

    public function renamePackage(): JsonResponse
    {
        if (!$this->token->validate('comtra-pkgs-' . __FUNCTION__)) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $newName = trim(preg_replace('/\s+/', ' ', $this->request->request->get('newName', '')));
        if ($newName === '') {
            throw new UserMessageException(t('Please specify the new name of the package'));
        }
        $em = $this->getEntityManager();
        $package = $em->find(Package::class, $this->request->request->getInt('packageID'));
        if ($package === null) {
            throw new UserMessageException(t('Unable to find the requested package'));
        }
        if ($package->getName() !== $newName) {
            $em->wrapInTransaction(static function () use ($em, $package, $newName): void {
                $package->setName($newName);
                $em->flush();
            });
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($this->serializePackage($package, false));
    }

    public function deletePackage(): JsonResponse
    {
        if (!$this->token->validate('comtra-pkgs-' . __FUNCTION__)) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $em = $this->getEntityManager();
        $package = $em->find(Package::class, $this->request->request->getInt('packageID'));
        if ($package === null) {
            throw new UserMessageException(t('Unable to find the requested package'));
        }
        if ($package->getVersions()->isEmpty() !== true) {
            throw new UserMessageException(t('Please delete all the versions before deleting a package'));
        }
        $em->wrapInTransaction(static function () use ($em, $package): void {
            $em->remove($package);
            $em->flush();
        });

        return $this->app->make(ResponseFactoryInterface::class)->json(true);
    }

    public function deletePackageVersion(): JsonResponse
    {
        if (!$this->token->validate('comtra-pkgs-' . __FUNCTION__)) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $em = $this->getEntityManager();
        $version = $em->find(Version::class, $this->request->request->getInt('versionID'));
        if ($version === null || $version->getPackage()->getID() !== $this->request->request->getInt('packageID')) {
            throw new UserMessageException(t('Unable to find the requested package version'));
        }
        $em->transactional(static function () use ($em, $version): void {
            $em->remove($version);
            $em->flush();
        });

        return $this->app->make(ResponseFactoryInterface::class)->json(true);
    }

    private function serializePackage(Package $package, bool $withVersions): array
    {
        $result = [
            'id' => $package->getID(),
            'handle' => $package->getHandle(),
            'name' => $package->getName(),
            'displayName' => $package->getDisplayName(),
        ];
        if ($withVersions) {
            $result['versions'] = array_map([$this, 'serializePackageVersion'], $package->getSortedVersions());
        }

        return $result;
    }

    private function serializePackageVersion(Version $version): array
    {
        return [
            'id' => $version->getID(),
            'version' => $version->getVersion(),
            'name' => $version->getDisplayVersion(),
            'isDev' => $version->isDevVersion(),
        ];
    }
}
