<?php
namespace Concrete\Package\CommunityTranslation\Src\Translatable\Comment;

use Concrete\Package\CommunityTranslation\Src\Translatable\Translatable;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Doctrine\Common\Collections\ArrayCollection;
use DateTime;

/**
 * Comment associated to a translatable string.
 *
 * @Entity
 * @Table(
 *     name="TranslatableComments",
 *     options={"comment": "Comment associated to a translatable string"}
 * )
 */
class Comment
{
    // Properties

    /**
     * Comment ID.
     *
     * @Id @Column(type="integer", options={"unsigned": true, "comment": "Comment ID"})
     * @GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $tcID;

    /**
     * Associated Translatable string.
     *
     * @ManyToOne(targetEntity="Concrete\Package\CommunityTranslation\Src\Translatable\Translatable", inversedBy="comments")
     * @JoinColumn(name="tcTranslatable", referencedColumnName="tID", nullable=false, onDelete="CASCADE")
     *
     * @var Translatable
     */
    protected $tcTranslatable;

    /**
     * Associated Locale (null for global comments).
     *
     * @ManyToOne(targetEntity="Concrete\Package\CommunityTranslation\Src\Locale\Locale", inversedBy="comments")
     * @JoinColumn(name="tcLocale", referencedColumnName="lID", nullable=true, onDelete="CASCADE")
     *
     * @var Locale|null
     */
    protected $tcLocale;

    /**
     * Parent comment (null for root comments).
     *
     * @ManyToOne(targetEntity="Concrete\Package\CommunityTranslation\Src\Translatable\Comment\Comment", inversedBy="childComments")
     * @JoinColumn(name="tcParentComment", referencedColumnName="tcID", nullable=true, onDelete="CASCADE")
     *
     * @var Comment|null
     */
    protected $tcParentComment;

    /**
     * Child comments.
     *
     * @OneToMany(targetEntity="Concrete\Package\CommunityTranslation\Src\Translatable\Comment\Comment", mappedBy="tcParentComment")
     *
     * @var ArrayCollection
     */
    protected $childComments;

    /**
     * Record creation date/time.
     *
     * @Column(type="datetime", nullable=false, options={"comment": "Record creation date/time"})
     *
     * @var DateTime
     */
    protected $tcPostedOn;

    /**
     * ID of the user that posted this comment.
     *
     * @Column(type="integer", nullable=false, options={"unsigned": true, "comment": "ID of the user that posted this comment"})
     *
     * @var int
     */
    protected $tcPostedBy;

    /**
     * Comment text.
     *
     * @Column(type="text", nullable=false, options={"comment": "Comment text"})
     *
     * @var string
     */
    protected $tcText;

    // Constructors

    public function __construct()
    {
        $this->childComments = new ArrayCollection();
    }

    /**
     * @return self
     */
    public static function create(Translatable $translatable, Locale $locale = null, Comment $parentComment = null)
    {
        $new = new self();
        $new->tcTranslatable = $translatable;
        $new->tcLocale = $locale;
        $new->tcParentComment = $parentComment;
        $new->tcPostedOn = new DateTime();
        $me = new \User();
        $new->tcPostedBy = $me->isRegistered() ? (int) $me->getUserID() : null;
        $new->tcText = '';

        return $new;
    }

    // Getters and setters

    // Properties

    /**
     * Get the comment ID.
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->tcID;
    }

    /**
     * Get the associated Translatable string.
     *
     * @return Translatable
     */
    public function getTranslatable()
    {
        return $this->tcTranslatable;
    }

    /**
     * Get the associated Locale (null for global comments).
     *
     * @return Locale|null
     */
    public function getLocale()
    {
        return $this->tcLocale;
    }

    /**
     * Set the associated Locale (null for global comments).
     *
     * @param Locale|null $value
     */
    public function setLocale(Locale $value = null)
    {
        $this->tcLocale = $value;
    }

    /**
     * Get the parent comment (null for root comments).
     *
     * @return Comment|null
     */
    public function getParentComment()
    {
        return $this->tcParentComment;
    }

    /**
     * Return the child comments of this comment.
     *
     * @return Comment[]
     */
    public function getChildComments()
    {
        return $this->childComments->toArray();
    }

    /**
     * Get the record creation date/time.
     *
     * @return DateTime
     */
    public function getPostedOn()
    {
        return $this->tcPostedOn;
    }

    /**
     * Get the ID of the user that posted this comment.
     * 
     * @return int
     */
    public function getPostedBy()
    {
        return $this->tcPostedBy;
    }

    /**
     * Get the comment text.
     *
     * @return string
     */
    public function getText()
    {
        return $this->tcText;
    }

    /**
     * set the comment text.
     *
     * @param string $value
     */
    public function setText($value)
    {
        $this->tcText = trim((string) $value);
    }

}
