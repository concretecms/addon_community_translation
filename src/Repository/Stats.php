<?php

declare(strict_types=1);

namespace CommunityTranslation\Repository;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\Stats as StatsEntity;
use CommunityTranslation\Entity\Translatable as TranslatableEntity;
use CommunityTranslation\Entity\Translatable\Place as TranslatablePlaceEntity;
use CommunityTranslation\Entity\Translation as TranslationEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Repository\Translatable\Place as TranslatablePlaceRepository;
use Concrete\Core\Error\UserMessageException;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

defined('C5_EXECUTE') or die('Access Denied.');

class Stats extends EntityRepository
{
    private ?PackageVersionRepository $packageVersionRepository = null;

    private ?LocaleRepository $localeRepository = null;

    private ?TranslatablePlaceRepository $translatablePlaceRepository = null;

    /**
     * Get some stats about one or more package versions and one or more locales.
     *
     * @param \CommunityTranslation\Entity\Package\Version|\CommunityTranslation\Entity\Package\Version[]|array|array[] $wantedPackageVersions
     * @param \CommunityTranslation\Entity\Locale|\CommunityTranslation\Entity\Locale[]|string|string[] $wantedLocales
     *
     * @return \CommunityTranslation\Entity\Stats[]
     */
    public function get($wantedPackageVersions, $wantedLocales): array
    {
        $packageVersions = array_values(array_unique($this->resolvePackageVersions($wantedPackageVersions), SORT_REGULAR));
        $locales = array_values(array_unique($this->resolveLocales($wantedLocales), SORT_REGULAR));
        $qb = $this->createQueryBuilder('s');
        if (count($packageVersions) === 1) {
            $qb->andWhere('s.packageVersion = :packageVersion')->setParameter('packageVersion', $packageVersions[0]);
        } else {
            $or = $qb->expr()->orX();
            foreach ($packageVersions as $i => $pv) {
                $or->add("s.packageVersion = :packageVersion{$i}");
                $qb->setParameter("packageVersion{$i}", $pv);
            }
            $qb->andWhere($or);
        }
        if (count($locales) === 1) {
            $qb->andWhere('s.locale = :locale');
            $qb->setParameter('locale', $locales[0]);
        } else {
            $or = $qb->expr()->orX();
            foreach ($locales as $i => $l) {
                $or->add("s.locale = :locale{$i}");
                $qb->setParameter("locale{$i}", $l);
            }
            $qb->andWhere($or);
        }
        $result = $qb->getQuery()->getResult();
        /** @var StatsEntity[] $result */
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

    public function resetForPackageVersion(PackageVersionEntity $packageVersion): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->where('s.packageVersion = :packageVersion')->setParameter('packageVersion', $packageVersion)
            ->getQuery()->execute();
    }

