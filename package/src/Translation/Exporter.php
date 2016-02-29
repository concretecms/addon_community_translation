<?php
namespace Concrete\Package\CommunityTranslation\Src\Translation;

use Concrete\Core\Application\Application;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Concrete\Package\CommunityTranslation\Src\Package\Package;
use Concrete\Package\CommunityTranslation\Src\Exception;

class Exporter implements \Concrete\Core\Application\ApplicationAwareInterface
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
     * Fill in the translations for a specific locale.
     *
     * @param \Gettext\Translations $translations
     * @param Locale $locale
     *
     * @return Locale
     */
    public function fromPot(\Gettext\Translations $translations, Locale $locale)
    {
        $po = clone $translations;
    }

    /**
     * Get the the translations for a specific package, version and locale.
     *
     * @param Package|array $packageOrHandleVersion The package for which you want the translations (a Package instance of an array with handle and version)
     * @param unknown $version The package version ('dev-...' for core development branches)
     * @param Locale $locale The locale that you want.
     * @param bool $excludeUntranslatedStrings Set to true to filter out untranslated strings.
     *
     * @return \Gettext\Translations
     */
    public function forPackage($packageOrHandleVersion, Locale $locale, $excludeUntranslatedStrings = false)
    {
        if ($packageOrHandleVersion instanceof Package) {
            $package = $packageOrHandleVersion;
        } elseif (is_array($packageOrHandleVersion) && isset($packageOrHandleVersion['handle']) && isset($packageOrHandleVersion['version'])) {
            $package = $this->app->make('community_translation/package')->findOneBy(array(
                'pHandle' => $packageOrHandleVersion['handle'],
                'pVersion' => $packageOrHandleVersion['version'],
            ));
        } elseif (is_array($packageOrHandleVersion) && isset($packageOrHandleVersion[0]) && isset($packageOrHandleVersion[1])) {
            $package = $this->app->make('community_translation/package')->findOneBy(array(
                'pHandle' => $packageOrHandleVersion[0],
                'pVersion' => $packageOrHandleVersion[1],
            ));
        } else {
            $package = null;
        }
        if ($package === null) {
            throw new Exception(t('Invalid translated package specified'));
        }
        $em = $this->app->make('community_translation/em');
        /* @var \Doctrine\ORM\EntityManager $em */
        $qb = $em->createQueryBuilder();
        if ($excludeUntranslatedStrings) {
            $join = 'innerJoin';
            $where = 'r.tLocale = :locale';
        } else {
            $join = 'leftJoin';
            $where = $qb->expr()->orX(
                $qb->expr()->eq('r.tLocale', ':locale'),
                $qb->expr()->isNull('r.tLocale')
            );
        }
        $qb
            ->select(array('t', 'p', 'r'))
            ->from('Concrete\Package\CommunityTranslation\Src\Translatable\Translatable', 't')
            ->innerJoin('Concrete\Package\CommunityTranslation\Src\Translatable\Place\Place', 'p', \Doctrine\ORM\Query\Expr\Join::WITH, 't.tID = p.tpTranslatable')
            ->$join('Concrete\Package\CommunityTranslation\Src\Translation\Translation', 'r', \Doctrine\ORM\Query\Expr\Join::WITH, 't.tID = r.tTranslatable AND 1 = r.tCurrent')
            ->where('p.tpPackage = :package')
            ->andWhere($where)
            ->orderBy('p.tpSort')
            ->setParameter('package', $package)
            ->setParameter('locale', $locale)
        ;
        $q = $qb->getQuery();
        $list = $q->getResult();
        $translations = new \Gettext\Translations();
        $translations->setLanguage($locale->getID());
        $numPlurals = $locale->getPluralCount();
        while (!empty($list)) {
            $translatable = array_shift($list);
            $translation = new \Gettext\Translation($translatable->getContext(), $translatable->getText(), $translatable->getPlural());
            $place = array_shift($list);
            foreach ($place->getLocations() as $location) {
                $translation->addReference($location);
            }
            foreach ($place->getComments() as $comment) {
                $translation->addExtractedComment($comment);
            }
            $translated = array_shift($list);
            if ($translated !== null) {
                /* @var \Concrete\Package\CommunityTranslation\Src\Translation\Translation $translated */
                $translation->setTranslation($translated->getText0());
                if ($translation->hasPlural()) {
                    switch ($numPlurals) {
                        case 6:
                            $translation->setPluralTranslation($translated->getText5(), 4);
                            /* @noinspection PhpMissingBreakStatementInspection */
                        case 5:
                            $translation->setPluralTranslation($translated->getText4(), 3);
                            /* @noinspection PhpMissingBreakStatementInspection */
                        case 4:
                            $translation->setPluralTranslation($translated->getText3(), 2);
                            /* @noinspection PhpMissingBreakStatementInspection */
                        case 3:
                            $translation->setPluralTranslation($translated->getText2(), 1);
                            /* @noinspection PhpMissingBreakStatementInspection */
                        case 2:
                            $translation->setPluralTranslation($translated->getText1(), 0);
                            /* @noinspection PhpMissingBreakStatementInspection */
                            break;
                    }
                }
            }
            $translations->append($translation);
        }

        return $translations;
    }
}
