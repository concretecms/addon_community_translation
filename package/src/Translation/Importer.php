<?php
namespace Concrete\Package\CommunityTranslation\Src\Translation;

use Concrete\Core\Application\Application;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Package\CommunityTranslation\Src\Exception;
use Concrete\Package\CommunityTranslation\Src\Service\Access;

class Importer implements \Concrete\Core\Application\ApplicationAwareInterface
{
    const IMPORT_BATCH_SIZE = 50;

    /**
     * The database connection.
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Get the database connection.
     *
     * @return  Connection
     */
    protected function getConnection()
    {
        if ($this->connection === null) {
            $this->connection = \Core::make('community_translation/em')->getConnection();
        }

        return $this->connection;
    }

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
     * Initializes the instance.
     *
     * @param Connection $connection
     */
    public function __construct()
    {
        $this->connection = null;
    }

    /**
     * Import the translated strings for a specific locale.
     *
     * @param \Gettext\Translations $translations
     * @param \Concrete\Package\CommunityTranslation\Src\Locale\Locale|string $locale
     * @param bool|null $reviewed
     *
     * @throws Exception
     */
    public function import(\Gettext\Translations $translations, $locale, $reviewed = null)
    {
        // Check locale
        if (!$locale instanceof \Concrete\Package\CommunityTranslation\Src\Locale\Locale) {
            $l = $this->app->make('community_translation/locale')->find($locale);
            if ($l === null) {
                throw new Exception(t('Invalid locale identifier: %s', $locale));
            }
            $locale = $l;
        }
        if ($locale->isSource()) {
            throw new Exception(t("The locale '%s' is the source one.", $locale->getDisplayName()));
        }
        if (!$locale->isApproved()) {
            throw new Exception(t("The locale '%s' is not approved.", $locale->getDisplayName()));
        }
        // Check $reviewed
        if (isset($reviewed)) {
            $reviewed = $reviewed ? 1 : 0;
        } else {
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            if ($access < Access::TRANSLATE) {
                throw new Exception(t("No access for the locale '%s'.", $locale->getDisplayName()));
            }
            $reviewed = ($access >= Access::ADMIN) ? 1 : 0;
        }
        // Some vars
        $me = new \User();
        $userID = (int) ($me->isRegistered() ? $me->getUserID() : USER_SUPER_ID);
        $pluralCount = $locale->getPluralCount();
        // Start working - This a bit hacky but it's lightning fast
        $connection = $this->getConnection();
        $connection->beginTransaction();
        try {
            // Prepare some queries
            $searchQuery = $connection->prepare('
                select
                    Translatables.tID as translatableID,
                    Translations.*
                from
                    Translatables
                    left join Translations on Translatables.tID = Translations.tTranslatable and '.$connection->quote($locale->getID()).' = Translations.tLocale
                where
                    Translatables.tHash = ?
            ')->getWrappedStatement();
            /* @var \Doctrine\DBAL\Driver\Statement $searchQuery */
            $insertQueryFields = 'tCreatedOn, tCreatedBy, tLocale, tCurrent, tReviewed, tTranslatable, tText0, tText1, tText2, tText3, tText4, tText5';
            $insertQueryChunk = ' ('.implode(', ', array(
                $connection->getDatabasePlatform()->getNowExpression(),
                $userID,
                $connection->quote($locale->getID()),
                '?', // tCurrent
                '?', // tReviewed
                '?', // tTranslatable
                '?', '?, ?, ?, ?, ?', // tText0... tText5
            )).'),';
            $insertQuery = $connection->prepare(
                'INSERT INTO Translations ('.$insertQueryFields.') VALUES '
                .rtrim(str_repeat($insertQueryChunk, self::IMPORT_BATCH_SIZE), ',')
            )->getWrappedStatement();
            $insertParams = array();
            $insertCount = 0;
            $updateTranslationQuery = $connection->prepare(
                'UPDATE Translations SET tCurrent = ?, tReviewed = ? WHERE tID = ? LIMIT 1'
            )->getWrappedStatement();
            /* @var \Doctrine\DBAL\Driver\Statement $updateTranslationQuery */

            // Check every strings to be imported
            foreach ($translations as $translationKey => $translation) {
                /* @var \Gettext\Translation $translation */
                if (!$translation->hasTranslation()) {
                    // This $translation instance is not translated
                    continue;
                }
                $isPlural = $translation->hasPlural();
                if ($isPlural && $pluralCount > 1 && !$translation->hasPluralTranslation()) {
                    // This plural form of the $translation instance is not translated
                    continue;
                }
                // Let's look for this translation
                $translatableID = null;
                $currentRow = null;
                $sameRow = null;
                $searchQuery->execute(array(md5($isPlural ? ("$translationKey\005".$translation->getPlural()) : $translationKey)));
                // Read the current translations and look for the current one and determine if we already have this new translation
                while (($row = $searchQuery->fetch()) !== false) {
                    if ($translatableID === null) {
                        $translatableID = (int) $row['translatableID'];
                    }
                    if (!isset($row['tID'])) {
                        break;
                    }
                    if ($currentRow === null && $row['tCurrent']) {
                        $currentRow = $row;
                    }
                    if ($sameRow === null && $this->rowSameAsTranslation($row, $translation, $isPlural, $pluralCount)) {
                        $sameRow = $row;
                    }
                }
                $searchQuery->closeCursor();
                if ($translatableID === null) {
                    // No translatable string for this translation
                    continue;
                }
                if ($sameRow === null) {
                    // This translation is not already present - Let's add it
                    if ($currentRow === null) {
                        // No current translation for this string: add this new one and mark it as the current one
                        $addCurrent = 1;
                        $addReviewed = $reviewed;
                    } elseif ($reviewed || !$currentRow['tReviewed']) {
                        // There's already a current translation for this string, but we'll activate this new one
                        $updateTranslationQuery->execute(array(null, $currentRow['tReviewed'], $currentRow['tID']));
                        $addCurrent = 1;
                        $addReviewed = $reviewed;
                    } else {
                        // Let keep the previously current translation as the current one, but let's add this new one
                        $addCurrent = null;
                        $addReviewed = 0;
                    }
                    // Add the new record to the queue
                    $insertParams[] = $addCurrent;
                    $insertParams[] = $addReviewed;
                    $insertParams[] = $translatableID;
                    $insertParams[] = $translation->getTranslation();
                    for ($p = 1; $p <= 5; ++$p) {
                        $insertParams[] = ($isPlural && $p < $pluralCount) ? $translation->getPluralTranslation($p - 1) : '';
                    }
                    ++$insertCount;
                    if ($insertCount === self::IMPORT_BATCH_SIZE) {
                        $insertQuery->execute($insertParams);
                        $insertParams = array();
                        $insertCount = 0;
                    }
                } elseif ($currentRow === null) {
                    // This translation is already present, but there's no current translation: let's activate it
                    $updateTranslationQuery->execute(array(1, ($reviewed || $sameRow['tReviewed']) ? 1 : 0, $sameRow['tID']));
                } elseif ($sameRow['tCurrent']) {
                    // This translation is already present and it's the current one
                    if ($reviewed && !$sameRow['tReviewed']) {
                        // Let's mark the translation as reviewed
                        $updateTranslationQuery->execute(array(1, 1, $sameRow['tID']));
                    }
                } else {
                    // This translation exists, but we have already another translation that's the current one
                    if ($reviewed || !$currentRow['tReviewed']) {
                        // Let's make the new translation the current one
                        $updateTranslationQuery->execute(array(null, $currentRow['tReviewed'], $currentRow['tID']));
                        $updateTranslationQuery->execute(array(1, $reviewed, $currentRow['tID']));
                    }
                }
            }
            if ($insertCount > 0) {
                $connection->executeQuery(
                    'INSERT INTO Translations ('.$insertQueryFields.') VALUES '.rtrim(str_repeat($insertQueryChunk, $insertCount), ','),
                    $insertParams
                );
            }
            $connection->commit();
        } catch (\Exception $x) {
            try {
                $connection->rollBack();
            } catch (\Exception $foo) {
            }
            throw $x;
        }
    }

    /**
     * Is a database row the same as the translation?
     *
     * @param array $row
     * @param \Gettext\Translation $translation
     * @param bool $isPlural
     * @param int $pluralCount
     *
     * @return bool
     */
    protected function rowSameAsTranslation(array $row, \Gettext\Translation $translation, $isPlural, $pluralCount)
    {
        if ($row['tText0'] !== $translation->getTranslation()) {
            return false;
        }
        if (!$isPlural) {
            return true;
        }
        $same = true;
        switch ($pluralCount) {
            case 6:
                if ($same && $row['tText5'] !== $translation->getPluralTranslation(4)) {
                    $same = false;
                }
                /* @noinspection PhpMissingBreakStatementInspection */
            case 5:
                if ($same && $row['tText4'] !== $translation->getPluralTranslation(3)) {
                    $same = false;
                }
                /* @noinspection PhpMissingBreakStatementInspection */
            case 4:
                if ($same && $row['tText3'] !== $translation->getPluralTranslation(2)) {
                    $same = false;
                }
                /* @noinspection PhpMissingBreakStatementInspection */
            case 3:
                if ($same && $row['tText2'] !== $translation->getPluralTranslation(1)) {
                    $same = false;
                }
                /* @noinspection PhpMissingBreakStatementInspection */
            case 2:
                if ($same && $row['tText1'] !== $translation->getPluralTranslation(0)) {
                    $same = false;
                }
                break;
        }

        return $same;
    }
}
