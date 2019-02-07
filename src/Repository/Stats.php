<?php

namespace CommunityTranslation\Repository;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\Stats as StatsEntity;
use CommunityTranslation\Entity\Translatable as TranslatableEntity;
use CommunityTranslation\Entity\Translation as TranslationEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Repository\Translatable\Place as TranslatablePlaceRepository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Support\Facade\Application;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

class Stats extends EntityRepository
{
    /**
     * @param mixed $obj
     *
     * @return PackageVersionEntity[]
     */
    protected function getPackageVersions($obj)
    {
        $result = [];
        if ($obj instanceof PackageVersionEntity) {
            $result[] = $obj;
        } elseif (is_array($obj)) {
            $app = Application::getFacadeApplication();
            if (isset($obj['handle']) && isset($obj['version'])) {
                $pv = $app->make(PackageVersionRepository::class)->findByHandleAndVersion(
                    $obj['handle'],
                    $obj['version']
                );
                if ($pv === null) {
                    throw new UserMessageException(t('Invalid translated package specified'));
                }
                $result[] = $pv;
            } elseif (isset($obj[0]) && is_string($obj[1]) && isset($obj[1]) && is_string($obj[1])) {
                $pv = $app->make(PackageVersionRepository::class)->findByHandleAndVersion(
                    $obj[0],
                    $obj[1]
                );
                if ($pv === null) {
                    throw new UserMessageException(t('Invalid translated package specified'));
                }
                $result[] = $pv;
            } else {
                foreach ($obj as $item) {
                    $result = array_merge($result, $this->getPackageVersions($item));
                }
            }
        }
        if (empty($result)) {
            throw new UserMessageException(t('Invalid translated package specified'));
        }

        return $result;
    }

    /**
     * @param mixed $obj
     *
     * @return LocaleEntity[]
     */
    protected function getLocales($obj)
    {
        $result = [];
        if ($obj instanceof LocaleEntity) {
            $result[] = $obj;
        } elseif (is_string($obj)) {
            $app = Application::getFacadeApplication();
            $l = $app->make(LocaleRepository::class)->findApproved($obj);
            if ($l === null) {
                throw new UserMessageException(t('Invalid locale specified'));
            }
            $result[] = $l;
        } elseif (is_array($obj)) {
            foreach ($obj as $item) {
                $result = array_merge($result, $this->getLocales($item));
            }
        }
        if (empty($result)) {
            throw new UserMessageException(t('Invalid locale specified'));
        }

        return $result;
    }

    /**
     * Get some stats about a package version and a locale.
     *
     * @param PackageVersionEntity $packageVersion
     * @param LocaleEntity $locale
     *
     * @return Stats
     */
    public function getOne(PackageVersionEntity $packageVersion, LocaleEntity $locale)
    {
        $array = $this->get($packageVersion, $locale);

        return array_shift($array);
    }

    /**
     * Get some stats about one or more package versions and one or more locales.
     *
     * @param PackageVersionEntity|PackageVersionEntity[]|array|array[array] $wantedPackageVersions
     * @param LocaleEntity|LocaleEntity[]|string|string[] $wantedLocales
     *
     * @return StatsEntity[]
     */
    public function get($wantedPackageVersions, $wantedLocales)
    {
        $packageVersions = array_values(array_unique($this->getPackageVersions($wantedPackageVersions)));
        $locales = array_values(array_unique($this->getLocales($wantedLocales)));
        $qb = $this->createQueryBuilder('s');
        if (count($packageVersions) === 1) {
            $qb->andWhere('s.packageVersion = :packageVersion')->setParameter('packageVersion', $packageVersions[0]);
        } else {
            $or = $qb->expr()->orX();
            foreach ($packageVersions as $i => $pv) {
                $or->add("s.packageVersion = :packageVersion$i");
                $qb->setParameter("packageVersion$i", $pv);
            }
            $qb->andWhere($or);
        }
        if (count($locales) === 1) {
            $qb->andWhere('s.locale = :locale');
            $qb->setParameter('locale', $locales[0]);
        } else {
            $or = $qb->expr()->orX();
            foreach ($locales as $i => $l) {
                $or->add("s.locale = :locale$i");
                $qb->setParameter("locale$i", $l);
            }
            $qb->andWhere($or);
        }

        $result = $qb->getQuery()->getResult();
        /* @var StatsEntity[] $result */
        $missingLocalesForPackageVersions = [];
        foreach ($packageVersions as $packageVersion) {
            foreach ($locales as $locale) {
                $found = false;
                foreach ($result as $stats) {
                    if ($stats->getPackageVersion()->getID() === $packageVersion->getID() && $stats->getLocale()->getID() === $locale->getID()) {
                        $found = true;
                        break;
                    }
                }
                if ($found === false) {
                    if (!isset($missingLocalesForPackageVersions[$packageVersion->getID()])) {
                        $missingLocalesForPackageVersions[$packageVersion->getID()] = ['packageVersion' => $packageVersion, 'locales' => []];
                    }
                    $missingLocalesForPackageVersions[$packageVersion->getID()]['locales'][] = $locale;
                }
            }
        }
        foreach ($missingLocalesForPackageVersions as $missingLocalesForPackageVersion) {
            $result = array_merge($result, $this->build($missingLocalesForPackageVersion['packageVersion'], $missingLocalesForPackageVersion['locales']));
        }

        return array_values($result);
    }

