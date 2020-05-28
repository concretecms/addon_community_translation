<?php

namespace CommunityTranslation\Translation;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use Concrete\Core\Application\Application;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\Error\UserMessageException;
use DateTime;
use Doctrine\ORM\EntityManager;
use Exception;
use Gettext\Translation as GettextTranslation;
use Gettext\Translations;
use Punic\Misc;
use Symfony\Component\EventDispatcher\GenericEvent;
use Throwable;

class Importer
{
    /**
     * @var int
     */
    const IMPORT_BATCH_SIZE = 50;

    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * The entity manager object.
     *
     * @var EntityManager
     */
    protected $em;

    /**
     * The events director.
     *
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
     */
    protected $events;

    /**
     * @param Application $application
     */
    public function __construct(Application $app, EntityManager $em)
    {
        $this->app = $app;
        $this->em = $em;
        $this->events = $this->app->make('director');
    }

    /**
     * Import translations into the database.
     *
     * This function works directly with the database, not with entities (so that working on thousands of strings requires seconds instead of minutes).
     * This implies that entities related to translations may become invalid.
     *
     * @param Translations $translations The translations to be imported
     * @param LocaleEntity $locale The locale of the translations
     * @param UserEntity $user The user to which new translations should be associated
     * @param ImportOptions $options Import options (if not specified: we assume regular translator options)
     *
     * @throws UserMessageException
     *
     * @return ImportResult
     */
    public function import(Translations $translations, LocaleEntity $locale, UserEntity $user, ImportOptions $options = null)
    {
        if ($options === null) {
            $options = ImportOptions::forTranslators();
        }
        $allFuzzy = $options->getAllFuzzy();
        $unapproveFuzzy = $options->getUnapproveFuzzy();
        $pluralCount = $locale->getPluralCount();
        $connection = $this->em->getConnection();
        $nowExpression = $connection->getDatabasePlatform()->getNowExpression();
        $sqlNow = (new DateTime())->format($connection->getDatabasePlatform()->getDateTimeFormatString());
        $translatablesChanged = [];
        $result = new ImportResult();
        $insertParams = [];
        $insertCount = 0;
        // gettext strongly discourages the use of these characters
        $invalidCharsMap = [
            "\x07" => '\\a',
            "\x08" => '\\b',
            "\x0C" => '\\f',
            "\x0D" => '\\r',
            "\x0B" => '\\v',
        ];
        $invalidChars = implode('', array_keys($invalidCharsMap));
        $connection->beginTransaction();
        try {
            // Prepare some queries
            $querySearch = $connection->prepare('
select
    CommunityTranslationTranslatables.id as translatableID,
    CommunityTranslationTranslations.*
from
    CommunityTranslationTranslatables
    left join CommunityTranslationTranslations
        on CommunityTranslationTranslatables.id = CommunityTranslationTranslations.translatable
        and ' . $connection->quote($locale->getID()) . ' = CommunityTranslationTranslations.locale
where
    CommunityTranslationTranslatables.hash = ?
            ')->getWrappedStatement();

            $queryInsert = $connection->prepare(
                $this->buildInsertTranslationsSQL($connection, $locale, self::IMPORT_BATCH_SIZE, $user)
            )->getWrappedStatement();

            $queryUnsetCurrentTranslation = $connection->prepare(
                'UPDATE CommunityTranslationTranslations SET current = NULL, currentSince = NULL, approved = ? WHERE id = ? LIMIT 1'
            )->getWrappedStatement();

            $querySetCurrentTranslation = $connection->prepare(
                'UPDATE CommunityTranslationTranslations SET current = 1, currentSince = ' . $nowExpression . ', approved = ? WHERE id = ? LIMIT 1'
            )->getWrappedStatement();

            $queryResubmitTranslation = $connection->prepare(
                'UPDATE CommunityTranslationTranslations SET approved = NULL, createdBy = ' . $user->getUserID() . ' WHERE id = ? LIMIT 1'
            )->getWrappedStatement();

            // Check every strings to be imported
            foreach ($translations as $translationKey => $translation) {
                /* @var GettextTranslation $translation */

                // Check if the string is translated
                if ($translation->hasTranslation() === false) {
                    // This $translation instance is not translated
                    ++$result->emptyTranslations;
                    continue;
                }
                $isPlural = $translation->hasPlural();
                if ($isPlural === true && $pluralCount > 1 && $translation->hasPluralTranslation() === false) {
                    // This plural form of the $translation instance is not translated
                    ++$result->emptyTranslations;
                    continue;
                }

                $allTranslations = $translation->getTranslation();
                if ($isPlural && $pluralCount > 1) {
                    $allTranslations .= implode('', $translation->getPluralTranslation());
                }

                $s = strpbrk($allTranslations, $invalidChars);
                if ($s !== false) {
                    throw new UserMessageException(
                        t('The translation for the string \'%1$s\' contains the invalid character \'%2$s\'.', $translation->getOriginal(), $invalidCharsMap[$s[0]])
                        . "\n" .
                        t('Translations can not contain these characters: %s', "'" . implode("', '", array_values($invalidCharsMap)) . "'")
                    );
                }

                $this->checkXSS($translation);
                $this->checkFormat($translation);

                // Let's look for the current translation and for an existing translation exactly the same as the one we're importing
                $translatableID = null;
                $currentTranslation = null;
                $sameTranslation = null;
                $hash = md5($isPlural ? ("$translationKey\005" . $translation->getPlural()) : $translationKey);
                $querySearch->execute([$hash]);
                while (($row = $querySearch->fetch()) !== false) {
                    if ($translatableID === null) {
                        $translatableID = (int) $row['translatableID'];
                    }
                    if (!isset($row['id'])) {
                        break;
                    }
                    if ($currentTranslation === null && $row['current'] === '1') {
                        $currentTranslation = $row;
                    }
                    if ($sameTranslation === null && $this->rowSameAsTranslation($row, $translation, $isPlural, $pluralCount)) {
                        $sameTranslation = $row;
                    }
                }
                $querySearch->closeCursor();

                // Check if the translation doesn't have a corresponding translatable string
                if ($translatableID === null) {
                    ++$result->unknownStrings;
                    continue;
                }

                // Check if the new translation is approved or not
                if ($allFuzzy) {
                    $isFuzzy = true;
                } else {
                    $isFuzzy = in_array('fuzzy', $translation->getFlags(), true);
                }

                if ($sameTranslation === null) {
                    // This translation is not already present - Let's add it

                    if ($currentTranslation === null) {
                        // No current translation for this string: add this new one and mark it as the current one
                        $addAsCurrent = 1;
                        if ($isFuzzy) {
                            $addAsApproved = null;
                            ++$result->newApprovalNeeded;
                        } else {
                            $addAsApproved = 1;
                        }
                        $translatablesChanged[] = $translatableID;
                        ++$result->addedAsCurrent;
                    } elseif ($isFuzzy === false || !$currentTranslation['approved']) {
                        // There's already a current translation for this string, but we'll activate this new one
                        if ($isFuzzy === false && $currentTranslation['approved'] === null) {
                            $queryUnsetCurrentTranslation->execute([0, $currentTranslation['id']]);
                        } else {
                            $queryUnsetCurrentTranslation->execute([$currentTranslation['approved'], $currentTranslation['id']]);
                        }
                        $addAsCurrent = 1;
                        if ($isFuzzy) {
                            $addAsApproved = null;
                            ++$result->newApprovalNeeded;
                        } else {
                            $addAsApproved = 1;
                        }
                        $translatablesChanged[] = $translatableID;
                        ++$result->addedAsCurrent;
                    } else {
                        // Let keep the previously current translation as the current one, but let's add this new one
                        $addAsCurrent = null;
                        $addAsApproved = null;
                        ++$result->addedNotAsCurrent;
                        ++$result->newApprovalNeeded;
                    }

                    // Add the new record to the queue
                    $insertParams[] = $addAsCurrent;
                    $insertParams[] = ($addAsCurrent === 1) ? $sqlNow : null;
                    $insertParams[] = $addAsApproved;
                    $insertParams[] = $translatableID;
                    $insertParams[] = $translation->getTranslation();
                    for ($p = 1; $p <= 5; ++$p) {
                        $insertParams[] = ($isPlural && $p < $pluralCount) ? $translation->getPluralTranslation($p - 1) : '';
                    }
                    ++$insertCount;

                    if ($insertCount === self::IMPORT_BATCH_SIZE) {
                        // Flush the add queue
                        $queryInsert->execute($insertParams);
                        $insertParams = [];
                        $insertCount = 0;
                    }
                } elseif ($currentTranslation === null) {
                    // This translation is already present, but there's no current translation: let's make it the current one
                    if ($isFuzzy) {
                        $querySetCurrentTranslation->execute([$sameTranslation['approved'], $sameTranslation['id']]);
                    } else {
                        $querySetCurrentTranslation->execute([1, $sameTranslation['id']]);
                        if (!$sameTranslation['approved']) {
                            ++$result->newApprovalNeeded;
                        }
                    }
                    $translatablesChanged[] = $translatableID;
                    ++$result->addedAsCurrent;
                } elseif ($sameTranslation['current'] === '1') {
                    // This translation is already present and it's the current one
                    if ($isFuzzy === false && !$sameTranslation['approved']) {
                        // Let's mark the translation as approved
                        $querySetCurrentTranslation->execute([1, $sameTranslation['id']]);
                        ++$result->existingCurrentApproved;
                    } elseif ($isFuzzy === true && $sameTranslation['approved'] && $unapproveFuzzy) {
                        $querySetCurrentTranslation->execute([0, $sameTranslation['id']]);
                        ++$result->existingCurrentUnapproved;
                    } else {
                        ++$result->existingCurrentUntouched;
                    }
                } else {
                    // This translation exists, but we have already another translation that's the current one
                    if ($isFuzzy === false || !$currentTranslation['approved']) {
                        // Let's make the new translation the current one
                        if ($isFuzzy === false && $currentTranslation['approved'] === null) {
                            $queryUnsetCurrentTranslation->execute([0, $currentTranslation['id']]);
                        } else {
                            $queryUnsetCurrentTranslation->execute([$currentTranslation['approved'], $currentTranslation['id']]);
                        }
                        $querySetCurrentTranslation->execute([1, $sameTranslation['id']]);
                        $translatablesChanged[] = $translatableID;
                        ++$result->existingActivated;
                    } else {
                        // The new translation already exists, it is fuzzy (not approved) and the current translation is approved
                        if ($sameTranslation['approved'] !== null) {
                            // Let's re-submit the existing translation for approval, as if it's a new translation
                            $queryResubmitTranslation->execute([$sameTranslation['id']]);
                            ++$result->addedNotAsCurrent;
                            ++$result->newApprovalNeeded;
                        } else {
                            ++$result->existingNotCurrentUntouched;
                        }
                    }
                }
            }

            if ($insertCount > 0) {
                // Flush the add queue
                $connection->executeQuery(
                    $this->buildInsertTranslationsSQL($connection, $locale, $insertCount, $user),
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
        } catch (Throwable $x) {
            try {
                $connection->rollBack();
            } catch (Exception $foo) {
            }
            throw $x;
        }

        if (count($translatablesChanged) > 0) {
            try {
                $this->events->dispatch(
                    'community_translation.translationsUpdated',
                    new GenericEvent(
                        $locale,
                        [
                            'translatableIDs' => $translatablesChanged,
                        ]
                    )
                );
            } catch (Exception $foo) {
            }
        }

        if ($result->newApprovalNeeded > 0) {
            try {
                $this->events->dispatch(
                    'community_translation.newApprovalNeeded',
                    new GenericEvent(
                        $locale,
                        [
                            'number' => $result->newApprovalNeeded,
                        ]
                    )
                );
            } catch (Exception $foo) {
            }
        }

        return $result;
    }

    /**
     * @param Connection $connection
     * @param int $numRecords
     * @param UserEntity $user
     *
     * @return string
     */
    private function buildInsertTranslationsSQL(Connection $connection, LocaleEntity $locale, $numRecords, UserEntity $user)
    {
        $fields = '(locale, createdOn, createdBy, current, currentSince, approved, translatable, text0, text1, text2, text3, text4, text5)';
        $values = ' (' . implode(', ', [
            // locale
            $connection->quote($locale->getID()),
            // createdOn
            $connection->getDatabasePlatform()->getNowExpression(),
            // createdBy
            $user->getUserID(),
            // current
            '?',
            // currentSince
            '?',
            // approved
            '?',
            // translatable
            '?',
            // text0... text5
            '?, ?, ?, ?, ?, ?',
        ]) . '),';

        $sql = 'INSERT INTO CommunityTranslationTranslations ';
        $sql .= ' ' . $fields;
        $sql .= ' VALUES ' . rtrim(str_repeat($values, $numRecords), ',');

        return $sql;
    }

    /**
     * Is a database row the same as the translation?
     *
     * @param array $row
     * @param GettextTranslation $translation
     * @param bool $isPlural
     * @param int $pluralCount
     *
     * @return bool
     */
    private function rowSameAsTranslation(array $row, GettextTranslation $translation, $isPlural, $pluralCount)
    {
        if ($row['text0'] !== $translation->getTranslation()) {
            return false;
        }
        if ($isPlural === false) {
            return true;
        }
        $same = true;
        switch ($pluralCount) {
            case 6:
                if ($same && $row['text5'] !== $translation->getPluralTranslation(4)) {
                    $same = false;
                }
                /* @noinspection PhpMissingBreakStatementInspection */
            case 5:
                if ($same && $row['text4'] !== $translation->getPluralTranslation(3)) {
                    $same = false;
                }
                /* @noinspection PhpMissingBreakStatementInspection */
            case 4:
                if ($same && $row['text3'] !== $translation->getPluralTranslation(2)) {
                    $same = false;
                }
                /* @noinspection PhpMissingBreakStatementInspection */
            case 3:
                if ($same && $row['text2'] !== $translation->getPluralTranslation(1)) {
                    $same = false;
                }
                /* @noinspection PhpMissingBreakStatementInspection */
            case 2:
                if ($same && $row['text1'] !== $translation->getPluralTranslation(0)) {
                    $same = false;
                }
                break;
        }

        return $same;
    }

    /**
     * Check that translated text doesn't contain potentially harmful code.
     *
     * @param \Gettext\Translation $translation
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function checkXSS(GettextTranslation $translation)
    {
        $sourceText = $translation->getOriginal();
        $translatedText = $translation->getTranslation();
        if ($translation->hasPlural()) {
            $sourceText .= "\n" . $translation->getPlural();
            $translatedText .= "\n" . implode("\n", $translation->getPluralTranslation());
        }
        $checkTags = false;
        foreach (['<', '=', '"'] as $char) {
            if (strpos($sourceText, $char) === false) {
                if (strpos($translatedText, $char) !== false) {
                    throw new UserMessageException(t('The translation for the string \'%1$s\' can\'t contain the character \'%2$s\'.', $translation->getOriginal(), $char));
                }
            } else {
                if ($char === '<') {
                    $checkTags = true;
                }
            }
        }
        if ($checkTags) {
            $m = null;
            $sourceTags = preg_match_all('/<\\w+/', $sourceText, $m) ? array_unique($m[0]) : [];
            $translatedTags = preg_match_all('/<\\w+/', $translatedText, $m) ? array_unique($m[0]) : [];
            $extraTags = array_diff($translatedTags, $sourceTags);
            switch (count($extraTags)) {
                case 0:
                    break;
                case 1:
                    throw new UserMessageException(t('The translation for the string \'%1$s\' can\'t contain the string \'%2$s\'.', $translation->getOriginal(), current($extraTags)));
                default:
                    $error = t('The translation for the string \'%1$s\' can\'t contain these strings:', $translation->getOriginal());
                    $error .= "\n- " . implode("\n- ", $extraTags);
                    throw new UserMessageException($error);
            }
        }
    }

    /**
     * Check that translated text doesn't contain potentially harmful code.
     *
     * @param \Gettext\Translation $translation
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function checkFormat(GettextTranslation $translation)
    {
        $sourcePlaceholdersList = $this->extractSourcePlaceholders($translation);
        $translatedTexts = [$translation->getTranslation()];
        if ($translation->hasPlural()) {
            $translatedTexts = array_merge($translatedTexts, $translation->getPluralTranslation());
        }
        foreach ($translatedTexts as $translatedText) {
            $translatedPlaceholders = $this->extractPlaceholders($translatedText);
            $someMatch = false;
            foreach ($sourcePlaceholdersList as $sourcePlaceholders) {
                if ($translatedPlaceholders === $sourcePlaceholders) {
                    $someMatch = true;
                    break;
                }
            }
            if ($someMatch) {
                continue;
            }
            $sourcePlaceholderDescriptions = [];
            foreach ($sourcePlaceholdersList as $sourcePlaceholders) {
                $sourcePlaceholders = array_map(function($placeholder) { return '"' . $placeholder . '"'; }, $sourcePlaceholders);                
                switch (count($sourcePlaceholders)) {
                    case 0:
                        $sourcePlaceholderDescriptions[] = t('no placeholders');
                        break;
                    case 1:
                        $sourcePlaceholderDescriptions[] = t('the placeholder %s', $sourcePlaceholders[0]);
                        break;
                    default:
                        $sourcePlaceholderDescriptions[] = t('all these placeholders: %s', Misc::joinAnd($sourcePlaceholders));
                        break;
                }
            }
            if (isset($sourcePlaceholderDescriptions[1])) {
                $sourcePlaceholderDescription = "\n- " . implode("\n- ", $sourcePlaceholderDescriptions);
            } else {
                $sourcePlaceholderDescription = (string) array_pop($sourcePlaceholderDescriptions);
            }
            if ($translatedPlaceholders === []) {
                throw new UserMessageException(t(
                    'The translation does not contain any placeholder, but it should contain %s',
                    $sourcePlaceholderDescription
                ));
            }
            $translatedPlaceholderDescription = Misc::joinAnd(array_map(function($placeholder) { return '"' . $placeholder . '"'; }, $translatedPlaceholders));
            if ($sourcePlaceholderDescription === t('no placeholders')) {
                throw new UserMessageException(t(
                    'The translation should not contain any placeholder, but it contains %s',
                    $translatedPlaceholderDescription
                ));
            }
            throw new UserMessageException(t(
                'The translation contains %1$s, but it should contain %2$s',
                $translatedPlaceholderDescription,
                $sourcePlaceholderDescription
            ));
        }
    }

    private function extractSourcePlaceholders(GettextTranslation $translation)
    {
        $placeholdersList = [$this->extractPlaceholders($translation->getOriginal())];
        if (!$translation->hasPlural()) {
            return $placeholdersList;
        }
        $pluralPlaceholders = $this->extractPlaceholders($translation->getPlural());
        if ($placeholdersList[0] !== $pluralPlaceholders) {
            $placeholdersList[] = $pluralPlaceholders;
        }
        foreach ($placeholdersList as $placeholders) {
            $index = array_search('%s', $placeholders, true);
            if ($index === false) {
                $index = array_search('%1$s', $placeholders, true);
                if ($index === false) {
                    continue;
                }
            }
            array_splice($placeholders, $index, 1);
            if (!in_array($placeholders, $placeholdersList, true)) {
                $placeholdersList[] = $placeholders;
            }
        }

        return $placeholdersList;
    }

    /**
     * Extract the placeholders from a string.
     *
     * @param string $text
     *
     * @return string[] sorted list of placeholders found
     */
    private function extractPlaceholders($text)
    {
        // placeholder := %[position][flags][width][.precision]specifier
        // position := \d+$
        // flags := ([\-+ 0]|('.))*
        // width := \d*
        // precision := (\.\d*)?
        // specifier := [bcdeEfFgGosuxX]
        // $placeholdersRX = %(?:\d+\$)?(?:[\-+ 0]|('.))*\d*(?:\.\d*)?[bcdeEfFgGosuxX]
        $placeholdersRX = "/%(?:\\d+\\$)?(?:[\\-+ 0]|(?:'.))*\\d*(?:\\.\\d*)?[bcdeEfFgGosuxX]/";
        $matches = null;
        preg_match_all($placeholdersRX, $text, $matches);
        sort($matches[0]);

        return $matches[0];
    }
}
