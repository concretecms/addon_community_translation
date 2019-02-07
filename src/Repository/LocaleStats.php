<?php

namespace CommunityTranslation\Repository;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\LocaleStats as LocaleStatsEntity;
use CommunityTranslation\Repository\Translatable as TranslatableRepository;
use CommunityTranslation\Repository\Translation as TranslationRepository;
use Concrete\Core\Support\Facade\Application;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

class LocaleStats extends EntityRepository
{
    /**
     * Get the stats for a specific locale.
     * If the stats does not exist yet, they are created.
     *
     * @return LocaleStatsEntity
     */
    public function getByLocale(LocaleEntity $locale)
    {
        $app = Application::getFacadeApplication();
        $result = $this->find($locale);
        if ($result === null) {
            $em = $this->getEntityManager();
            try {
                $numTranslatable = (int) $app->make(TranslatableRepository::class)
                    ->createQueryBuilder('t')
                        ->select('count(t.id)')
                        ->getQuery()->getSingleScalarResult();
            } catch (NoResultException $x) {
                $numTranslatable = 0;
            }
            if ($numTranslatable === 0) {
                $numApprovedTranslations = 0;
            } else {
                try {
                    $numApprovedTranslations = (int) $app->make(TranslationRepository::class)
                    ->createQueryBuilder('t')
                        ->select('count(t.id)')
                        ->where('t.locale = :locale')->setParameter('locale', $locale)
                        ->andWhere('t.current = 1')
                        ->getQuery()->getSingleScalarResult();
                } catch (NoResultException $x) {
                    $numApprovedTranslations = 0;
                }
            }
            $result = LocaleStatsEntity::create($locale, $numTranslatable, $numApprovedTranslations);
            $em->persist($result);
            $em->flush($result);
        }

        return $result;
    }

    /**
     * Clear the stats for a specific locale.
     *
     * @para LocaleEntity $locale
     */
    public function resetForLocale(LocaleEntity $locale)
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.locale = :locale')->setParameter('locale', $locale)
            ->getQuery()->execute();
    }

    /**
     * Clear the stats for all the locales.
     */
    public function resetAll()
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->getQuery()->execute();
    }
}
