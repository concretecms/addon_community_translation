<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity\Translatable;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Translatable as TranslatableEntity;
use Concrete\Core\Entity\User\User as UserEntity;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Comment associated to a translatable string.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Translatable\Comment",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationTranslatableComments",
 *     options={"comment": "Comment associated to a translatable string"}
 * )
 */
class Comment
{
    /**
     * Comment ID.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned": true, "comment": "Comment ID"})
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id;

    /**
     * Associated Translatable string.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Translatable", inversedBy="comments")
     * @Doctrine\ORM\Mapping\JoinColumn(name="translatable", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected TranslatableEntity $translatable;

    /**
     * Associated Locale (null for global comments).
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale", inversedBy="comments")
     * @Doctrine\ORM\Mapping\JoinColumn(name="locale", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     */
    protected ?LocaleEntity $locale;

    /**
     * Parent comment (null for root comments).
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Translatable\Comment", inversedBy="childComments")
     * @Doctrine\ORM\Mapping\JoinColumn(name="parentComment", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     */
    protected ?Comment $parentComment;

    /**
     * Child comments.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Translatable\Comment", mappedBy="parentComment")
     */
    protected Collection $childComments;

    /**
     * Record creation date/time.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=false, options={"comment": "Record creation date/time"})
     */
    protected DateTimeImmutable $postedOn;

    /**
     * User that posted this comment.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Concrete\Core\Entity\User\User")
     * @Doctrine\ORM\Mapping\JoinColumn(name="postedBy", referencedColumnName="uID", nullable=true, onDelete="SET NULL")
     */
    protected ?UserEntity $postedBy;

    /**
     * Comment text.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Comment text"})
     */
    protected string $text;

    /**
     * Create a new (unsaved) entity.
     *
     * @param \CommunityTranslation\Entity\Translatable $translatable The associated translatable string
     * @param \Concrete\Core\Entity\User\User $postedBy The user that posted the comment
     * @param \CommunityTranslation\Entity\Locale|null $locale NULL if it's a global comment, the locale instance if it's translation-specific
     * @param \CommunityTranslation\Entity\Translatable\Comment|null $parentComment The parent comment (if this is a followup) or null
     */
    public function __construct(TranslatableEntity $translatable, UserEntity $postedBy, string $text, ?LocaleEntity $locale = null, ?self $parentComment = null)
    {
        $this->id = null;
        $this->translatable = $translatable;
        $this->locale = $locale;
        $this->parentComment = $parentComment;
        $this->postedOn = new DateTimeImmutable();
        $this->postedBy = $postedBy;
        $this->text = $text;
        $this->childComments = new ArrayCollection();
    }

    /**
     * Get the comment ID.
     */
    public function getID(): ?int
    {
        return $this->id;
    }

    /**
     * Get the associated Translatable string.
     */
    public function getTranslatable(): TranslatableEntity
    {
        return $this->translatable;
    }

    /**
     * Set the associated Locale (null for global comments).
     *
     * @return $this
     */
    public function setLocale(?LocaleEntity $value = null): self
    {
        $this->locale = $value;

        return $this;
    }

    /**
     * Get the associated Locale (null for global comments).
     */
    public function getLocale(): ?LocaleEntity
    {
        return $this->locale;
    }

    /**
     * Get the parent comment (null for root comments).
     */
    public function getParentComment(): ?self
    {
        return $this->parentComment;
    }

    /**
     * Get the root comment (this instance itself if it's the root one).
     */
    public function getRootComment(): self
    {
        $result = $this;
        while (($parent = $result->getParentComment()) !== null) {
            $result = $parent;
        }

        return $result;
    }

    /**
     * Return the child comments of this comment.
     *
     * @return \CommunityTranslation\Entity\Translatable\Comment[]
     */
    public function getChildComments(): Collection
    {
        return $this->childComments;
    }

    /**
     * Get the record creation date/time.
     */
    public function getPostedOn(): DateTimeImmutable
    {
        return $this->postedOn;
    }

    /**
     * Get the User that posted this comment (null if the user has been deleted).
     */
    public function getPostedBy(): ?UserEntity
    {
        return $this->postedBy;
    }

    /**
     * Set the comment text.
     *
     * @return $this
     */
    public function setText(string $value): self
    {
        $this->text = $value;

        return $this;
    }

    /**
     * Get the comment text.
     */
    public function getText(): string
    {
        return $this->text;
    }
}
