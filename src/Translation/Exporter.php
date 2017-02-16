<?php
namespace CommunityTranslation\Translation;

use CommunityTranslation\Entity\Locale;
use CommunityTranslation\Entity\Package;
use CommunityTranslation\Entity\Translation;
use CommunityTranslation\UserException;
use Concrete\Core\Application\Application;
use Doctrine\ORM\EntityManager;
use Gettext\Translations;

class Exporter
{
    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Fill in the translations for a specific locale.
     *
     * @param Translations $translations
     * @param Locale $locale
     *
     * @return Translations
     */
    public function fromPot(Translations $pot, Locale $locale)
    {
        $cn = $this->app->make(EntityManager::class)->getConnection();
        $po = clone $pot;
        $po->setLanguage($locale->getID());
        $numPlurals = $locale->getPluralCount();
        $searchQuery = $cn->prepare('
            select
                CommunityTranslationTranslations.*
            from
                CommunityTranslationTranslatables
                inner join CommunityTranslationTranslations on CommunityTranslationTranslatables.id = CommunityTranslationTranslations.translatable
            where
                CommunityTranslationTranslations.locale = ' . $cn->quote($locale->getID()) . '
                and CommunityTranslationTranslations.current = 1
                and CommunityTranslationTranslatables.hash = ?
            limit 1
        ')->getWrappedStatement();
        foreach ($po as $translationKey => $translation) {
            $plural = $translation->getPlural();
            $hash = md5(($plural === '') ? $translationKey : "$translationKey\005$plural");
            $searchQuery->execute([$hash]);
            $row = $searchQuery->fetch();
            $searchQuery->closeCursor();
            if ($row !== false) {
                $translation->setTranslation($row['text0']);
                if ($plural !== '') {
                    switch ($numPlurals) {
                        case 6:
                            $translation->setPluralTranslation($row['text5'], 4);
                            /* @noinspection PhpMissingBreakStatementInspection */
                        case 5:
                            $translation->setPluralTranslation($row['text4'], 3);
                            /* @noinspection PhpMissingBreakStatementInspection */
                        case 4:
                            $translation->setPluralTranslation($row['text3'], 2);
                            /* @noinspection PhpMissingBreakStatementInspection */
                        case 3:
                            $translation->setPluralTranslation($row['text2'], 1);
                            /* @noinspection PhpMissingBreakStatementInspection */
                        case 2:
                            $translation->setPluralTranslation($row['text1'], 0);
                            /* @noinspection PhpMissingBreakStatementInspection */
                            break;
                    }
                }
            }
        }

        return $po;
    }

    /**
     * Builds the base select query string to retrieve some translatable/translated strings.
     *
     * @param Locale $locale
     * @param bool $withPlaces
     * @param bool $excludeUntranslatedStrings
     *
     * @return string
     */
    protected function getBaseSelectString(Locale $locale, $withPlaces, $excludeUntranslatedStrings)
    {
        $cn = $this->app->make(EntityManager::class)->getConnection();
        $queryLocaleID = $cn->quote($locale->getID());

        $result = '
            select
                CommunityTranslationTranslatables.id,
                CommunityTranslationTranslatables.context,
                CommunityTranslationTranslatables.text,
                CommunityTranslationTranslatables.plural,
        ';
        if ($withPlaces) {
            $result .= '
                TranslatablePlaces.locations,
                TranslatablePlaces.comments,
            ';
        } else {
            $result .= "
                'a:0:{}' as locations,
                'a:0:{}' as comments,
            ";
        }
        $result .= '
                CommunityTranslationTranslations.status,
                CommunityTranslationTranslations.text0,
                CommunityTranslationTranslations.text1,
                CommunityTranslationTranslations.text2,
                CommunityTranslationTranslations.text3,
                CommunityTranslationTranslations.text4,
                CommunityTranslationTranslations.text5
            from
                CommunityTranslationTranslatables
        ';
        if ($withPlaces) {
            $result .= '
                inner join TranslatablePlaces on CommunityTranslationTranslatables.id = TranslatablePlaces.translatable
            ';
        }
        $result .=
            ($excludeUntranslatedStrings ? 'inner join' : 'left join')
            . "
                CommunityTranslationTranslations on CommunityTranslationTranslatables.id = CommunityTranslationTranslations.translatable and 1 = CommunityTranslationTranslations.current and $queryLocaleID = CommunityTranslationTranslations.locale
            "
        ;

        return $result;
    }

    /**
     * Get the recordset of all the translations of a locale that needs to be reviewed.
     *
     * @param Locale $locale
     *
     * @return \Concrete\Core\Database\Driver\PDOStatement
     */
    public function getUnreviewedSelectQuery(Locale $locale)
    {
        $cn = $this->app->make('community_translation/em')->getConnection();
        $queryLocaleID = $cn->quote($locale->getID());

        return $cn->executeQuery(
            $this->getBaseSelectString($locale, false, false) .
            ' inner join
                (
                    select distinct translatable from CommunityTranslationTranslations where current is null and status = ' . Translation::STATUS_PENDINGAPPROVAL . " and locale = $queryLocaleID
                ) as tNR on CommunityTranslationTranslatables.id = tNR.translatable
            order by
                CommunityTranslationTranslatables.text
            "
        );
    }

    /**
     * Get recordset of the translations for a specific package, version and locale.
     *
     * @param Package $package
     * @param Locale $locale
     *
     * @return \Concrete\Core\Database\Driver\PDOStatement
     */
    public function getPackageSelectQuery(Package $package, Locale $locale, $excludeUntranslatedStrings = false)
    {
        $cn = $this->app->make('community_translation/em')->getConnection();
        $queryPackageID = (int) $package->getID();

        return $cn->executeQuery(
            $this->getBaseSelectString($locale, true, $excludeUntranslatedStrings) .
            "
                where
                    TranslatablePlaces.package = $queryPackageID
                order by
                    TranslatablePlaces.sort
            "
        );
    }

    /**
     * Get the translations for a specific package, version and locale.
     *
     * @param Package|array $packageOrHandleVersion The package for which you want the translations (a Package instance of an array with handle and version)
     * @param Locale $locale the locale that you want
     * @param bool $excludeUntranslatedStrings set to true to filter out untranslated strings
     *
     * @return Translations
     */
    public function forPackage($packageOrHandleVersion, Locale $locale, $excludeUntranslatedStrings = false)
    {
        if ($packageOrHandleVersion instanceof Package) {
            $package = $packageOrHandleVersion;
        } elseif (is_array($packageOrHandleVersion) && isset($packageOrHandleVersion['handle']) && isset($packageOrHandleVersion['version'])) {
            $package = $this->app->make('community_translation/package')->findOneBy([
                'pHandle' => $packageOrHandleVersion['handle'],
                'pVersion' => $packageOrHandleVersion['version'],
            ]);
        } elseif (is_array($packageOrHandleVersion) && isset($packageOrHandleVersion[0]) && isset($packageOrHandleVersion[1])) {
            $package = $this->app->make('community_translation/package')->findOneBy([
                'pHandle' => $packageOrHandleVersion[0],
                'pVersion' => $packageOrHandleVersion[1],
            ]);
        } else {
            $package = null;
        }
        if ($package === null) {
            throw new UserException(t('Invalid translated package specified'));
        }
        $rs = $this->getPackageSelectQuery($package, $locale, $excludeUntranslatedStrings);
        $translations = new Translations();
        $translations->setLanguage($locale->getID());
        $numPlurals = $locale->getPluralCount();
        while (($row = $rs->fetch()) !== false) {
            $translation = new \Gettext\Translation($row['context'], $row['text'], $row['plural']);
            if ((int) $row['status'] < Translation::STATUS_APPROVED) {
                $translation->addFlag('fuzzy');
            }
            foreach (unserialize($row['locations']) as $location) {
                $translation->addReference($location);
            }
            foreach (unserialize($row['comments']) as $comment) {
                $translation->addExtractedComment($comment);
            }
            if ($row['text0'] !== null) {
                $translation->setTranslation($row['text0']);
                if ($translation->hasPlural()) {
                    switch ($numPlurals) {
                        case 6:
                            $translation->setPluralTranslation($row['text5'], 4);
                            /* @noinspection PhpMissingBreakStatementInspection */
                        case 5:
                            $translation->setPluralTranslation($row['text4'], 3);
                            /* @noinspection PhpMissingBreakStatementInspection */
                        case 4:
                            $translation->setPluralTranslation($row['text3'], 2);
                            /* @noinspection PhpMissingBreakStatementInspection */
                        case 3:
                            $translation->setPluralTranslation($row['text2'], 1);
                            /* @noinspection PhpMissingBreakStatementInspection */
                        case 2:
                            $translation->setPluralTranslation($row['text1'], 0);
                            /* @noinspection PhpMissingBreakStatementInspection */
                            break;
                    }
                }
            }
            $translations->append($translation);
        }
        $rs->closeCursor();

        return $translations;
    }
}
