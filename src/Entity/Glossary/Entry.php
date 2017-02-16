<?php
namespace CommunityTranslation\Entity\Glossary;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an glossary entry.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Glossary\Entry",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationGlossaryEntries",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="IDX_CTGlossaryEntryTermType", columns={"term", "type"})
 *     },
 *     indexes={
 *         @ORM\Index(name="IDX_CTGlossaryEntryTermFT", columns={"term"}, flags={"fulltext"})
 *     },
 *     options={"comment": "Glossary entries"}
 * )
 */
class Entry
{
    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * Glossary entry ID.
     *
     * @ORM\Column(type="integer", options={"unsigned": true, "comment": "Glossary entry ID"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $id = null;

    /**
     * Get the glossary entry ID.
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Term.
     *
     * @ORM\Column(type="string", length=255, nullable=false, options={"comment": "Term"})
     *
     * @var string
     */
    protected $term = null;

    /**
     * Get the term.
     *
     * @return string
     */
    public function getTerm()
    {
        return $this->term;
    }

    /**
     * Set the term.
     *
     * @param string $value
     *
     * @return static
     */
    public function setTerm($value)
    {
        $this->term = (string) $value;

        return $this;
    }

    /**
     * Term type.
     *
     * @ORM\Column(type="string", length=50, nullable=false, options={"comment": "Term type"})
     *
     * @var string
     */
    protected $type = null;

    /**
     * Get the term type (one of the CommunityTranslation\Glossary\EntryType::... constants).
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set the term type (one of the Entry::CommunityTranslation\Glossary\EntryType:... constants).
     *
     * @param string $value
     *
     * @return static
     */
    public function setType($value)
    {
        $this->type = (string) $value;

        return $this;
    }

    /**
     * Comments about the term.
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Comments about the term"})
     *
     * @var string
     */
    protected $comments = '';

    /**
     * Get the comments about the term.
     *
     * @return string
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * Set the comments about the term.
     *
     * @param string $value
     *
     * @return static
     */
    public function setComments($value)
    {
        $this->comments = (string) $value;
    }

    /**
     * Translations of this entry.
     *
     * @ORM\OneToMany(targetEntity="CommunityTranslation\Entity\Glossary\Entry\Localized", mappedBy="entry")
     *
     * @var ArrayCollection
     */
    protected $translations;

    /**
     * Get all the associated translations.
     *
     * @return ArrayCollection
     */
    public function getTranslations()
    {
        return $this->translations;
    }
}
