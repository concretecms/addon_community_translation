<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity;

use Concrete\Core\Entity\User\User as UserEntity;
use DateTimeImmutable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents an translated string.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Translation",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationTranslations",
 *     uniqueConstraints={
 *         @Doctrine\ORM\Mapping\UniqueConstraint(name="IDX_CTTranslationLocaleTranslatableCurrent", columns={"locale", "translatable", "current"})
 *     },
 *     indexes={
 *         @Doctrine\ORM\Mapping\Index(name="IDX_CTTranslationCreatorSince", columns={"currentSince", "createdBy"})
 *     },
 *     options={"comment": "Translated strings"}
 * )
 */
class Translation
{
    /**
     * Translation ID.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned": true, "comment": "Translation ID"})
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id;

    /**
     * Associated Locale.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale", inversedBy="translations")
     * @Doctrine\ORM\Mapping\JoinColumn(name="locale", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected Locale $locale;

    /**
     * Associated Translatable.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Translatable", inversedBy="translations")
     * @Doctrine\ORM\Mapping\JoinColumn(name="translatable", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected Translatable $translatable;

    /**
     * Record creation date/time.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=false, options={"comment": "Record creation date/time"})
     */
    protected DateTimeImmutable $createdOn;

    /**
     * User that submitted this translation.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Concrete\Core\Entity\User\User")
     * @Doctrine\ORM\Mapping\JoinColumn(name="createdBy", referencedColumnName="uID", nullable=true, onDelete="SET NULL")
     */
    protected ?UserEntity $createdBy;

    /**
     * Is this the current translation? (true: yes, null: no).
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=true, options={"comment": "Is this the current translation? (true: yes, null: no)"})
     */
    protected ?bool $current;

    /**
     * Since when is this the current translation?
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=true, options={"comment": "Since when is this the current translation?"})
     */
    protected ?DateTimeImmutable $currentSince;

    /**
     * Translation is approved (true: yes, false: no, null: pending review).
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=true, options={"comment": "Translation is approved (true: yes, false: no, null: pending review)"})
     */
    protected ?bool $approved;

    /**
     * Translation (singular / plural 0).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Translation (singular / plural 0)"})
     */
    protected string $text0;

    /**
     * Translation (plural 1).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Translation (plural 1)"})
     */
    protected string $text1;

    /**
     * Translation (plural 2).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Translation (plural 2)"})
     */
    protected string $text2;

    /**
     * Translation (plural 3).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Translation (plural 3)"})
     */
    protected string $text3;

    /**
     * Translation (plural 4).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Translation (plural 4)"})
     */
    protected string $text4;

    /**
     * Translation (plural 5).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Translation (plural 5)"})
     */
    protected string $text5;

    public function __construct(Locale $locale, Translatable $translatable, string $singularText, ?UserEntity $createdBy = null)
    {
        $this->id = null;
        $this->locale = $locale;
        $this->translatable = $translatable;
        $this->createdOn = new DateTimeImmutable();
        $this->createdBy = $createdBy;
        $this->current = null;
        $this->currentSince = null;
        $this->approved = null;
        $this->text0 = $singularText;
        $this->text1 = '';
        $this->text2 = '';
        $this->text3 = '';
        $this->text4 = '';
        $this->text5 = '';
    }

    /**
     * Get the translation ID.
     */
    public function getID(): ?int
    {
        return $this->id;
    }

    /**
     * Get the associated locale.
     */
    public function getLocale(): Locale
    {
        return $this->locale;
    }

    /**
     * Get the associated Translatable.
     */
    public function getTranslatable(): Translatable
    {
        return $this->translatable;
    }

    /**
     * Get the record creation date/time.
     */
    public function getCreatedOn(): DateTimeImmutable
    {
        return $this->createdOn;
    }

    /**
     * Get the user that submitted this translation (if available).
     */
    public function getCreatedBy(): ?UserEntity
    {
        return $this->createdBy;
    }

    /**
     * Is this the current translation?
     *
     * @return $this
     */
    public function setIsCurrent(bool $value): self
    {
        $this->current = $value ? true : null;

        return $this;
    }

    /**
     * Is this the current translation?
     */
    public function isCurrent(): bool
    {
        return $this->current === null ? false : $this->current;
    }

    /**
     * Since when is this the current translation?
     */
    public function getCurrentSince(): ?DateTimeImmutable
    {
        return $this->currentSince;
    }

    /**
     * Is the translation approved? (true: yes, false: no, null: pending review).
     *
     * @return $this
     */
    public function setIsApproved(?bool $value): self
    {
        $this->approved = $value;

        return $this;
    }

    /**
     * Is the translation approved? (true: yes, false: no, null: pending review).
     */
    public function isApproved(): ?bool
    {
        return $this->approved;
    }

    /**
     * Set the translation (singular / plural 0).
     *
     * @return $this
     */
    public function setText0(string $value): self
    {
        $this->text0 = $value;

        return $this;
    }

    /**
     * Get the translation (singular / plural 0).
     */
    public function getText0(): string
    {
        return $this->text0;
    }

    /**
     * Set the translation (plural 1).
     *
     * @return $this
     */
    public function setText1(string $value): self
    {
        $this->text1 = $value;

        return $this;
    }

    /**
     * Get the translation (plural 1).
     */
    public function getText1(): string
    {
        return $this->text1;
    }

    /**
     * Set the translation (plural 2).
     *
     * @return $this
     */
    public function setText2(string $value): self
    {
        $this->text2 = $value;

        return $this;
    }

    /**
     * Get the translation (plural 2).
     */
    public function getText2(): string
    {
        return $this->text2;
    }

    /**
     * Set the translation (plural 3).
     *
     * @return $this
     */
    public function setText3(string $value): self
    {
        $this->text3 = $value;

        return $this;
    }

    /**
     * Get the translation (plural 3).
     */
    public function getText3(): string
    {
        return $this->text3;
    }

    /**
     * Set the translation (plural 4).
     *
     * @return $this
     */
    public function setText4(string $value): self
    {
        $this->text4 = $value;

        return $this;
    }

    /**
     * Get the translation (plural 4).
     */
    public function getText4(): string
    {
        return $this->text4;
    }

    /**
     * Set the translation (plural 5).
     *
     * @return $this
     */
    public function setText5(string $value): self
    {
        $this->text5 = $value;

        return $this;
    }

    /**
     * Get the translation (plural 5).
     */
    public function getText5(): string
    {
        return $this->text5;
    }
}
