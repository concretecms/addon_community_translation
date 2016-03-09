<?php
namespace Concrete\Package\CommunityTranslation\Src\Glossary\Entry;

use Concrete\Package\CommunityTranslation\Src\Locale\Locale;

/**
 * Represents the translation of a glossary entry.
 *
 * @Entity
 * @Table(name="GlossaryLocalizedEntries", options={"comment": "Localized glossary entries"})
 */
class Localized
{
    // Properties

    /**
     * Associated Glossary Entry record.
     *
     * @Id
     * @ManyToOne(targetEntity="Concrete\Package\CommunityTranslation\Src\Glossary\Entry\Entry", inversedBy="translations")
     * @JoinColumn(name="gleEntry", referencedColumnName="geID", nullable=false, onDelete="CASCADE")
     *
     * @var int
     */
    protected $gleEntry;

    /**
     * Associated Locale.
     *
     * @Id
     * @ManyToOne(targetEntity="Concrete\Package\CommunityTranslation\Src\Locale\Locale", inversedBy="glossaryEntries")
     * @JoinColumn(name="gleLocale", referencedColumnName="lID", nullable=false, onDelete="CASCADE")
     *
     * @var string
     */
    protected $gleLocale;

    /**
     * Term translation.
     *
     * @Column(type="text", nullable=false, options={"comment": "Translated term"})
     *
     * @var string
     */
    protected $gleTranslation;

    /**
     * Locale-specific comments about the term.
     *
     * @Column(type="text", nullable=false, options={"comment": "Locale-specific comment about the term"})
     *
     * @var string
     */
    protected $gleComments;

    // Getters and setters

    /**
     * Get the associated Glossary Entry.
     *
     * @return Entry
     */
    public function getEntry()
    {
        return $this->gleEntry;
    }

    /**
     * Get the associated Locale.
     *
     * @return Locale
     */
    public function getLocale()
    {
        return $this->gleLocale;
    }

    /**
     * Get the term translation.
     *
     * @return string
     */
    public function getTranslation()
    {
        return $this->gleTranslation;
    }

    /**
     * Set the term translation.
     *
     * @param string $value
     */
    public function setTranslation($value)
    {
        $this->gleTranslation = trim((string) $value);
    }

    /**
     * Get the locale-specific comments about the term.
     *
     * @return string
     */
    public function getComments()
    {
        return $this->gleComments;
    }

    /**
     * Set the locale-specific comments about the term.
     *
     * @param string $value
     */
    public function setComments($value)
    {
        $this->gleComments = trim((string) $value);
    }

    // Helper functions

    public static function create(Entry $entry, Locale $locale)
    {
        $new = new static();
        $new->gleEntry = $entry;
        $new->gleLocale = $locale;
        $new->gleTranslation = '';
        $new->gleComments = '';

        return $new;
    }
}
