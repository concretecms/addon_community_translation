<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity\Glossary;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents an glossary entry.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Glossary\Entry",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationGlossaryEntries",
 *     uniqueConstraints={
 *         @Doctrine\ORM\Mapping\UniqueConstraint(name="IDX_CTGlossaryEntryTermType", columns={"term", "type"})
 *     },
 *     indexes={
 *         @Doctrine\ORM\Mapping\Index(name="IDX_CTGlossaryEntryTermFT", columns={"term"}, flags={"fulltext"})
 *     },
 *     options={"comment": "Glossary entries"}
 * )
 */
class Entry
{
    /**
     * Glossary entry ID.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned": true, "comment": "Glossary entry ID"})
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected ?int $id;

    /**
     * Term.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment": "Term"})
     *
     * @var string
     */
    protected string $term;

    /**
     * Term type.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=50, nullable=false, options={"comment": "Term type"})
     */
    protected string $type;

    /**
     * Comments about the term.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Comments about the term"})
     */
    protected string $comments;

    /**
     * Translations of this entry.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Glossary\Entry\Localized", mappedBy="entry")
     */
    protected Collection $translations;

    public function __construct(string $term)
    {
        $this->id = null;
        $this->term = $term;
        $this->comments = '';
        $this->type = '';
        $this->translations = new ArrayCollection();
    }

    /**
     * Get the glossary entry ID.
     */
    public function getID(): ?int
    {
        return $this->id;
    }

    /**
     * Set the term.
     *
     * @return $this
     */
    public function setTerm(string $value): self
    {
        $this->term = $value;

        return $this;
    }

    /**
     * Get the term.
     */
    public function getTerm(): string
    {
        return $this->term;
    }

    /**
     * Set the term type (one of the Entry::CommunityTranslation\Glossary\EntryType:... constants).
     *
     * @return $this
     */
    public function setType(string $value): self
    {
        $this->type = $value;

        return $this;
    }

    /**
     * Get the term type (one of the CommunityTranslation\Glossary\EntryType::... constants).
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set the comments about the term.
     *
     * @return $this
     */
    public function setComments(string $value): self
    {
        $this->comments = $value;

        return $this;
    }

    /**
     * Get the comments about the term.
     */
    public function getComments(): string
    {
        return $this->comments;
    }

    /**
     * Get all the associated translations.
     *
     * @return \CommunityTranslation\Entity\Glossary\Entry\Localized[]
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }
}
