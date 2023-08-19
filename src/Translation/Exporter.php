<?php

declare(strict_types=1);

namespace CommunityTranslation\Translation;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\Translatable as TranslatableEntity;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use Concrete\Core\Error\UserMessageException;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Gettext\Translation;
use Gettext\Translations;

defined('C5_EXECUTE') or die('Access Denied.');

class Exporter
{
    private EntityManagerInterface $entityManager;

    private PackageRepository $packageRepository;

    private PackageVersionRepository $packageVersionRepository;

    public function __construct(EntityManagerInterface $entityManager, PackageRepository $packageRepository, PackageVersionRepository $packageVersionRepository)
    {
        $this->entityManager = $entityManager;
        $this->packageRepository = $packageRepository;
        $this->packageVersionRepository = $packageVersionRepository;
    }

    /**
     * Fill in the translations for a specific locale.
     */
    public function fromPot(Translations $pot, LocaleEntity $locale): Translations
    {
        $cn = $this->entityManager->getConnection();
        $po = clone $pot;
        $po->setLanguage($locale->getID());
        $po->setPluralForms($locale->getPluralCount(), $locale->getPluralFormula());
        $numPlurals = $locale->getPluralCount();
        $quotedLocaleID = $cn->quote($locale->getID());
        $searchQuery = $cn->prepare(
            <<<EOT
SELECT
    CommunityTranslationTranslations.*
FROM
    CommunityTranslationTranslatables
    INNER JOIN CommunityTranslationTranslations ON CommunityTranslationTranslatables.id = CommunityTranslationTranslations.translatable
WHERE
    CommunityTranslationTranslations.locale = {$quotedLocaleID}
    AND CommunityTranslationTranslations.current = 1
    AND CommunityTranslationTranslatables.hash = ?
LIMIT 1
EOT
        );
        $searchStatement = $searchQuery->getWrappedStatement();
        foreach ($po as $translationKey => $translation) {
            /** @var \Gettext\Translation $translation */
            $plural = $translation->getPlural();
            $hash = TranslatableEntity::generateHashFromGettextKey($translationKey, $plural);
            $searchStatement->execute([$hash]);
            $row = $searchStatement->fetchAssociative();
            if ($row === false) {
                continue;
            }
            $translation->setTranslation($row['text0']);
            if ($plural !== '') {
                switch ($numPlurals) {
                    case 6:
                        $translation->setPluralTranslation($row['text5'], 4);
                        // no break
                    case 5:
                        $translation->setPluralTranslation($row['text4'], 3);
                        // no break
                    case 4:
                        $translation->setPluralTranslation($row['text3'], 2);
                        // no break
                    case 3:
                        $translation->setPluralTranslation($row['text2'], 1);
                        // no break
                    case 2:
                        $translation->setPluralTranslation($row['text1'], 0);
                        break;
                }
            }
        }

        return $po;
    }

    /**
     * Get the recordset of all the translations of a locale that needs to be reviewed.
     */
    public function getUnreviewedSelectQuery(LocaleEntity $locale): Result
    {
        $cn = $this->entityManager->getConnection();
        $quotedLocaleID = $cn->quote($locale->getID());
        $baseSelectString = $this->getBaseSelectString($locale, false, false);

        return $cn->executeQuery(
            <<<EOT
{$baseSelectString}
    INNER JOIN (
        SELECT DISTINCT
            translatable
        FROM
            CommunityTranslationTranslations
        WHERE
            locale = {$quotedLocaleID}
            AND (
                approved IS NULL
                OR (current = 1 and approved = 0)
            )
    ) AS tNR ON CommunityTranslationTranslatables.id = tNR.translatable
ORDER BY
    CommunityTranslationTranslatables.text
EOT
        );
    }

    /**
     * Get recordset of the translations for a specific package, version and locale.
     */
    public function getPackageSelectQuery(PackageVersionEntity $packageVersion, LocaleEntity $locale, bool $excludeUntranslatedStrings = false): Result
    {
        $cn = $this->entityManager->getConnection();
        $queryPackageVersionID = (int) $packageVersion->getID();
        $baseSelectString = $this->getBaseSelectString($locale, true, $excludeUntranslatedStrings);

        return $cn->executeQuery(
            <<<EOT
{$baseSelectString}
WHERE
    CommunityTranslationTranslatablePlaces.packageVersion = {$queryPackageVersionID}
ORDER BY
    CommunityTranslationTranslatablePlaces.sort
EOT
        );
    }

