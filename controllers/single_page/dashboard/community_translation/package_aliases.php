<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation;

use CommunityTranslation\Entity\Package\Alias as AliasEntity;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Repository\Package\Alias as AliasRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use Concrete\Core\Database\Query\LikeBuilder;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class PackageAliases extends DashboardPageController
{
    public function view(): ?Response
    {
        $repo = $this->app->make(AliasRepository::class);
        $aliases = [];
        foreach ($repo->findAll() as $alias) {
            $aliases[] = $this->serializeAlias($alias);
        }
        $this->set('aliases', $aliases);

        return null;
    }

    public function createAlias(): JsonResponse
    {
        if (!$this->token->validate('ct-pa-add')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $package = $em->find(PackageEntity::class, (int) $this->request->request->getInt('package'));
        if ($package === null) {
            throw new UserMessageException(t('Please select a package'));
        }
        $handle = trim((string) $this->request->request->get('handle'));
        if ($handle === '') {
            throw new UserMessageException(t('Please specify the handle'));
        }
        if ($em->getRepository(PackageEntity::class)->getByHandle($handle) !== null) {
            throw new UserMessageException(t('The handle "%s" is already in use', $handle));
        }
        $alias = new AliasEntity($package, $handle);
        $package->getAliases()->add($alias);
        $em->persist($alias);
        $em->flush();

        return $this->app->make(ResponseFactoryInterface::class)->json($this->serializeAlias($alias));
    }

    public function deleteAlias(): JsonResponse
    {
        if (!$this->token->validate('ct-pa-del')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $handle = $this->request->request->get('handle');
        if (!is_string($handle) || $handle === '') {
            $alias = null;
        } else {
            $repo = $this->app->make(AliasRepository::class);
            $alias = $repo->findOneBy(['handle' => $handle]);
        }
        if ($alias === null) {
            throw new UserMessageException(t('Unable to find the requested package alias.'));
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $em->remove($alias);
        $em->flush();

        return $this->app->make(ResponseFactoryInterface::class)->json(true);
    }

    public function getPackages(): JsonResponse
    {
        if (!$this->token->validate('ct-pa-gp', $this->request->request->get('accessToken', ''))) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $query = $this->request->request->get('query');
        $words = is_string($query) ? preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) : [];
        if ($words === []) {
            throw new UserMessageException(t('Please specify part of the wanted package'));
        }
        $likeBuilder = $this->app->make(LikeBuilder::class);
        $repo = $this->app->make(PackageRepository::class);
        $qb = $repo->createQueryBuilder('p')
            ->addOrderBy('p.name')
            ->addOrderBy('p.handle')
        ;
        $ands = [];
        foreach ($words as $index => $word) {
            $parameterName = "p{$index}";
            $qb->setParameter($parameterName, $likeBuilder->escapeForLike($word));
            $ors = [];
            foreach (['handle', 'name'] as $field) {
                $ors[] = $qb->expr()->like("p.{$field}", ":{$parameterName}");
            }
            $ands[] = $qb->expr()->orX(...$ors);
        }
        $qb->andWhere(...$ands);
        $result = [];
        foreach ($qb->getQuery()->execute() as $package) {
            $result[] = [
                'id' => $package->getID(),
                'primary_label' => h($package->getDisplayName()) . ' (<code class="small">' . h($package->getHandle()) . '</code>)',
            ];
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($result);
    }

    private function serializeAlias(AliasEntity $alias): array
    {
        return [
            'handle' => $alias->getHandle(),
            'package' => [
                'id' => $alias->getPackage()->getID(),
                'handle' => $alias->getPackage()->getHandle(),
                'name' => $alias->getPackage()->getDisplayName(),
            ],
            'createdOn' => $alias->getCreatedOn()->getTimestamp(),
        ];
    }
}
