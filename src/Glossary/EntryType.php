<?php

declare(strict_types=1);

namespace CommunityTranslation\Glossary;

use Concrete\Core\Localization\Localization;
use Punic\Comparer;

defined('C5_EXECUTE') or die('Access Denied.');

final class EntryType
{
    /**
     * Term type: adjective.
     *
     * @var string
     */
    public const ADJECTIVE = 'adjective';

    /**
     * Term type: adverb.
     *
     * @var string
     */
    public const ADVERB = 'adverb';

    /**
     * Term type: conjunction.
     *
     * @var string
     */
    public const CONJUNCTION = 'conjunction';

    /**
     * Term type: interjection.
     *
     * @var string
     */
    public const INTERJECTION = 'interjection';

    /**
     * Term type: noun.
     *
     * @var string
     */
    public const NOUN = 'noun';

    /**
     * Term type: preposition.
     *
     * @var string
     */
    public const PREPOSITION = 'preposition';

    /**
     * Term type: pronoun.
     *
     * @var string
     */
    public const PRONOUN = 'pronoun';

    /**
     * Term type: verb.
     *
     * @var string
     */
    public const VERB = 'verb';

    /**
     * @var array
     */
    private static $typesInfo = [];

    /**
     * Get all the allowed type names, short names and descriptions.
     */
    public static function getTypesInfo(): array
    {
        $locale = Localization::activeLocale();
        if (!isset(self::$typesInfo[$locale])) {
            $list = [
                self::ADJECTIVE => [
                    'name' => tc('TermType', 'Adjective'),
                    'short' => tc('TermType_short', 'adj.'),
                    'description' => t('A word that describes a noun or pronoun (examples: big, happy, obvious).'),
                ],
                self::ADVERB => [
                    'name' => tc('TermType', 'Adverb'),
                    'short' => tc('TermType_short', 'adv.'),
                    'description' => t('A word that describes or gives more information about a verb, adjective or other adverb (examples: carefully, quickly, very).'),
                ],
                self::CONJUNCTION => [
                    'name' => tc('TermType', 'Conjunction'),
                    'short' => tc('TermType_short', 'conj.'),
                    'description' => t('A word that connects words, phrases, and clauses in a sentence (examples: and, but, while, although).'),
                ],
                self::INTERJECTION => [
                    'name' => tc('TermType', 'Interjection'),
                    'short' => tc('TermType_short', 'interj.'),
                    'description' => t('A word that is used to show a short sudden expression of emotion (examples: Bye!, Cheers!, Goodbye!, Hi!, Hooray!).'),
                ],
                self::NOUN => [
                    'name' => tc('TermType', 'Noun'),
                    'short' => tc('TermType_short', 'n.'),
                    'description' => t('A word that refers to a person, place, thing, event, substance, or quality (examples: Andrew, house, pencil, table).'),
                ],
                self::PREPOSITION => [
                    'name' => tc('TermType', 'Preposition'),
                    'short' => tc('TermType_short', 'prep.'),
                    'description' => t('A word that is used before a noun, a noun phrase, or a pronoun, connecting it to another word (examples: at, for, in, on, under).'),
                ],
                self::PRONOUN => [
                    'name' => tc('TermType', 'Pronoun'),
                    'short' => tc('TermType_short', 'pron.'),
                    'description' => t('A word that is used instead of a noun or a noun phrase (examples: I, me, mine, myself).'),
                ],
                self::VERB => [
                    'name' => tc('TermType', 'Verb'),
                    'short' => tc('TermType_short', 'v.'),
                    'description' => t('A word or phrase that describes an action, condition, or experience (examples: listen, read, write).'),
                ],
            ];
            $comparer = new Comparer($locale);
            uasort($list, static function (array $a, array $b) use ($comparer): int {
                return $comparer->compare($a['name'], $b['name']);
            });
            self::$typesInfo[$locale] = $list;
        }

        return self::$typesInfo[$locale];
    }

    /**
     * Check if a term type is valid.
     */
    public static function isValidType(string $type): bool
    {
        if ($type === '') {
            $result = true;
        } else {
            $valid = self::getTypesInfo();
            $result = isset($valid[$type]);
        }

        return $result;
    }
}