    /**
     * Get the translations for a specific package, version and locale.
     *
     * @param \CommunityTranslation\Entity\Package\Version|array $packageOrHandleVersion The package version for which you want the translations (a Package\Version entity instance of an array with handle and version)
     * @param bool $excludeUntranslatedStrings set to true to filter out untranslated strings
     */
    public function forPackage($packageOrHandleVersion, LocaleEntity $locale, bool $excludeUntranslatedStrings = false): Translations
    {
        if ($packageOrHandleVersion instanceof PackageVersionEntity) {
            $packageVersion = $packageOrHandleVersion;
        } elseif (is_array($packageOrHandleVersion) && isset($packageOrHandleVersion['handle'], $packageOrHandleVersion['version'])) {
            $package = $this->packageRepository->getByHandle($packageOrHandleVersion['handle']);
            $packageVersion = $package === null ? null : $this->packageVersionRepository->findOneBy([
                'package' => $package,
                'version' => $packageOrHandleVersion['version'],
            ]);
        } elseif (is_array($packageOrHandleVersion) && isset($packageOrHandleVersion[0], $packageOrHandleVersion[1])) {
            $package = $this->packageRepository->getByHandle($packageOrHandleVersion[0]);
            $packageVersion = $package === null ? null : $this->packageVersionRepository->findOneBy([
                'package' => $package,
                'version' => $packageOrHandleVersion[1],
            ]);
        } else {
            $packageVersion = null;
        }
        if ($packageVersion === null) {
            throw new UserMessageException(t('Invalid translated package version specified'));
        }
        $rs = $this->getPackageSelectQuery($packageVersion, $locale, $excludeUntranslatedStrings);
        $result = $this->buildTranslations($locale, $rs);
        $result->setHeader('Project-Id-Version', "{$packageVersion->getPackage()->getHandle()} {$packageVersion->getVersion()}");

        return $result;
    }

    /**
     * Get the unreviewed translations for a locale.
     */
    public function unreviewed(LocaleEntity $locale): Translations
    {
        $rs = $this->getUnreviewedSelectQuery($locale);
        $result = $this->buildTranslations($locale, $rs);
        $result->setHeader('Project-Id-Version', 'unreviewed');

        return $result;
    }

    /**
     * Does a locale have translation strings that needs review?
     */
    public function localeHasPendingApprovals(LocaleEntity $locale): bool
    {
        $cn = $this->entityManager->getConnection();
        $rs = $cn->executeQuery(
            <<<'EOT'
SELECT
    CommunityTranslationTranslations.id
FROM
    CommunityTranslationTranslations
    INNER JOIN CommunityTranslationTranslatablePlaces ON CommunityTranslationTranslations.translatable = CommunityTranslationTranslatablePlaces.translatable
WHERE
    CommunityTranslationTranslations.locale = ?
    AND (
        CommunityTranslationTranslations.approved IS NULL
        OR (CommunityTranslationTranslations.current = 1 AND CommunityTranslationTranslations.approved = 0)
    )
LIMIT 1
EOT
            ,
            [
                $locale->getID(),
            ]
        );

        return $rs->fetchOne() !== false;
    }

    /**
     * Builds the base select query string to retrieve some translatable/translated strings.
     */
    private function getBaseSelectString(LocaleEntity $locale, bool $withPlaces, bool $excludeUntranslatedStrings): string
    {
        $cn = $this->entityManager->getConnection();
        $quotedLocaleID = $cn->quote($locale->getID());

        $result = <<<'EOT'
SELECT
    CommunityTranslationTranslatables.id,
    CommunityTranslationTranslatables.context,
    CommunityTranslationTranslatables.text,
    CommunityTranslationTranslatables.plural,

EOT
        ;
        if ($withPlaces) {
            $result .= <<<'EOT'
    CommunityTranslationTranslatablePlaces.locations,
    CommunityTranslationTranslatablePlaces.comments,

EOT
            ;
        } else {
            $emptySerializedArray = $cn->quote(serialize([]));
            $result .= <<<EOT
    {$emptySerializedArray} as locations,
    {$emptySerializedArray} as comments,

EOT
            ;
        }
        $result .= <<<'EOT'
    CommunityTranslationTranslations.approved,
    CommunityTranslationTranslations.text0,
    CommunityTranslationTranslations.text1,
    CommunityTranslationTranslations.text2,
    CommunityTranslationTranslations.text3,
    CommunityTranslationTranslations.text4,
    CommunityTranslationTranslations.text5
FROM
    CommunityTranslationTranslatables

EOT
        ;
        if ($withPlaces) {
            $result .= <<<'EOT'
    INNER JOIN CommunityTranslationTranslatablePlaces ON CommunityTranslationTranslatables.id = CommunityTranslationTranslatablePlaces.translatable

EOT
            ;
        }
        $joinHow = $excludeUntranslatedStrings ? 'INNER' : 'LEFT';
        $result .= <<<EOT
    {$joinHow} JOIN CommunityTranslationTranslations
        ON CommunityTranslationTranslatables.id = CommunityTranslationTranslations.translatable AND
        1 = CommunityTranslationTranslations.current
        AND {$quotedLocaleID} = CommunityTranslationTranslations.locale
EOT
        ;

        return $result;
    }

    private function buildTranslations(LocaleEntity $locale, Result $rs): Translations
    {
        $translations = new Translations();
        $translations->setLanguage($locale->getID());
        $translations->setPluralForms($locale->getPluralCount(), $locale->getPluralFormula());
        $numPlurals = $locale->getPluralCount();
        while (($row = $rs->fetchAssociative()) !== false) {
            $translation = new Translation($row['context'], $row['text'], $row['plural']);
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
                            // no break
                        case 5:
                            $translation->setPluralTranslation($row['text4'], 3);
                            // no break
                        case 4:
                            $translation->setPluralTranslation($row['text3'], 2);
                            // no break
                        case 3:
                            $translation->setPluralTranslation($row['text2'], 1);
                            // no break
                        case 2:
                            $translation->setPluralTranslation($row['text1'], 0);
                            break;
                    }
                }
            }
            $translations->append($translation);
        }

        return $translations;
    }
}
