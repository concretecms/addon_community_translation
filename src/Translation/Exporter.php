<?php
namespace CommunityTranslation\Translation;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\UserException;
use Concrete\Core\Application\Application;
use Concrete\Core\Database\Driver\PDOStatement;
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
     * @param LocaleEntity $locale
     *
     * @return Translations
     */
    public function fromPot(Translations $pot, LocaleEntity $locale)
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
     * @param LocaleEntity $locale
     * @param bool $withPlaces
     * @param bool $excludeUntranslatedStrings
     *
     * @return string
     */
    protected function getBaseSelectString(LocaleEntity $locale, $withPlaces, $excludeUntranslatedStrings)
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
                CommunityTranslationTranslatablePlaces.locations,
                CommunityTranslationTranslatablePlaces.comments,
            ';
        } else {
            $result .= "
                'a:0:{}' as locations,
                'a:0:{}' as comments,
            ";
        }
        $result .= '
                CommunityTranslationTranslations.approved,
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
                inner join CommunityTranslationTranslatablePlaces on CommunityTranslationTranslatables.id = CommunityTranslationTranslatablePlaces.translatable
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
     * @param LocaleEntity $locale
     *
     * @return PDOStatement
     */
    public function getUnreviewedSelectQuery(LocaleEntity $locale)
    {
        $cn = $this->app->make(EntityManager::class)->getConnection();
        $queryLocaleID = $cn->quote($locale->getID());

        return $cn->executeQuery(
            $this->getBaseSelectString($locale, false, false) .
            " inner join
                (
                    select distinct
                        translatable
                    from
                        CommunityTranslationTranslations
                    where
                        locale = $queryLocaleID
                        and
                        (
                            (approved is null)
                            or
                            (current = 1 and approved = 0)
                        )
                ) as tNR on CommunityTranslationTranslatables.id = tNR.translatable
            order by
                CommunityTranslationTranslatables.text
            "
        );
    }

    /**
     * Get recordset of the translations for a specific package, version and locale.
     *
     * @param PackageVersionEntity $packageVersion
     * @param LocaleEntity $locale
     *
     * @return PDOStatement
     */
    public function getPackageSelectQuery(PackageVersionEntity $packageVersion, LocaleEntity $locale, $excludeUntranslatedStrings = false)
    {
        $cn = $this->app->make(EntityManager::class)->getConnection();
        $queryPackageVersionID = (int) $packageVersion->getID();

        return $cn->executeQuery(
            $this->getBaseSelectString($locale, true, $excludeUntranslatedStrings) .
            "
                where
                    CommunityTranslationTranslatablePlaces.packageVersion = $queryPackageVersionID
                order by
                    CommunityTranslationTranslatablePlaces.sort
            "
        );
    }

    /**
     * Get the translations for a specific package, version and locale.
     *
     * @param PackageVersionEntity|array $packageOrHandleVersion The package version for which you want the translations (a Package\Version entity instance of an array with handle and version)
     * @param LocaleEntity $locale the locale that you want
     * @param bool $excludeUntranslatedStrings set to true to filter out untranslated strings
     *
     * @return Translations
     */
    public function forPackage($packageOrHandleVersion, LocaleEntity $locale, $excludeUntranslatedStrings = false)
    {
        if ($packageOrHandleVersion instanceof PackageVersionEntity) {
            $packageVersion = $packageOrHandleVersion;
        } elseif (is_array($packageOrHandleVersion) && isset($packageOrHandleVersion['handle']) && isset($packageOrHandleVersion['version'])) {
            $packageVersion = $this->app->make(PackageVersionRepository::class)->findOneBy([
                'handle' => $packageOrHandleVersion['handle'],
                'version' => $packageOrHandleVersion['version'],
            ]);
        } elseif (is_array($packageOrHandleVersion) && isset($packageOrHandleVersion[0]) && isset($packageOrHandleVersion[1])) {
            $packageVersion = $this->app->make(PackageVersionRepository::class)->findOneBy([
                'handle' => $packageOrHandleVersion[0],
                'version' => $packageOrHandleVersion[1],
            ]);
        } else {
            $packageVersion = null;
        }
        if ($packageVersion === null) {
            throw new UserException(t('Invalid translated package version specified'));
        }
        $rs = $this->getPackageSelectQuery($packageVersion, $locale, $excludeUntranslatedStrings);

        $result = $this->buildTranslations($locale, $rs);

        $result->setHeader('Project-Id-Version', $packageVersion->getPackage()->getHandle() . ' ' . $packageVersion->getVersion());

        return $result;
    }

    /**
     * Get the unreviewed translations for a locale.
     *
     * @param LocaleEntity $locale the locale that you want
     * @param bool $excludeUntranslatedStrings set to true to filter out untranslated strings
     *
     * @return Translations
     */
    public function unreviewed(LocaleEntity $locale)
    {
        $rs = $this->getUnreviewedSelectQuery($locale);

        $result = $this->buildTranslations($locale, $rs);

        $result->setHeader('Project-Id-Version', 'unreviewed');

        return $result;
    }

    /**
     * @param LocaleEntity $locale
     * @param PDOStatement $rs
     *
     * @return Translations
     */
    protected function buildTranslations(LocaleEntity $locale, PDOStatement $rs)
    {
        $translations = new Translations();
        $translations->setLanguage($locale->getID());
        $numPlurals = $locale->getPluralCount();
        while (($row = $rs->fetch()) !== false) {
            $translation = new \Gettext\Translation($row['context'], $row['text'], $row['plural']);
            foreach (unserialize($row['locations']) as $location) {
                $translation->addReference($location);
            }
            foreach (unserialize($row['comments']) as $comment) {
                $translation->addExtractedComment($comment);
            }
            if ($row['text0'] !== null) {
                if (!$row['approved']) {
                    $translation->addFlag('fuzzy');
                }
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

    /**
     * Does a locale have translation strings that needs review?
     *
     * @param LocaleEntity $locale
     *
     * @return bool
     */
    public function localeHasPendingApprovals(LocaleEntity $locale)
    {
        $cn = $this->app->make(EntityManager::class)->getConnection();
        $rs = $cn->executeQuery(
            '
                select
                    CommunityTranslationTranslations.id
                from
                    CommunityTranslationTranslations
                    inner join CommunityTranslationTranslatablePlaces on CommunityTranslationTranslations.translatable = CommunityTranslationTranslatablePlaces.translatable
                where
                    CommunityTranslationTranslations.locale = ?
                    and (
                        (CommunityTranslationTranslations.approved is null)
                        or
                        (CommunityTranslationTranslations.current = 1 and CommunityTranslationTranslations.approved = 0)
                    )
                limit 1
            ',
            [
                $locale->getID(),
            ]
        );
        $result = $rs->fetchColumn() !== false;
        $rs->closeCursor();

        return $result;
    }
}
