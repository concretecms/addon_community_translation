<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity\Glossary\Entry;

use CommunityTranslation\Entity\Glossary\Entry;
use CommunityTranslation\Entity\Locale;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents the translation of a glossary entry.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Glossary\Entry\Localized",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationGlossaryEntriesLocalized",
 *     options={"comment": "Localized glossary entries"}
 * )
 */
class Localized
{
    /**
     * Associated Entry record.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Glossary\Entry", inversedBy="translations")
     * @Doctrine\ORM\Mapping\JoinColumn(name="entry", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Doctrine\ORM\Mapping\Id
     */
    protected Entry $entry;

    /**
     * Associated Locale.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale", inversedBy="glossaryEntries")
     * @Doctrine\ORM\Mapping\JoinColumn(name="locale", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Doctrine\ORM\Mapping\Id
     */
    protected Locale $locale;

    /**
     * Term translation.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Translated term"})
     *
     * @var string
     */
    protected string $translation;

    /**
     * Locale-specific comments about the term.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Locale-specific comment about the term"})
     */
    protected string $comments;

    public function __construct(Entry $entry, Locale $locale, string $translation)
    {
        $this->entry = $entry;
        $this->locale = $locale;
        $this->translation = $translation;
        $this->comments = '';
    }

    /**
     * Get the associated glossary entry.
     */
    public function getEntry(): Entry
    {
        return $this->entry;
    }

    /**
     * Get the associated Locale.
     */
    public function getLocale(): Locale
    {
        return $this->locale;
    }

    /**
     * Set the term translation.
     *
     * @return $this
     */
    public function setTranslation(string $value): self
    {
        $this->translation = $value;

        return $this;
    }

    /**
     * Get the term translation.
     */
    public function getTranslation(): string
    {
        return $this->translation;
    }

    /**
     * Set the locale-specific comments about the term.
     *
     * @return $this
     */
    public function setComments(string $value): self
    {
        $this->comments = $value;

        return $this;
    }

    /**
     * Get the locale-specific comments about the term.
     */
    public function getComments(): string
    {
        return $this->comments;
    }
}
