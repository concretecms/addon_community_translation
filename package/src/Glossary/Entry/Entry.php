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
    const TERMTYPE_verb = 'verb';

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
     * Get all the allowed type names.
     *
     * @return array
     */
    public static function getTypeNames()
    {
        static $result;
        if (!isset($result)) {
            $result = array(
                self::TERMTYPE_ADJECTIVE => tc('TermType', 'adjective'),
                self::TERMTYPE_ADVERB => tc('TermType', 'adverb'),
                self::TERMTYPE_CONJUNCTION => tc('TermType', 'conjunction'),
                self::TERMTYPE_INTERJECTION => tc('TermType', 'interjection'),
                self::TERMTYPE_NOUN => tc('TermType', 'noun'),
                self::TERMTYPE_PREPOSITION => tc('TermType', 'preposition'),
                self::TERMTYPE_PRONOUN => tc('TermType', 'pronoun'),
                self::TERMTYPE_verb => tc('TermType', 'verb'),
            );
            natcasesort($result);
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
            $valid = array_keys(self::getTypeNames());
        }

        return ($type === '') || in_array($type, $valid, true);
    }
}
