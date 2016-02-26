<?php
namespace Concrete\Package\CommunityTranslation\Src\Locale;

use DateTime;
use Concrete\Package\CommunityTranslation\Src\Exception;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Represents an locale.
 *
 * @Entity
 * @Table(name="Locales", options={"comment": "Defined locales for the Community Translation package"})
 */
class Locale
{
    // Properties

    /**
     * Locale identifier.
     *
     * @Id @Column(type="string", length=12, options={"comment": "Locale identifier"})
     *
     * @var string
     */
    protected $lID;

    /**
     * Locale English name.
     *
     * @Column(type="string", length=100, nullable=false, options={"comment": "Locale English name"})
     *
     * @var string
     */
    protected $lName;

    /**
     * Is this the source locale? (Only en_US should have this).
     *
     * @Column(type="boolean", nullable=false, options={"comment": "Is this the source locale? (Only en_US should have this)"}))
     * 
     * @var bool
     */
    protected $lIsSource;

    /**
     * Plural forms.
     *
     * @Column(type="array", nullable=false, options={"comment": "Plural forms"})
     * 
     * @var string
     */
    protected $lPluralForms;

    /**
     * The formula used to to determine the plural case.
     * 
     * @Column(type="text", nullable=false, options={"comment": "The formula used to to determine the plural case"})
     *
     * @var string
     */
    protected $lPluralFormula;

    /**
     * Is this locale approved?
     * 
     * @Column(type="boolean", nullable=false, options={"comment": "Is this locale approved?"})
     *
     * @var bool
     */
    protected $lIsApproved;

    /**
     * ID of the user that requested this locale.
     * 
     * @Column(type="integer", nullable=false, options={"unsigned": true, "comment": "ID of the user that requested this locale"})
     *
     * @var int|null
     */
    protected $lRequestedBy;

    /**
     * When has this locale been requested?
     * 
     * @Column(type="datetime", nullable=false, options={"comment": "When has this locale been requested?"})
     *
     * @var string
     */
    protected $lRequestedOn;

    /**
     * Translations associated to this locale.
     *
     * @OneToMany(targetEntity="Concrete\Package\CommunityTranslation\Src\Translation\Translation", mappedBy="tLocale")
     */
    protected $translations;

    // Constructor

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    // Getters & setters

    /**
     * Get the locale identifier.
     *
     * @return string 
     */
    public function getID()
    {
        return $this->lID;
    }

    /**
     * Set the locale identifier.
     *
     * @param string $value
     */
    public function setID($value)
    {
        $this->lID = (string) $value;
    }

    /**
     * Get the locale English name.
     *
     * @return string 
     */
    public function getName()
    {
        return $this->lName;
    }

    /**
     * Get the localized name of this locale.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return \Punic\Language::getName($this->lID);
    }

    /**
     * Set the locale English name.
     *
     * @param string $value
     */
    public function setName($value)
    {
        $this->lName = (string) $value;
    }

    /**
     * Is this the source locale? (Only en_US should have this).
     *
     * @return bool
     */
    public function isSource()
    {
        return $this->lIsSource;
    }

    /**
     * Is this the source locale? (Only en_US should have this).
     *
     * @param bool $value
     */
    public function setIsSource($value)
    {
        $this->lIsSource = (bool) $value;
    }

    /**
     * Get the plural forms.
     *
     * @return string[]
     */
    public function getPluralForms()
    {
        return $this->lPluralForms;
    }

    /**
     * Set the plural forms.
     *
     * @param string[] $value
     */
    public function setPluralForms(array $value)
    {
        $this->lPluralForms = $value;
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
     * Get the formula used to to determine the plural case.
     *
     * @return string 
     */
    public function getPluralFormula()
    {
        return $this->lPluralFormula;
    }

    /**
     * Set the formula used to to determine the plural case.
     *
     * @param string $value
     */
    public function setPluralFormula($value)
    {
        $this->lPluralFormula = (string) $value;
    }

    /**
     * Is this locale approved?
     *
     * @return bool
     */
    public function isApproved()
    {
        return $this->lIsApproved;
    }

    /**
     * Is this locale approved?
     *
     * @param bool $value
     */
    public function setIsApproved($value)
    {
        $this->lIsApproved = (bool) $value;
    }

    /**
     * Get ID of the user that requested this locale.
     *
     * @return int
     */
    public function getRequestedBy()
    {
        return $this->lRequestedBy;
    }

    /**
     * Set ID of the user that requested this locale.
     *
     * @param int $value
     */
    public function setRequestedBy($value)
    {
        $this->lRequestedBy = (int) $value;
    }

    /**
     * Get the date/time when has this locale been requested.
     *
     * @return DateTime
     */
    public function getRequestedOn()
    {
        return $this->lRequestedOn;
    }

    /**
     * Set the date/time when has this locale been requested.
     *
     * @param DateTime $value
     */
    public function setRequestedOn(DateTime $value)
    {
        $this->lRequestedOn = $value;
    }

    /**
     * Create a new (unsaved and unapproved) Locale instance given its locale ID.
     *
     * @return self
     */
    public static function createForLocaleID($id)
    {
        $language = \Gettext\Languages\Language::getById($id);
        if ($language === null) {
            throw new Exception(t('Unknown language identifier: %s', $id));
        }
        $pluralForms = array();
        foreach ($language->categories as $category) {
            $pluralForms[] = $category->id.':'.$category->examples;
        }
        $locale = new self();
        $locale->setID($language->id);
        $locale->setName($language->name);
        $locale->setIsSource(false);
        $locale->setPluralForms($pluralForms);
        $locale->setPluralFormula($language->formula);
        $locale->setIsApproved(false);
        $by = new \User();
        $locale->setRequestedBy($by->isRegistered() ? $by->getUserID() : USER_SUPER_ID);
        $locale->setRequestedOn(new DateTime());

        return $locale;
    }
}
