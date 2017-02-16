<?php
namespace CommunityTranslation\Entity\Translatable;

use CommunityTranslation\Entity\Locale;
use CommunityTranslation\Entity\Translatable;
use Concrete\Core\Entity\User\User;
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
    public function __construct()
    {
        $this->childComments = new ArrayCollection();
        $this->postedOn = new DateTime();
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
    protected $id = null;

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
     * @var Translatable
     */
    protected $translatable = null;

    /**
     * Get the associated Translatable string.
     *
     * @return Translatable
     */
    public function getTranslatable()
    {
        return $this->translatable;
    }

    /**
     * Set the associated Translatable string.
     *
     * @param Translatable $value
     *
     * @return static
     */
    public function setTranslatable(Translatable $value)
    {
        $this->translatable = $value;

        return $this;
    }

    /**
     * Associated Locale (null for global comments).
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale", inversedBy="comments")
     * @ORM\JoinColumn(name="locale", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     *
     * @var Locale|null
     */
    protected $locale = null;

    /**
     * Get the associated Locale (null for global comments).
     *
     * @return Locale|null
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set the associated Locale (null for global comments).
     *
     * @param Locale|null $value
     *
     * @return static
     */
    public function setLocale(Locale $value = null)
    {
        $this->locale = $value;

        return $this;
    }

    /**
     * Parent comment (null for root comments).
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Translatable\Comment", inversedBy="childComments")
     * @ORM\JoinColumn(name="parentComment", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     *
     * @var Comment|null
     */
    protected $parentComment = null;

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
     * Get the parent comment (null for root comments).
     *
     * @param Comment|null $value
     *
     * @return static
     */
    public function setParentComment(Comment $value = null)
    {
        $this->parentComment = $value;

        return $this;
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
     * Set the record creation date/time.
     *
     * @param DateTime $value
     *
     * @return static
     */
    public function setPostedOn(DateTime $value)
    {
        $this->postedOn = $value;

        return $this;
    }

    /**
     * User that posted this comment.
     *
     * @ORM\ManyToOne(targetEntity="Concrete\Core\Entity\User\User")
     * @ORM\JoinColumn(name="postedBy", referencedColumnName="uID", nullable=true, onDelete="SET NULL")
     *
     * @var User|null
     */
    protected $postedBy = null;

    /**
     * Get the User that posted this comment.
     *
     * @return User|null
     */
    public function getPostedBy()
    {
        return $this->postedBy;
    }

    /**
     * Set the User that posted this comment.
     *
     * @param User|null $value
     *
     * @return static
     */
    public function setPostedBy(User $value = null)
    {
        $this->postedBy = $value;

        return $this;
    }

    /**
     * Comment text.
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Comment text"})
     *
     * @var string
     */
    protected $text = '';

    /**
     * Get the comment text.
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

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
}