    public function resetForLocale(LocaleEntity $locale): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->where('s.locale = :locale')->setParameter('locale', $locale)
            ->getQuery()->execute();
    }

    public function resetForTranslation(TranslationEntity $translation): void
    {
        $this->resetForLocaleTranslatables($translation->getLocale(), $translation->getTranslatable());
    }

    /**
     * @param \CommunityTranslation\Entity\Translatable|int|\CommunityTranslation\Entity\Translatable[]|int[] $translatables
     *
     * @throws UserMessageException
     */
    public function resetForLocaleTranslatables(LocaleEntity $locale, $translatables)
    {
        $translatableIDs = $this->resolveTranslatableIDs($translatables);
        $this->resetForLocale($locale);
        $this->getEntityManager()->getConnection()->executeStatement(
            <<<'EOT'
DELETE
    s.*
FROM
    CommunityTranslationStats as s
    INNER JOIN CommunityTranslationTranslatablePlaces AS p
        ON s.packageVersion = p.packageVersion
WHERE
    s.locale = :locale
    AND p.translatable IN (:translatables)
EOT
            ,
            [
                'locale' => $locale->getID(),
                'translatables' => $translatableIDs,
            ],
            [
                'locale' => ParameterType::STRING,
                'translatables' => Connection::PARAM_INT_ARRAY,
            ]
        );
    }

    /**
     * Get some stats about a package version and a locale.
     */
    public function getOne(PackageVersionEntity $packageVersion, LocaleEntity $locale): StatsEntity
    {
        $array = $this->get($packageVersion, $locale);

        return array_shift($array);
    }

    protected function getPackageVersionRepository(): PackageVersionRepository
    {
        if ($this->packageVersionRepository === null) {
            $this->packageVersionRepository = $this->getEntityManager()->getRepository(PackageVersionEntity::class);
        }

        return $this->packageVersionRepository;
    }

    protected function getLocaleRepository(): LocaleRepository
    {
        if ($this->localeRepository === null) {
            $this->localeRepository = $this->getEntityManager()->getRepository(LocaleEntity::class);
        }

        return $this->localeRepository;
    }

    protected function getTranslatablePlaceRepository(): TranslatablePlaceRepository
    {
        if ($this->translatablePlaceRepository === null) {
            $this->translatablePlaceRepository = $this->getEntityManager()->getRepository(TranslatablePlaceEntity::class);
        }

        return $this->translatablePlaceRepository;
    }

    /**
     * @param \CommunityTranslation\Entity\Package\Version|\CommunityTranslation\Entity\Package\Version[]|array|array[] $mixed
     *
     * @return \CommunityTranslation\Entity\Package\Version[]
     */
    protected function resolvePackageVersions($mixed): array
    {
        if ($mixed instanceof PackageVersionEntity) {
            return [$mixed];
        }
        $result = [];
        if (is_array($mixed)) {
            if (is_string($mixed['handle'] ?? null) && is_string($mixed['version'] ?? null)) {
                $pv = $this->getPackageVersionRepository()->findByHandleAndVersion($mixed['handle'], $mixed['version']);
                if ($pv === null) {
                    throw new UserMessageException(t('Invalid translated package specified'));
                }

                return [$pv];
            }
            if (is_string($mixed[0] ?? null) && is_string($mixed[1] ?? null)) {
                $pv = $this->getPackageVersionRepository()->findByHandleAndVersion($mixed[0], $mixed[1]);
                if ($pv === null) {
                    throw new UserMessageException(t('Invalid translated package specified'));
                }

                return [$pv];
            }
            foreach ($mixed as $item) {
                $result = array_merge($result, $this->resolvePackageVersions($item));
            }
        }
        if ($result === []) {
            throw new UserMessageException(t('Invalid translated package specified'));
        }

        return $result;
    }

    /**
     * @param \CommunityTranslation\Entity\Locale|string|array $mixed
     *
     * @return \CommunityTranslation\Entity\Locale[]
     */
    protected function resolveLocales($mixed): array
    {
        if ($mixed instanceof LocaleEntity) {
            return [$mixed];
        }
        if (is_string($mixed)) {
            $l = $mixed === '' ? null : $this->getLocaleRepository()->findApproved($mixed);
            if ($l === null) {
                throw new UserMessageException(t('Invalid locale specified'));
            }

            return [$l];
        }
        $result = [];
        if (is_array($mixed)) {
            foreach ($mixed as $item) {
                $result = array_merge($result, $this->resolveLocales($item));
            }
        }
        if ($result === []) {
            throw new UserMessageException(t('Invalid locale specified'));
        }

        return $result;
    }

    /**
     * @param \CommunityTranslation\Entity\Translatable|int|\CommunityTranslation\Entity\Translatable[]|int[] $mixed
     *
     * @return int[]
     */
    protected function resolveTranslatableIDs($mixed): array
    {
        $result = [];
        if ($mixed instanceof TranslatableEntity) {
            return [$mixed->getID()];
        }
        if (is_int($mixed)) {
            return [$mixed];
        }
        $result = [];
        if (is_array($mixed)) {
            foreach ($mixed as $item) {
                $result = array_merge($result, $this->resolveTranslatableIDs($item));
            }
        }
        if ($result === []) {
            throw new UserMessageException(t('Invalid translatable string specified'));
        }

        return $result;
    }

    /**
     * @param \CommunityTranslation\Entity\Locale[] $locales
     *
     * @return \CommunityTranslation\Entity\Stats[]
     */
    protected function build(PackageVersionEntity $packageVersion, array $locales): array
    {
        $result = [];
        try {
            $total = (int) $this->getTranslatablePlaceRepository()
                ->createQueryBuilder('p')
                ->select('count(p.translatable)')
                ->where('p.packageVersion = :packageVersion')->setParameter('packageVersion', $packageVersion)
                ->getQuery()->getSingleScalarResult();
        } catch (NoResultException $x) {
            $total = 0;
        }
        foreach ($locales as $locale) {
            $result[$locale->getID()] = new StatsEntity($packageVersion, $locale, $total);
        }
        $em = $this->getEntityManager();
        $connection = $em->getConnection();
        if ($total > 0) {
            $localeIDs = array_map(
                static function (LocaleEntity $locale): string {
                    return $locale->getID();
                },
                $locales
            );
            $dateTimeFormatString = $connection->getDatabasePlatform()->getDateTimeFormatString();
            $rs = $connection->executeQuery(
                <<<'EOT'
SELECT
    t.locale,
    COUNT(t.translatable) AS translated,
    MAX(t.currentSince) AS updated
FROM
    CommunityTranslationTranslatablePlaces as p
    INNER JOIN CommunityTranslationTranslations AS t
        ON p.translatable = t.translatable AND 1 = t.current
WHERE
    p.packageVersion = :packageVersion
    AND t.locale IN (:localeIDs)
GROUP BY
    t.locale
EOT
                ,
                [
                    'packageVersion' => $packageVersion->getID(),
                    'localeIDs' => $localeIDs,
                ],
                [
                    'packageVersion' => ParameterType::INTEGER,
                    'localeIDs' => Connection::PARAM_STR_ARRAY,
                ]
            );
            while (($row = $rs->fetch()) !== false) {
                if (!empty($row['translated'])) {
                    $result[$row['locale']]
                        ->setTranslated((int) $row['translated'])
                        ->setLastUpdated(DateTimeImmutable::createFromFormat($dateTimeFormatString, $row['updated']))
                    ;
                }
            }
        }
        foreach ($result as $stats) {
            $em->persist($stats);
        }
        $em->flush();

        return array_values($result);
    }
}
