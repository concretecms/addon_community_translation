<?php
namespace CommunityTranslation\Entity;

use Concrete\Core\Entity\User\User;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an translated string.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Translation",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationTranslations",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="IDX_CTTranslationLocaleTranslatableCurrent", columns={"locale", "translatable", "current"})
 *     },
 *     options={"comment": "Translated strings"}
 * )
 */
class Translation
{
    /**
     * Translation ID.
     *
     * @ORM\Column(type="integer", options={"unsigned": true, "comment": "Translation ID"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $id;

    /**
     * Get the translation ID.
     *
     * @return int
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Associated Locale.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale", inversedBy="translations")
     * @ORM\JoinColumn(name="locale", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     *
     * @var string
     */
    protected $locale;

    /**
     * Get the associated locale.
     *
     * @return Locale
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Associated Translatable.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Translatable", inversedBy="translations")
     * @ORM\JoinColumn(name="translatable", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     *
     * @var int
     */
    protected $translatable;

    /**
     * Get the associated Translatable.
     *
     * @return Translatable
     */
    public function getTranslatable()
    {
        return $this->translatable;
    }

    /**
     * Record creation date/time.
     *
     * @ORM\Column(type="datetime", nullable=false, options={"comment": "Record creation date/time"})
     *
     * @var DateTime
     */
    protected $createdOn;

    /**
     * Get the record creation date/time.
     *
     * @return DateTime
     */
    public function getCreatedOn()
    {
        return $this->createdOn;
    }

    /**
     * User that submitted this translation.
     *
     * @ORM\ManyToOne(targetEntity="Concrete\Core\Entity\User\User")
     * @ORM\JoinColumn(name="createdBy", referencedColumnName="uID", nullable=true, onDelete="SET NULL")
     *
     * @var User|null
     */
    protected $createdBy;

    /**
     * Get the ID of the user that submitted this translation.
     *
     * @return int
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * Is this the current translation? (true: yes, null: no).
     *
     * @ORM\Column(type="boolean", nullable=true, options={"comment": "Is this the current translation? (true: yes, null: no)"})
     *
     * @var true|null
     */
    protected $current;

    /**
     * Is this the current translation?
     *
     * @return bool
     */
    public function isCurrent()
    {
        return (bool) $this->current;
    }

    /**
     * Since when is this the current translation?
     *
     * @ORM\Column(type="datetime", nullable=true, options={"comment": "Since when is this the current translation?"})
     *
     * @var DateTime|null
     */
    protected $currentSince;

    /**
     * Since when is this the current translation?
     *
     * @return DateTime|null
     */
    public function getCurrentSince()
    {
        return $this->currentSince;
    }

    /**
     * Translation is approved (true: yes, false: no, null: pending review).
     *
     * @ORM\Column(type="boolean", nullable=true, options={"comment": "Translation is approved (true: yes, false: no, null: pending review)"})
     *
     * @var bool|null
     */
    protected $approved;

    /**
     * Is the translation approved? (true: yes, false: no, null: pending review).
     *
     * @param bool|null $value
     *
     * @return static
     */
    public function setIsApproved($value)
    {
        if ($value === null || $value === '') {
            $this->approved = null;
        } else {
            $this->approved = (bool) $value;
        }

        return $this;
    }

    /**
     * Is the translation approved? (true: yes, false: no, null: pending review).
     *
     * @return bool|null
     */
    public function isApproved()
    {
        return $this->approved;
    }

    /**
     * Translation (singular / plural 0).
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Translation (singular / plural 0)"})
     *
     * @var string
     */
    protected $text0;

    /**
     * Set the translation (singular / plural 0).
     *
     * @param string $value
     *
     * @return static
     */
    public function setText0($value)
    {
        $this->text0 = (string) $value;

        return $this;
    }

    /**
     * Get the translation (singular / plural 0).
     *
     * @return string
     */
    public function getText0()
    {
        return $this->text0;
    }

    /**
     * Translation (plural 1).
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Translation (plural 1)"})
     *
     * @var string
     */
    protected $text1;

    /**
     * Set the translation (plural 1).
     *
     * @param string $value
     *
     * @return static
     */
    public function setText1($value)
    {
        $this->text1 = (string) $value;

        return $this;
    }

    /**
     * Get the translation (plural 1).
     *
     * @return string
     */
    public function getText1()
    {
        return $this->text1;
    }

    /**
     * Translation (plural 2).
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Translation (plural 2)"})
     *
     * @var string
     */
    protected $text2;

    /**
     * Set the translation (plural 2).
     *
     * @param string $value
     *
     * @return static
     */
    public function setText2($value)
    {
        $this->text2 = (string) $value;

        return $this;
    }

    /**
     * Get the translation (plural 2).
     *
     * @return string
     */
    public function getText2()
    {
        return $this->text2;
    }

    /**
     * Translation (plural 3).
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Translation (plural 3)"})
     *
     * @var string
     */
    protected $text3;

    /**
     * Set the translation (plural 3).
     *
     * @param string $value
     *
     * @return static
     */
    public function setText3($value)
    {
        $this->text3 = (string) $value;

        return $this;
    }

    /**
     * Get the translation (plural 3).
     *
     * @return string
     */
    public function getText3()
    {
        return $this->text3;
    }

    /**
     * Translation (plural 4).
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Translation (plural 4)"})
     *
     * @var string
     */
    protected $text4;

    /**
     * Set the translation (plural 4).
     *
     * @param string $value
     *
     * @return static
     */
    public function setText4($value)
    {
        $this->text4 = (string) $value;

        return $this;
    }

    /**
     * Get the translation (plural 4).
     *
     * @return string
     */
    public function getText4()
    {
        return $this->text4;
    }

    /**
     * Translation (plural 5).
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Translation (plural 5)"})
     *
     * @var string
     */
    protected $text5;

    /**
     * Set the translation (plural 5).
     *
     * @param string $value
     *
     * @return static
     */
    public function setText5($value)
    {
        $this->text5 = (string) $value;

        return $this;
    }

    /**
     * Get the translation (plural 5).
     *
     * @return string
     */
    public function getText5()
    {
        return $this->text5;
    }
}
