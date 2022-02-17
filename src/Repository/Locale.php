<?php

declare(strict_types=1);

namespace CommunityTranslation\Repository;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use Doctrine\ORM\EntityRepository;
use Punic\Comparer;

defined('C5_EXECUTE') or die('Access Denied.');

class Locale extends EntityRepository
{
    /**
     * Search an approved locale given its ID (excluding the source one).
     */
    public function findApproved(string $localeID): ?LocaleEntity
    {
        if ($localeID === '') {
            return null;
        }
        $locale = $this->find($localeID);

        return $locale !== null && $locale->isApproved() && !$locale->isSource() ? $locale : null;
    }

    /**
     * Get the list of the approved locales (excluding the source one).
     *
     * @return \CommunityTranslation\Entity\Locale[]
     */
    public function getApprovedLocales(): array
    {
        $locales = $this->findBy(['isSource' => null, 'isApproved' => true]);
        $comparer = new Comparer();
        usort(
            $locales,
            static function (LocaleEntity $a, LocaleEntity $b) use ($comparer): int {
                return $comparer->compare($a->getDisplayName(), $b->getDisplayName());
            }
        );

        return $locales;
    }
}
