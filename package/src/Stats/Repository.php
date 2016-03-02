<?php

namespace Concrete\Package\CommunityTranslation\Src\Stats;

use Concrete\Package\CommunityTranslation\Src\Package\Package;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Concrete\Core\Application\Application;
use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Package\CommunityTranslation\Src\Exception;
use Doctrine\ORM\EntityRepository;

class Repository extends EntityRepository implements ApplicationAwareInterface
{
    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * Set the application object.
     *
     * @param Application $application
     */
    public function setApplication(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @param mixed $obj
     *
     * @return Package[]
     */
    protected function getPackages($obj)
    {
        $result = array();
        if ($obj instanceof Package) {
            $result[] = $obj;
        } elseif (is_array($obj)) {
            if (isset($obj['handle']) && isset($obj['version'])) {
                $p = $this->app->make('community_translation/package')->findOneBy(array(
                    'pHandle' => $obj['handle'],
                    'pVersion' => $obj['version'],
                ));
                if ($p === null) {
                    throw new Exception(t('Invalid translated package specified'));
                }
                $result[] = $p;
            } elseif (isset($obj[0]) && is_string($obj[1]) && isset($obj[1]) && is_string($obj[1])) {
                $p = $this->app->make('community_translation/package')->findOneBy(array(
                    'pHandle' => $obj[0],
                    'pVersion' => $obj[1],
                ));
                if ($p === null) {
                    throw new Exception(t('Invalid translated package specified'));
                }
                $result[] = $p;
            } else {
                foreach ($obj as $item) {
                    $result = array_merge($result, $this->getPackages($item));
                }
            }
        }
        if (empty($result)) {
            throw new Exception(t('Invalid translated package specified'));
        }

        return $result;
    }

    /**
     * @param mixed $obj
     *
     * @return Locale[]
     */
    protected function getLocales($obj)
    {
        $result = array();
        if ($obj instanceof Locale) {
            $result[] = $obj;
        } elseif (is_string($obj)) {
            $l = $this->app->make('community_translation/locale')->find($obj);
            if ($l === null) {
                throw new Exception(t('Invalid locale specified'));
            }
            $result[] = $l;
        } elseif (is_array($obj)) {
            foreach ($obj as $item) {
                $result = array_merge($result, $this->getLocales($item));
            }
        }
        if (empty($result)) {
            throw new Exception(t('Invalid locale specified'));
        }

        return $result;
    }

    /**
     * Get some stats about one or more packages and one or more locales.
     *
     * @param Package|Packages[]|array|array[array] $packages
     * @param Locale|Locale[]|string|string[] $locales
     *
     * @return Stats[]
     */
    public function get($packages, $locales)
    {
        $packs = array_values(array_unique($this->getPackages($packages)));
        $locs = array_values(array_unique($this->getLocales($locales)));
        $qb = $this->createQueryBuilder('s');
        if (count($packs) === 1) {
            $qb->andWhere('s.sPackage = :package');
            $qb->setParameter('package', $packs[0]);
        } else {
            $or = $qb->expr()->orX();
            foreach ($packs as $i => $p) {
                $or->add("s.sPackage = :package$i");
                $qb->setParameter("package$i", $p);
            }
            $qb->andWhere($or);
        }
        if (count($locs) === 1) {
            $qb->andWhere('s.sLocale = :locale');
            $qb->setParameter('locale', $locs[0]);
        } else {
            $or = $qb->expr()->orX();
            foreach ($locs as $i => $l) {
                $or->add("s.sLocale = :locale$i");
                $qb->setParameter("locale$i", $l);
            }
            $qb->andWhere($or);
        }

        $q = $qb->getQuery();
        $result = $q->getResult();
        /* @var Stats[] $result */
        $missingLocalesForPackages = array();
        foreach ($packs as $package) {
            foreach ($locs as $locale) {
                $found = false;
                foreach ($result as $stats) {
                    if ($stats->getPackage()->getID() === $package->getID() && $stats->getLocale()->getID() === $locale->getID()) {
                        $found = true;
                        break;
                    }
                }
                if ($found === false) {
                    if (!isset($missingLocalesForPackages[$package->getID()])) {
                        $missingLocalesForPackages[$package->getID()] = array('package' => $package, 'locales' => array());
                    }
                    $missingLocalesForPackages[$package->getID()]['locales'][] = $locale;
                }
            }
        }
        foreach ($missingLocalesForPackages as $missingLocalesForPackage) {
            $result = array_merge($result, $this->build($missingLocalesForPackage['package'], $missingLocalesForPackage['locales']));
        }

        return array_values($result);
    }

    public function resetForPackage(Package $package)
    {
        $r = $this->getEntityManager()
            ->createQuery('delete from '.$this->getEntityName().' as s where s.sPackage = :package')
                ->setParameter('package', $package)
                ->execute();
    }

    public function resetForLocale(Locale $locale)
    {
        $r = $this->getEntityManager()
            ->createQuery('delete from '.$this->getEntityName().' as s where s.sLocale = :locale')
                ->setParameter('locale', $locale)
                ->execute();
    }

    /**
     * @param Package $package
     * @param Locale[] $locales
     *
     * @return Stats
     */
    protected function build(Package $package, array $locales)
    {
        /* @var Locale[] $locales */
        $result = array();
        /* @var Stats[] $result */

        $total = $this->app->make('community_translation/translatable/place')
            ->createQueryBuilder('p')
                ->select('count(p.tpTranslatable)')
                ->where('p.tpPackage = :package')
                ->setParameter('package', $package)
                ->getQuery()
                    ->getSingleScalarResult()
        ;
        $total = empty($total) ? 0 : (int) $total;
        foreach ($locales as $locale) {
            $stats = Stats::create($package, $locale);
            $stats->setTotal($total);
            $result[$locale->getID()] = $stats;
        }
        $em = $this->getEntityManager();
        if ($total > 0) {
            $q = array($package->getID());
            $w = array();
            foreach ($locales as $locale) {
                $w[] = 't.tLocale = ?';
                $q[] = $locale->getID();
            }
            $dateTimeFormatString = $em->getConnection()->getDatabasePlatform()->getDateTimeFormatString();
            $rs = $this->app->make('community_translation/em')->getConnection()->executeQuery(
                '
                    select
                        t.tLocale,
                        count(t.tTranslatable) as translated,
                        max(tCreatedOn) as updated
                    from
                        TranslatablePlaces as p
                        inner join Translations as t on p.tpTranslatable = t.tTranslatable and 1 = t.tCurrent
                    where
                        p.tpPackage = ?
                        and ('.implode(' or ', $w).')
                    group by
                        t.tLocale
                ',
                $q
            );
            while (($row = $rs->fetch()) !== false) {
                if (!empty($row['translated'])) {
                    $result[$row['tLocale']]->setTranslated($row['translated']);
                    $result[$row['tLocale']]->setLastUpdated(\DateTime::createFromFormat($dateTimeFormatString, $row['updated']));
                }
            }
            $rs->closeCursor();
        }
        foreach ($result as $stats) {
            $em->persist($stats);
        }
        $em->flush();

        return $result;
    }
}
