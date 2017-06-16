<?php

namespace CommunityTranslation\Entity;

use Concrete\Core\Entity\User\User;
use Concrete\Core\Error\UserMessageException;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gettext\Languages\Language as GettextLanguage;
use Punic\Language;

/**
 * Represents a locale.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Locale",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationLocales",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="IDX_CTLocaleIssource", columns={"isSource"})
 *     },
 *     options={"comment": "Defined locales for the Community Translation package"}
 * )
 */
class Locale
{
    /**
     * Create a new (unsaved and unapproved) Locale instance given its locale ID.
     *
     * @param string id
     *
     * @return static
     */
    public static function create($id)
    {
        $language = GettextLanguage::getById($id);
        if ($language === null) {
            throw new UserMessageException(t('Unknown locale identifier: %s', $id));
        }
        $pluralForms = [];
        foreach ($language->categories as $category) {
            $pluralForms[] = $category->id . ':' . $category->examples;
        }
        $result = new static();
        $result->id = $language->id;
        $result
            ->setIsApproved(false)
            ->setIsSource(false)
            ->setName($language->name)
            ->setPluralForms($pluralForms)
            ->setPluralFormula($language->formula)
            ->setRequestedBy(null)
            ->setRequestedOn(null)
        ;

        return $result;
    }

    protected function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->stats = new ArrayCollection();
        $this->glossaryEntries = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    /**
     * Locale identifier.
     *
     * @ORM\Column(type="string", length=12, options={"comment": "Locale identifier"})
     * @ORM\Id
     *
     * @var string
     */
    protected $id;

    /**
     * Get the locale identifier.
     *
     * @return string
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Locale English name.
     *
     * @ORM\Column(type="string", length=100, nullable=false, options={"comment": "Locale English name"})
     *
     * @var string
     */
    protected $name;

    /**
     * Get the locale English name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the localized name of this locale.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return Language::getName($this->id);
    }

    /**
     * Set the locale English name.
     *
     * @param string $value
     *
     * @return static
     */
    public function setName($value)
    {
        $this->name = (string) $value;

        return $this;
    }

    /**
     * Is this the source locale? (One and only one locale should have this).
     *
     * @ORM\Column(type="boolean", nullable=true, options={"comment": "Is this the source locale? (One and only one locale should have this)"}))
     *
     * @var bool
     */
    protected $isSource;

    /**
     * Is this the source locale? (One and only one locale should have this).
     *
     * @return bool
     */
    public function isSource()
    {
        return (bool) $this->isSource;
    }

    /**
     * Is this the source locale? (One and only one locale should have this).
     *
     * @param bool $value
     *
     * @return static
     */
    public function setIsSource($value)
    {
        $this->isSource = $value ? true : null;

        return $this;
    }

    /**
     * Plural forms.
     *
     * @ORM\Column(type="array", nullable=false, options={"comment": "Plural forms"})
     *
     * @var string
     */
    protected $pluralForms;

    /**
     * Get the plural forms.
     *
     * @return string[]
     */
    public function getPluralForms()
    {
        return $this->pluralForms;
    }

    /**
     * Get the number of plural forms.
     *
     * @return int
     */
    public function getPluralCount()
    {
        return count($this->getPluralForms());
    }

    /**
     * Set the plural forms.
     *
     * @param string[] $value
     *
     * @return static
     */
    public function setPluralForms(array $value)
    {
        $this->pluralForms = $value;

        return $this;
    }

    /**
     * The formula used to to determine the plural case.
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "The formula used to to determine the plural case"})
     *
     * @var string
     */
    protected $pluralFormula;

    /**
     * Get the formula used to to determine the plural case.
     *
     * @return string
     */
    public function getPluralFormula()
    {
        return $this->pluralFormula;
    }

    /**
     * Set the formula used to to determine the plural case.
     *
     * @param string $value
     *
     * @return static
     */
    public function setPluralFormula($value)
    {
        $this->pluralFormula = (string) $value;

        return $this;
    }

    /**
     * Is this locale approved?
     *
     * @ORM\Column(type="boolean", nullable=false, options={"comment": "Is this locale approved?"})
     *
     * @var bool
     */
    protected $isApproved;

    /**
     * Is this locale approved?
     *
     * @return bool
     */
    public function isApproved()
    {
        return $this->isApproved;
    }

    /**
     * Is this locale approved?
     *
     * @param bool $value
     *
     * @return static
     */
    public function setIsApproved($value)
    {
        $this->isApproved = (bool) $value;

        return $this;
    }

    /**
     * User that requested this locale.
     *
     * @ORM\ManyToOne(targetEntity="Concrete\Core\Entity\User\User")
     * @ORM\JoinColumn(name="requestedBy", referencedColumnName="uID", nullable=true, onDelete="SET NULL")
     *
     * @var User|null
     */
    protected $requestedBy;

    /**
     * Get the user that requested this locale.
     *
     * @return User|null
     */
    public function getRequestedBy()
    {
        return $this->requestedBy;
    }

    /**
     * Set the user that requested this locale.
     *
     * @param User|null $value
     *
     * @return static
     */
    public function setRequestedBy(User $value = null)
    {
        $this->requestedBy = $value;

        return $this;
    }

    /**
     * When has this locale been requested?
     *
     * @ORM\Column(type="datetime", nullable=true, options={"comment": "When has this locale been requested?"})
     *
     * @var string
     */
    protected $requestedOn;

    /**
     * Get the date/time when has this locale been requested.
     *
     * @return DateTime
     */
    public function getRequestedOn()
    {
        return $this->requestedOn;
    }

    /**
     * Set the date/time when has this locale been requested.
     *
     * @param DateTime|null $value
     *
     * @return static
     */
    public function setRequestedOn(DateTime $value = null)
    {
        $this->requestedOn = $value;

        return $this;
    }

    /**
     * Translations associated to this locale.
     *
     * @ORM\OneToMany(targetEntity="CommunityTranslation\Entity\Translation", mappedBy="locale")
     *
     * @var ArrayCollection
     */
    protected $translations;

    /**
     * Stats associated to this locale.
     *
     * @ORM\OneToMany(targetEntity="CommunityTranslation\Entity\Stats", mappedBy="locale")
     *
     * @var ArrayCollection
     */
    protected $stats;

    /**
     * Localized Glossary Entries associated to this locale.
     *
     * @ORM\OneToMany(targetEntity="CommunityTranslation\Entity\Glossary\Entry\Localized", mappedBy="locale")
     *
     * @var ArrayCollection
     */
    protected $glossaryEntries;

    /**
     * Localized comments about translations.
     *
     * @ORM\OneToMany(targetEntity="CommunityTranslation\Entity\Translatable\Comment", mappedBy="locale")
     *
     * @var ArrayCollection
     */
    protected $comments;

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->id;
    }
}
