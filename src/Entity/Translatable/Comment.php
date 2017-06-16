<?php

namespace CommunityTranslation\Entity\Translatable;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Translatable as TranslatableEntity;
use Concrete\Core\Entity\User\User as UserEntity;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Comment associated to a translatable string.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Translatable\Comment",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationTranslatableComments",
 *     options={"comment": "Comment associated to a translatable string"}
 * )
 */
class Comment
{
    /**
     * Create a new (unsaved) entity.
     *
     * @param TranslatableEntity $translatable The associated translatable string
     * @param UserEntity $postedBy The user that posted the comment
     * @param LocaleEntity $locale NULL if it's a global comment, the locale instance if it's translation-specific
     * @param Comment $parentComment The parent comment (if this is a followup) or null
     *
     * @return static
     */
    public static function create(TranslatableEntity $translatable, UserEntity $postedBy, LocaleEntity $locale = null, Comment $parentComment = null)
    {
        $result = new static();
        $result->translatable = $translatable;
        $result->locale = $locale;
        $result->parentComment = $parentComment;
        $result->postedOn = new DateTime();
        $result->postedBy = $postedBy;
        $result->text = null;

        return $result;
    }

    protected function __construct()
    {
        $this->childComments = new ArrayCollection();
    }

    /**
     * Comment ID.
     *
     * @ORM\Column(type="integer", options={"unsigned": true, "comment": "Comment ID"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $id;

    /**
     * Get the comment ID.
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Associated Translatable string.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Translatable", inversedBy="comments")
     * @ORM\JoinColumn(name="translatable", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     *
     * @var TranslatableEntity
     */
    protected $translatable;

    /**
     * Get the associated Translatable string.
     *
     * @return TranslatableEntity
     */
    public function getTranslatable()
    {
        return $this->translatable;
    }

    /**
     * Associated Locale (null for global comments).
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale", inversedBy="comments")
     * @ORM\JoinColumn(name="locale", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     *
     * @var LocaleEntity|null
     */
    protected $locale;

    /**
     * Set the associated Locale (null for global comments).
     *
     * @param LocaleEntity|null $value
     *
     * @return static
     */
    public function setLocale(LocaleEntity $value = null)
    {
        $this->locale = $value;

        return $this;
    }

    /**
     * Get the associated Locale (null for global comments).
     *
     * @return LocaleEntity|null
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Parent comment (null for root comments).
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Translatable\Comment", inversedBy="childComments")
     * @ORM\JoinColumn(name="parentComment", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     *
     * @var Comment|null
     */
    protected $parentComment;

    /**
     * Get the parent comment (null for root comments).
     *
     * @return Comment|null
     */
    public function getParentComment()
    {
        return $this->parentComment;
    }

    /**
     * Get the root comment (this instance itself if it's the root one).
     *
     * @return Comment
     */
    public function getRootComment()
    {
        $result = $this;
        while (($parent = $result->getParentComment()) !== null) {
            $result = $parent;
        }

        return $result;
    }

    /**
     * Child comments.
     *
     * @ORM\OneToMany(targetEntity="CommunityTranslation\Entity\Translatable\Comment", mappedBy="parentComment")
     *
     * @var ArrayCollection
     */
    protected $childComments;

    /**
     * Return the child comments of this comment.
     *
     * @return Comment[]|ArrayCollection
     */
    public function getChildComments()
    {
        return $this->childComments;
    }

    /**
     * Record creation date/time.
     *
     * @ORM\Column(type="datetime", nullable=false, options={"comment": "Record creation date/time"})
     *
     * @var DateTime
     */
    protected $postedOn;

    /**
     * Get the record creation date/time.
     *
     * @return DateTime
     */
    public function getPostedOn()
    {
        return $this->postedOn;
    }

    /**
     * User that posted this comment.
     *
     * @ORM\ManyToOne(targetEntity="Concrete\Core\Entity\User\User")
     * @ORM\JoinColumn(name="postedBy", referencedColumnName="uID", nullable=true, onDelete="SET NULL")
     *
     * @var UserEntity|null
     */
    protected $postedBy;

    /**
     * Get the User that posted this comment (null if the user has been deleted).
     *
     * @return UserEntity|null
     */
    public function getPostedBy()
    {
        return $this->postedBy;
    }

    /**
     * Comment text.
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Comment text"})
     *
     * @var string
     */
    protected $text;

    /**
     * Set the comment text.
     *
     * @param string $value
     *
     * @return static
     */
    public function setText($value)
    {
        $this->text = (string) $value;

        return $this;
    }

    /**
     * Get the comment text.
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }
}
