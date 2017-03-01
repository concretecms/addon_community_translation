<?php
namespace CommunityTranslation\Repository;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use Doctrine\ORM\EntityRepository;
use Punic\Comparer;

class Locale extends EntityRepository
{
    /**
     * Search an approved locale given its ID (excluding the source one).
     *
     * @return LocaleEntity|null
     */
    public function findApproved($localeID)
    {
        $result = null;
        if (is_string($localeID) && $localeID !== '') {
            $l = $this->find($localeID);
            if ($l !== null && $l->isApproved() && !$l->isSource()) {
                $result = $l;
            }
        }

        return $result;
    }

    /**
     * Get the list of the approved locales (excluding the source one).
     *
     * @return LocaleEntity[]
     */
    public function getApprovedLocales()
    {
        $locales = $this->findBy(['isSource' => null, 'isApproved' => true]);
        $comparer = new Comparer();
        usort($locales, function (LocaleEntity $a, LocaleEntity $b) use ($comparer) {
            return $comparer->compare($a->getDisplayName(), $b->getDisplayName());
        });

        return $locales;
    }
}
