<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity;

use Concrete\Core\Entity\User\User;
use Concrete\Core\Error\UserMessageException;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gettext\Languages\Language as GettextLanguage;
use Punic\Language;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents a locale.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Locale",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationLocales",
 *     uniqueConstraints={
 *         @Doctrine\ORM\Mapping\UniqueConstraint(name="IDX_CTLocaleIssource", columns={"isSource"})
 *     },
 *     options={"comment": "Defined locales for the Community Translation package"}
 * )
 */
class Locale
{
    /**
     * Locale identifier.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=12, options={"comment": "Locale identifier"})
     * @Doctrine\ORM\Mapping\Id
     */
    protected string $id;

    /**
     * Locale English name.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=100, nullable=false, options={"comment": "Locale English name"})
     */
    protected string $name;

    /**
     * Is this the source locale? (One and only one locale should have this).
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=true, options={"comment": "Is this the source locale? (One and only one locale should have this)"}))
     */
    protected ?bool $isSource;

    /**
     * Plural forms.
     *
     * @Doctrine\ORM\Mapping\Column(type="array", nullable=false, options={"comment": "Plural forms"})
     */
    protected array $pluralForms;

    /**
     * The formula used to to determine the plural case.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "The formula used to to determine the plural case"})
     */
    protected string $pluralFormula;

    /**
     * Is this locale approved?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment": "Is this locale approved?"})
     */
    protected bool $isApproved;

    /**
     * User that requested this locale.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Concrete\Core\Entity\User\User")
     * @Doctrine\ORM\Mapping\JoinColumn(name="requestedBy", referencedColumnName="uID", nullable=true, onDelete="SET NULL")
     */
    protected ?User $requestedBy;

    /**
     * When has this locale been requested?
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=true, options={"comment": "When has this locale been requested?"})
     */
    protected ?DateTimeImmutable $requestedOn;

    /**
     * Translations associated to this locale.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Translation", mappedBy="locale")
     */
    protected Collection $translations;

    /**
     * Stats associated to this locale.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Stats", mappedBy="locale")
     */
    protected Collection $stats;

    /**
     * Localized Glossary Entries associated to this locale.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Glossary\Entry\Localized", mappedBy="locale")
     */
    protected Collection $glossaryEntries;

    /**
     * Localized comments about translations.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Translatable\Comment", mappedBy="locale")
     */
    protected Collection $comments;

    /**
     * @throws \Concrete\Core\Error\UserMessageException if $id is not a valid locale identifier
     */
    public function __construct(string $id)
    {
        $language = GettextLanguage::getById($id);
        if ($language === null) {
            throw new UserMessageException(t('Unknown locale identifier: %s', $id));
        }
        $pluralForms = [];
        foreach ($language->categories as $category) {
            $pluralForms[] = $category->id . ':' . $category->examples;
        }
        $this->id = $language->id;
        $this->name = $language->name;
        $this->isSource = null;
        $this->pluralForms = $pluralForms;
        $this->pluralFormula = $language->formula;
        $this->isApproved = false;
        $this->requestedBy = null;
        $this->requestedOn = null;
        $this->translations = new ArrayCollection();
        $this->stats = new ArrayCollection();
        $this->glossaryEntries = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->id;
    }

    /**
     * Get the locale identifier.
     */
    public function getID(): string
    {
        return $this->id;
    }

    /**
     * Get the locale English name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the localized name of this locale.
     */
    public function getDisplayName(): string
    {
        return Language::getName($this->id);
    }

    /**
     * Set the locale English name.
     *
     * @return $this
     */
    public function setName(string $value): self
    {
        $this->name = $value;

        return $this;
    }

    /**
     * Is this the source locale? (One and only one locale should have this).
     */
    public function isSource(): bool
    {
        return $this->isSource ?? false;
    }

    /**
     * Is this the source locale? (One and only one locale should have this).
     *
     * @return $this
     */
    public function setIsSource(bool $value): self
    {
        $this->isSource = $value ? true : null;

        return $this;
    }

    /**
     * Get the plural forms.
     *
     * @return string[]
     */
    public function getPluralForms(): array
    {
        return $this->pluralForms;
    }

    /**
     * Get the number of plural forms.
     */
    public function getPluralCount(): int
    {
        return count($this->getPluralForms());
    }

    /**
     * Set the plural forms.
     *
     * @param string[] $value
     *
     * @return $this
     */
    public function setPluralForms(array $value): self
    {
        $this->pluralForms = $value;

        return $this;
    }

    /**
     * Get the formula used to to determine the plural case.
     */
    public function getPluralFormula(): string
    {
        return $this->pluralFormula;
    }

    /**
     * Set the formula used to to determine the plural case.
     *
     * @return $this
     */
    public function setPluralFormula(string $value): self
    {
        $this->pluralFormula = $value;

        return $this;
    }

    /**
     * Is this locale approved?
     */
    public function isApproved(): bool
    {
        return $this->isApproved;
    }

    /**
     * Is this locale approved?
     *
     * @return $this
     */
    public function setIsApproved(bool $value): self
    {
        $this->isApproved = $value;

        return $this;
    }

    /**
     * Get the user that requested this locale.
     */
    public function getRequestedBy(): ?User
    {
        return $this->requestedBy;
    }

    /**
     * Set the user that requested this locale.
     *
     * @return $this
     */
    public function setRequestedBy(?User $value): self
    {
        $this->requestedBy = $value;

        return $this;
    }

    /**
     * Get the date/time when has this locale been requested (if available).
     */
    public function getRequestedOn(): ?DateTimeImmutable
    {
        return $this->requestedOn;
    }

    /**
     * Set the date/time when has this locale been requested.
     *
     * @return $this
     */
    public function setRequestedOn(?DateTimeImmutable $value): self
    {
        $this->requestedOn = $value;

        return $this;
    }
}
