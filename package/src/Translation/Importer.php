<?php
namespace Concrete\Package\CommunityTranslation\Src\Translation;

use Concrete\Core\Application\Application;
use Concrete\Core\Database\Connection\Connection;
use Exception;

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
        $this->searchQueries = array();
    }

    /**
     * Prepared query to search for existing translations.
     *
     * @var \Concrete\Core\Database\Driver\PDOStatement[]
     */
    protected $searchQueries;

    /**
     * @param string $localeID
     *
     * @return \Concrete\Core\Database\Driver\PDOStatement
     */
    protected function getSearchQuery($localeID)
    {
        if (!isset($this->searchQueries[$localeID])) {
        }

        return $this->searchQueries[$localeID];
    }

    /**
     * Import the translated strings for a specific locale.
     *
     * @param \Gettext\Translations $translations
     * @param \Concrete\Package\CommunityTranslation\Src\Locale\Locale|string $locale
     * @param int|null $status
     *
     * @throws Exception
     */
    public function import(\Gettext\Translations $translations, $locale, $status = null)
    {
        if (!$locale instanceof \Concrete\Package\CommunityTranslation\Src\Locale\Locale) {
            $l = $this->app->make('community_translation/locale')->find($locale);
            if ($l === null) {
                throw new Exception(t('Invalid locale identifier: %s', $locale));
            }
            $locale = $l;
        }
        if (!$locale->isApproved()) {
            throw new Exception(t("The locale '%s' is not approved.", $locale->getName()));
        }
        if (is_numeric($status)) {
            $status = (int) $status;
        } else {
            // Detect by current user
            throw new Exception('@todo');
        }
        $me = new \User();
        $userID = (int) ($me->isRegistered() ? $me->getUserID() : USER_SUPER_ID);
        $statusForNewTranslations = max(1, $status);
        $pluralCount = $locale->getPluralCount();

        $connection = $this->getConnection();
        $connection->beginTransaction();
        try {
            $searchQuery = $connection->prepare('
                select
                    Translatables.tID as translatableID,
                    Translations.*
                from
                    Translatables
                    left join Translations on Translatables.tID = Translations.tTranslatable and '.$connection->quote($locale->getID()).' = Translations.tLocale
                where
                    Translatables.tHash = ?
                limit 1
            ')->getWrappedStatement();
            $insertQueryChunk = ' ('.implode(', ', array(
                $connection->getDatabasePlatform()->getNowExpression(),
                $userID,
                $connection->quote($locale->getID()),
                '?',
                '?',
                '?',
                '?, ?, ?, ?, ?',
            )).'),';
            $insertQuery = $connection->prepare(
                'INSERT INTO Translations (tCreatedOn, tCreatedBy, tLocale, tStatus, tTranslatable, tText0, tText1, tText2, tText3, tText4, tText5) VALUES '
                .rtrim(str_repeat($insertQueryChunk, self::IMPORT_BATCH_SIZE), ',')
            )->getWrappedStatement();
            $insertParams = array();
            $insertCount = 0;
            foreach ($translations as $translationKey => $translation) {
                /* @var \Gettext\Translation $translation */
                if (!$translation->hasTranslation()) {
                    // This $translation instance is not translated
                    continue;
                }
                $plural = $translation->getPlural();
                if ($pluralCount > 1 && $plural !== '' && !$translation->hasPluralTranslation()) {
                    // This plural form of the $translation instance is not translated
                    continue;
                }
                $searchQuery->execute(array(md5($plural ? "$translationKey\x005$plural" : $translationKey)));
                $row = $searchQuery->fetch();
                $searchQuery->closeCursor();
                if ($row === false) {
                    // No translatable string for this translation
                    continue;
                }
                $saveStatus = null;
                if ($row['tID'] === null) {
                    $saveStatus = $statusForNewTranslations;
                } else {
                    $oldStatus = (int) $row['tStatus'];
                    $same = $row['tText0'] === $translation->getTranslation();
                    if ($same && $plural !== '') {
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
                    }
                    if ($same) {
                        if ($status > $oldStatus) {
                            if ($oldStatus === 0) {
                                // Deactivate other translations
                                $connection->executeQuery(
                                    'update Translations set tStatus = 0 where tLocale = ? and tTranslatable = ?',
                                    array($locale->getID(), $row['translatableID'])
                                );
                            }
                            // Activate this translation
                            $connection->executeQuery(
                                'update Translations set tStatus = ? where tID = ? limit 1',
                                array($status, $row['tID'])
                            );
                        }
                        continue;
                    }
                    // Save the new translation
                    if ($status < $oldStatus) {
                        // Deactivated
                        $saveStatus = 0;
                    } else {
                        // Activated
                        $saveStatus = $status;
                    }
                }
                if ($saveStatus !== null) {
                    if ($saveStatus > 0) {
                        // Deactivate all (other) translations
                        $connection->executeQuery(
                            'update Translations set tStatus = 0 where tLocale = ? and tTranslatable = ?',
                            array($locale->getID(), $row['translatableID'])
                        );
                    }
                    // No translation found for this translatable string - Let's add it
                    $insertParams[] = $saveStatus;
                    $insertParams[] = $row['translatableID'];
                    $insertParams[] = $translation->getTranslation();
                    for ($p = 1; $p <= 5; ++$p) {
                        $insertParams[] = (($plural === '') || ($p >= $pluralCount)) ? '' : $translation->getPluralTranslation($p - 1);
                    }
                    ++$insertCount;
                    if ($insertCount === self::IMPORT_BATCH_SIZE) {
                        $insertQuery->execute($insertParams);
                        $insertParams = array();
                        $insertCount = 0;
                    }
                }
            }
            if ($insertCount > 0) {
                $connection->executeQuery(
                    'INSERT INTO Translations (tCreatedOn, tCreatedBy, tStatus, tLocale, tTranslatable, tText0, tText1, tText2, tText3, tText4, tText5) VALUES '.rtrim(str_repeat($insertQueryChunk, $insertCount), ','),
                    $insertParams
                );
            }
            $connection->commit();
        } catch (Exception $x) {
            try {
                $connection->rollBack();
            } catch (Exception $foo) {
            }
            throw $x;
        }
    }
}