    /**
     * @param PackageVersionEntity $packageVersion
     */
    public function resetForPackageVersion(PackageVersionEntity $packageVersion)
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->where('s.packageVersion = :packageVersion')->setParameter('packageVersion', $packageVersion)
            ->getQuery()->execute();
    }

    /**
     * @param LocaleEntity $locale
     */
    public function resetForLocale(LocaleEntity $locale)
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->where('s.locale = :locale')->setParameter('locale', $locale)
            ->getQuery()->execute();
    }

    /**
     * @param TranslationEntity $translation
     */
    public function resetForTranslation(TranslationEntity $translation)
    {
        $this->resetForLocaleTranslatables($translation->getLocale(), $translation->getTranslatable());
    }

    /**
     * @param LocaleEntity $locale
     * @param TranslatableEntity|int|TranslatableEntity[]|int[] $translatables
     *
     * @throws UserMessageException
     */
    public function resetForLocaleTranslatables(LocaleEntity $locale, $translatables)
    {
        $this->resetForLocale($locale);
        $translatableIDs = [];
        if ($translatables) {
            if ($translatables instanceof TranslatableEntity) {
                $translatableIDs[] = $translatables->getID();
            } elseif (is_int($translatables)) {
                $translatableIDs[] = $translatables;
            } elseif (is_array($translatables)) {
                foreach ($translatables as $translatable) {
                    if ($translatable instanceof TranslatableEntity) {
                        $translatableIDs[] = $translatable->getID();
                    } elseif (is_int($translatable)) {
                        $translatableIDs[] = $translatable;
                    } else {
                        throw new UserMessageException(t('Invalid translatable string specified'));
                    }
                }
            }
        }
        if (empty($translatableIDs)) {
            throw new UserMessageException(t('Invalid translatable string specified'));
        }
        $this->getEntityManager()->getConnection()->executeQuery(
            '
                delete s.*
                from CommunityTranslationStats as s
                inner join CommunityTranslationTranslatablePlaces as p on s.packageVersion = p.packageVersion
                where s.locale = ?
                and (p.translatable = ' . implode(' or p.translatable = ', $translatableIDs) . ')
            ',
            [$locale->getID()]
        );
    }

    /**
     * @param PackageVersionEntity $packageVersion
     * @param LocaleEntity[] $locales
     *
     * @return Stats
     */
    protected function build(PackageVersionEntity $packageVersion, array $locales)
    {
        $result = [];
        $app = Application::getFacadeApplication();
        try {
            $total = (int) $app->make(TranslatablePlaceRepository::class)
                ->createQueryBuilder('p')
                    ->select('count(p.translatable)')
                    ->where('p.packageVersion = :packageVersion')->setParameter('packageVersion', $packageVersion)
                    ->getQuery()->getSingleScalarResult();
        } catch (NoResultException $x) {
            $total = 0;
        }
        foreach ($locales as $locale) {
            $result[$locale->getID()] = StatsEntity::create($packageVersion, $locale)->setTotal($total);
        }
        $em = $this->getEntityManager();
        $connection = $em->getConnection();
        if ($total > 0) {
            $q = [$packageVersion->getID()];
            $w = [];
            foreach ($locales as $locale) {
                $w[] = 't.locale = ?';
                $q[] = $locale->getID();
            }
            $dateTimeFormatString = $connection->getDatabasePlatform()->getDateTimeFormatString();
            $rs = $connection->executeQuery(
                '
                    select
                        t.locale,
                        count(t.translatable) as translated,
                        max(t.currentSince) as updated
                    from
                        CommunityTranslationTranslatablePlaces as p
                        inner join CommunityTranslationTranslations as t on p.translatable = t.translatable and 1 = t.current
                    where
                        p.packageVersion = ?
                        and (' . implode(' or ', $w) . ')
                    group by
                        t.locale
                ',
                $q
            );
            while (($row = $rs->fetch()) !== false) {
                if (!empty($row['translated'])) {
                    $result[$row['locale']]
                        ->setTranslated($row['translated'])
                        ->setLastUpdated(DateTime::createFromFormat($dateTimeFormatString, $row['updated']));
                }
            }
            $rs->closeCursor();
        }
        foreach ($result as $stats) {
            $em->persist($stats);
        }
        $em->flush();

        return array_values($result);
    }
}
