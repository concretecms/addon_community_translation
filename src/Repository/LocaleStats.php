<?php

declare(strict_types=1);

namespace CommunityTranslation\Repository;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\LocaleStats as LocaleStatsEntity;
use CommunityTranslation\Entity\Translatable as TranslatableEntity;
use CommunityTranslation\Entity\Translation as TranslationEntity;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

defined('C5_EXECUTE') or die('Access Denied.');

class LocaleStats extends EntityRepository
{
    /**
     * Get the stats for a specific locale.
     * If the stats don't exist yet, they are created.
     */
    public function getByLocale(LocaleEntity $locale): LocaleStatsEntity
    {
        return $this->find($locale) ?? $this->createLocaleStats($locale);
    }

    /**
     * Clear the stats for a specific locale.
     */
    public function resetForLocale(LocaleEntity $locale): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.locale = :locale')->setParameter('locale', $locale)
            ->getQuery()->execute();
    }

    /**
     * Clear the stats for all the locales.
     */
    public function resetAll(): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->getQuery()->execute();
    }

    private function createLocaleStats(LocaleEntity $locale): LocaleStatsEntity
    {
        $em = $this->getEntityManager();
        try {
            $numTranslatable = (int) $em->getRepository(TranslatableEntity::class)
                ->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->getQuery()->getSingleScalarResult();
        } catch (NoResultException $x) {
            $numTranslatable = 0;
        }
        if ($numTranslatable === 0) {
            $numApprovedTranslations = 0;
        } else {
            try {
                $numApprovedTranslations = (int) $em->getRepository(TranslationEntity::class)
                    ->createQueryBuilder('t')
                    ->select('COUNT(t.id)')
                    ->where('t.locale = :locale')->setParameter('locale', $locale)
                    ->andWhere('t.current = 1')
                    ->getQuery()->getSingleScalarResult();
            } catch (NoResultException $x) {
                $numApprovedTranslations = 0;
            }
        }
        $localeStats = new LocaleStatsEntity($locale, $numTranslatable, $numApprovedTranslations);
        $em->persist($localeStats);
        $em->flush($localeStats);

        return $localeStats;
    }
}
