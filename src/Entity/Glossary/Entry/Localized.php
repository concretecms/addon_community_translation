<?php
namespace CommunityTranslation\Entity\Glossary\Entry;

use CommunityTranslation\Entity\Glossary\Entry;
use CommunityTranslation\Entity\Locale;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents the translation of a glossary entry.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Glossary\Entry\Localized",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationGlossaryEntriesLocalized",
 *     options={"comment": "Localized glossary entries"}
 * )
 */
class Localized
{
    /**
     * @param Entry $entry
     * @param Locale $locale
     * @param string $text
     * @param string $comments
     *
     * @return static
     */
    public static function create(Entry $entry, Locale $locale)
    {
        $result = new static();
        $result->entry = $entry;
        $result->locale = $locale;
        $result->comments = '';

        return $result;
    }

    protected function __construct()
    {
    }

    /**
     * Associated Entry record.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Glossary\Entry", inversedBy="translations")
     * @ORM\JoinColumn(name="entry", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @ORM\Id
     *
     * @var Entry
     */
    protected $entry;

    /**
     * Get the associated glossary entry.
     *
     * @return Entry|null
     */
    public function getEntry()
    {
        return $this->entry;
    }

    /**
     * Associated Locale.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale", inversedBy="glossaryEntries")
     * @ORM\JoinColumn(name="locale", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @ORM\Id
     *
     * @var Locale|null
     */
    protected $locale;

    /**
     * Get the associated Locale.
     *
     * @return Locale
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Term translation.
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Translated term"})
     *
     * @var string
     */
    protected $translation;

    /**
     * Set the term translation.
     *
     * @param string $value
     *
     * @return static
     */
    public function setTranslation($value)
    {
        $this->translation = (string) $value;

        return $this;
    }

    /**
     * Get the term translation.
     *
     * @return string
     */
    public function getTranslation()
    {
        return $this->translation;
    }

    /**
     * Locale-specific comments about the term.
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Locale-specific comment about the term"})
     *
     * @var string
     */
    protected $comments;

    /**
     * Set the locale-specific comments about the term.
     *
     * @param string $value
     *
     * @return static
     */
    public function setComments($value)
    {
        $this->comments = (string) $value;

        return $this;
    }

    /**
     * Get the locale-specific comments about the term.
     *
     * @return string
     */
    public function getComments()
    {
        return $this->comments;
    }
}
