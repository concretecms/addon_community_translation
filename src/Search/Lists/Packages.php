<?php

declare(strict_types=1);

namespace CommunityTranslation\Search\Lists;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Repository\Stats as StatsRepository;
use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Application\ApplicationAwareTrait;
use Concrete\Core\Database\Query\LikeBuilder;
use Concrete\Core\Search\ItemList\Database\ItemList;
use Concrete\Core\Search\Pagination\Pagination;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Pagerfanta\Doctrine\DBAL\QueryAdapter;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Class that manages the criterias of the package search.
 */
class Packages extends ItemList implements ApplicationAwareInterface
{
    use ApplicationAwareTrait;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Search\ItemList\ItemList::$paginationPageParameter
     */
    protected $paginationPageParameter = 'page';

    /**
     * Show stats about a specific locale.
     */
    private ?LocaleEntity $showStatsForLocale = null;

    private ?EntityManager $em = null;

    private ?StatsRepository $statsRepo = null;

    /**
     * Filter the results by keywords.
     *
     * @param string $name
     *
     * @return $this
     */
    public function filterByKeywords(string $name): self
    {
        $likeBuilder = $this->app->make(LikeBuilder::class);
        $likes = $likeBuilder->splitKeywordsForLike($name, '\W_');
        if ($likes === null || $likes === []) {
            return $this;
        }
        $expr = $this->query->expr();
        $andLike = null;
        foreach ($likes as $like) {
            $parameterName = $this->query->createNamedParameter($like);
            $orLike = null;
            foreach (['p.handle', 'p.name'] as $fieldName) {
                $like = $expr->like($fieldName, $parameterName);
                $orLike = $orLike === null ? $expr->or($like) : $orLike->with($like);
            }
            $andLike = $andLike === null ? $expr->and($orLike) : $andLike->with($orLike);
        }
        $this->query->andWhere($andLike);

        return $this;
    }

    /**
     * Show stats about a specific locale (null for none).
     *
     * @return $this
     */
    public function showStatsForLocale(?LocaleEntity $locale)
    {
        $this->showStatsForLocale = $locale;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Search\ItemList\Database\ItemList::createQuery()
     */
    public function createQuery()
    {
        $this->query
            ->select('p.id, coalesce(p.name, p.handle) as sortBy')
            ->from('CommunityTranslationPackages', 'p')
            ->orderBy('sortBy', 'asc')
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Search\ItemList\ItemList::getTotalResults()
     */
    public function getTotalResults()
    {
        $query = $this->deliverQueryObject();
        $query
            ->resetQueryParts(['groupBy', 'orderBy'])
            ->select('count(distinct p.id)')
            ->setMaxResults(1)
        ;

        return (int) $query->execute()->fetchOne();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Search\ItemList\ItemList::getResult()
     */
    public function getResult($queryRow)
    {
        $entityManager = $this->getEntityManager();
        $package = $entityManager->find(PackageEntity::class, (int) $queryRow['id']);
        if ($package === null) {
            return null;
        }
        $result = [$package];
        if ($this->showStatsForLocale === null) {
            return $result;
        }
        $pv = $package->getLatestVersion();
        if ($pv !== null) {
            $result[] = $this->getStatsRepo()->getOne($pv, $this->showStatsForLocale);
        } else {
            $result[] = null;
        }

        return $result;
    }

    public function createPaginationObject(): Pagination
    {
        $adapter = new QueryAdapter(
            $this->deliverQueryObject(),
            static function (QueryBuilder $query): void {
                $query
                    ->resetQueryParts(['groupBy', 'orderBy'])
                    ->select('count(distinct p.id)')
                    ->setMaxResults(1)
                ;
            }
        );

        return new Pagination($this, $adapter);
    }

    protected function getEntityManager(): EntityManager
    {
        if ($this->em === null) {
            $this->em = $this->app->make(EntityManager::class);
        }

        return $this->em;
    }

    protected function getStatsRepo(): StatsRepository
    {
        if ($this->statsRepo === null) {
            $this->statsRepo = $this->app->make(StatsRepository::class);
        }

        return $this->statsRepo;
    }
}
