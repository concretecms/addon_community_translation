<?php

namespace CommunityTranslation\Search\Lists;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Repository\Stats as StatsRepository;
use Concrete\Core\Application\Application;
use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Database\Query\LikeBuilder;
use Concrete\Core\Search\ItemList\Database\ItemList;
use Concrete\Core\Search\Pagination\Pagination;
use Doctrine\ORM\EntityManager;
use Pagerfanta\Adapter\DoctrineDbalAdapter;

/**
 * Class that manages the criterias of the boat searches.
 */
class Packages extends ItemList implements ApplicationAwareInterface
{
    /**
     * The application container.
     *
     * @var Application
     */
    protected $app;

    /**
     * {@inheritdoc}
     *
     * @see ApplicationAwareInterface::setApplication()
     */
    public function setApplication(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @var EntityManager|null
     */
    protected $em;

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        if ($this->em === null) {
            $this->em = $this->app->make(EntityManager::class);
        }

        return $this->em;
    }

    /**
     * @var StatsRepository|null
     */
    protected $stats;

    /**
     * @return StatsRepository
     */
    protected function getStats()
    {
        if ($this->stats === null) {
            $this->stats = $this->app->make(StatsRepository::class);
        }

        return $this->stats;
    }

    /**
     * The parameter name to be used for pagination.
     *
     * @var string
     */
    protected $paginationPageParameter = 'page';

    /**
     * {@inheritdoc}
     *
     * @see ItemList::createQuery()
     */
    public function createQuery()
    {
        $this->query->select('p.id, coalesce(p.name, p.handle) as sortBy')
            ->from('CommunityTranslationPackages', 'p')
            ->orderBy('sortBy', 'asc')
        ;
    }

    /**
     * Filter the results by keywords.
     *
     * @param string $name
     */
    public function filterByKeywords($name)
    {
        $likeBuilder = $this->app->make(LikeBuilder::class);
        /* @var LikeBuilder $likeBuilder */
        $likes = $likeBuilder->splitKeywordsForLike($name, '\W_');
        if (!empty($likes)) {
            $expr = $this->query->expr();
            $orFields = $expr->orX();
            foreach (['p.handle', 'p.name'] as $fieldName) {
                $and = $expr->andX();
                foreach ($likes as $like) {
                    $and->add($expr->like($fieldName, $this->query->createNamedParameter($like)));
                }
                $orFields->add($and);
            }
            $this->query->andWhere($orFields);
        }
    }

    /**
     * Show stats about a specific locale.
     *
     * @var LocaleEntity|null
     */
    protected $localeStats;

    /**
     * Show stats about a specific locale.
     *
     * @param LocaleEntity $locale
     */
    public function showLocaleStats(LocaleEntity $locale)
    {
        $this->localeStats = $locale;
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
            ->setMaxResults(1);
        $result = $query->execute()->fetchColumn();

        return (int) $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Search\ItemList\ItemList::createPaginationObject()
     */
    protected function createPaginationObject()
    {
        $adapter = new DoctrineDbalAdapter(
            $this->deliverQueryObject(),
            function (\Doctrine\DBAL\Query\QueryBuilder $query) {
                $query
                    ->resetQueryParts(['groupBy', 'orderBy'])
                    ->select('count(distinct p.id)')
                    ->setMaxResults(1);
            }
        );
        $pagination = new Pagination($this, $adapter);

        return $pagination;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Search\ItemList\ItemList::getResult()
     */
    public function getResult($queryRow)
    {
        $result = null;
        $entityManager = $this->getEntityManager();
        $package = $entityManager->find(PackageEntity::class, $queryRow['id']);

        if ($package !== null) {
            $result = [$package];
            if ($this->localeStats !== null) {
                $pv = $package->getLatestVersion();
                if ($pv !== null) {
                    $result[] = $this->getStats()->getOne($pv, $this->localeStats);
                } else {
                    $result[] = false;
                }
            }
        }

        return $result;
    }
}
