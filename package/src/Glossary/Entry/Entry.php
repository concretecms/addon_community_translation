<?php
namespace Concrete\Package\CommunityTranslation\Src\Glossary\Entry;

use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Represents an glossary entry.
 *
 * @Entity
 * @Table(
 *     name="GlossaryEntries",
 *     uniqueConstraints={@UniqueConstraint(name="GlossaryEntriesTermType", columns={"geTerm", "geType"})},
 *     options={"comment": "Glossary entries"}
 * )
 */
class Entry
{
    /**
     * Term type: adjective.
     *
     * @var string
     */
    const TERMTYPE_ADJECTIVE = 'adjective';
    /**
     * Term type: adverb.
     *
     * @var string
     */
    const TERMTYPE_ADVERB = 'adverb';
    /**
     * Term type: conjunction.
     *
     * @var string
     */
    const TERMTYPE_CONJUNCTION = 'conjunction';
    /**
     * Term type: interjection.
     *
     * @var string
     */
    const TERMTYPE_INTERJECTION = 'interjection';
    /**
     * Term type: noun.
     *
     * @var string
     */
    const TERMTYPE_NOUN = 'noun';
    /**
     * Term type: preposition.
     *
     * @var string
     */
    const TERMTYPE_PREPOSITION = 'preposition';
    /**
     * Term type: pronoun.
     *
     * @var string
     */
    const TERMTYPE_PRONOUN = 'pronoun';
    /**
     * Term type: verb.
     *
     * @var string
     */
    const TERMTYPE_VERB = 'verb';

    // Properties

    /**
     * Glossary entry ID.
     *
     * @Id @Column(type="integer", options={"unsigned": true, "comment": "Glossary entry ID"})
     * @GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $geID;

    /**
     * Term.
     *
     * @Column(type="string", length=255, nullable=false, options={"comment": "Term"})
     *
     * @var string
     */
    protected $geTerm;

    /**
     * Term type.
     *
     * @Column(type="string", length=50, nullable=false, options={"comment": "Term type"})
     *
     * @var string
     */
    protected $geType;

    /**
     * Comments about the term.
     *
     * @Column(type="text", nullable=false, options={"comment": "Comments about the term"})
     *
     * @var string
     */
    protected $geComments;

    /**
     * Translations of this entry.
     *
     * @OneToMany(targetEntity="Concrete\Package\CommunityTranslation\Src\Glossary\Entry\Localized", mappedBy="gleEntry")
     */
    protected $translations;

    // Constructor

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    // Getters and setters

    /**
     * Get the term.
     *
     * @return string
     */
    public function getTerm()
    {
        return $this->geTerm;
    }

    /**
     * Set the term.
     *
     * @param string $value
     */
    public function setTerm($value)
    {
        $this->geTerm = (string) $value;
    }

    /**
     * Get the term type (one of the Entry::TERMTYPE__... constants).
     *
     * @return string
     */
    public function getType()
    {
        return $this->geType;
    }

    /**
     * Set the term type (one of the Entry::TERMTYPE__... constants).
     *
     * @param string $value
     */
    public function setType($value)
    {
        $this->geType = (string) $value;
    }

    /**
     * Get the comments about the term.
     *
     * @return string
     */
    public function getComments()
    {
        return $this->geComments;
    }

    /**
     * Set the comments about the term.
     *
     * @param string $value
     */
    public function setComments($value)
    {
        $this->geComments = (string) $value;
    }

    /**
     * Get all the associated translations.
     *
     * @return Localized[]
     */
    public function getTranslations()
    {
        return $this->translations->toArray();
    }

    // Helper methods

    /**
     * Add a new (unsaved) translation to this entry.
     *
     * @param Locale $locale
     * @param string $translation
     * @param string $comments
     *
     * @return Localized
     */
    public function addTranslation(Locale $locale, $translation, $comments = '')
    {
        $localized = Localized::create($this, $locale);
        $localized->setTranslation($translation);
        $localized->setComments($comments);
        $this->translations->add($localized);

        return $localized;
    }

    /**
     * Get all the allowed type names, short names and descriptions.
     *
     * @return array
     */
    public static function getTypesInfo()
    {
        static $result;
        if (!isset($result)) {
            $result = array(
                self::TERMTYPE_ADJECTIVE => array(
                    'name' => tc('TermType', 'adjective'),
                    'short' => tc('TermType_short', 'adj.'),
                    'description' => t('A word that describes a noun or pronoun (examples: big, happy, obvious).'),
                ),
                self::TERMTYPE_ADVERB => array(
                    'name' => tc('TermType', 'adverb'),
                    'short' => tc('TermType_short', 'adv.'),
                    'description' => t('A word that describes or gives more information about a verb, adjective or other adverb (examples: carefully, quickly, very).'),
                ),
                self::TERMTYPE_CONJUNCTION => array(
                    'name' => tc('TermType', 'conjunction'),
                    'short' => tc('TermType_short', 'conj.'),
                    'description' => t('A word that ​connects words, ​phrases, and ​clauses in a ​sentence (examples: and, but, while, ​although).'),
                ),
                self::TERMTYPE_INTERJECTION => array(
                    'name' => tc('TermType', 'interjection'),
                    'short' => tc('TermType_short', 'interj.'),
                    'description' => t('A word that is used to show a ​short ​sudden ​expression of ​emotion (examples: Bye!, Cheers!, Goodbye!, Hi!, Hooray!).'),
                ),
                self::TERMTYPE_NOUN => array(
                    'name' => tc('TermType', 'noun'),
                    'short' => tc('TermType_short', 'n.'),
                    'description' => t('A word that refers to a ​person, ​place, thing, ​event, ​substance, or ​quality (examples: Andrew, house, pencil, table).'),
                ),
                self::TERMTYPE_PREPOSITION => array(
                    'name' => tc('TermType', 'preposition'),
                    'short' => tc('TermType_short', 'prep.'),
                    'description' => t('A word that is used before a ​noun, a ​noun phrase, or a ​pronoun, ​connecting it to another word (examples: at, for, in, on, under).'),
                ),
                self::TERMTYPE_PRONOUN => array(
                    'name' => tc('TermType', 'pronoun'),
                    'short' => tc('TermType_short', 'pron.'),
                    'description' => t('A word that is used ​instead of a ​noun or a ​noun phrase (examples: I, me, mine, myself).'),
                ),
                self::TERMTYPE_VERB => array(
                    'name' => tc('TermType', 'verb'),
                    'short' => tc('TermType_short', 'v.'),
                    'description' => t('A word or phrase that ​describes an ​action, ​condition, or ​experience (examples: listen, read, write).'),
                ),
            );
            uasort($result, function(array $a, array $b) {
                return strcasecmp($a['name'], $b['name']);
            });
        }

        return $result;
    }

    /**
     * Check if a term type is valid.
     *
     * @return bool
     */
    public static function isValidType($type)
    {
        static $valid;
        if (!isset($valid)) {
            $valid = array_keys(self::getTypesInfo());
        }

        return ($type === '') || in_array($type, $valid, true);
    }
}
