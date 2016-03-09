<?php
namespace Concrete\Package\CommunityTranslation\Src\Package;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Represents an package for which we have translatable strings.
 *
 * @Entity(
 *     repositoryClass="Concrete\Package\CommunityTranslation\Src\Package\Repository",
 * )
 *
 * @Table(
 *     name="TranslatedPackages",
 *     uniqueConstraints={@UniqueConstraint(name="TranslatedPackageHandleVersion", columns={"pHandle", "pVersion"})},
 *     options={"comment": "List of all package and core versions with translations"}
 * )
 */
class Package
{
    // Constants

    /*
     * Prefix for development versions.
     *
     * @var string
     */
    const DEV_PREFIX = 'dev-';

    // Properties

    /**
     * Package ID.
     *
     * @Id @Column(type="integer", options={"unsigned": true, "comment": "Package ID"})
     * @GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $pID;

    /**
     * Package handle (empty for core).
     *
     * @Column(type="string", length=64, nullable=false, options={"comment": "Package handle (empty for core)"})
     *
     * @var string
     */
    protected $pHandle;

    /**
     * Package version (starting with Package::DEV_PREFIX for development branches).
     *
     * @Column(type="string", length=64, nullable=false, options={"comment": "Package version (starting with Package::DEV_PREFIX for development branches)"})
     *
     * @var string
     */
    protected $pVersion;

    /**
     * Record creation date/time.
     *
     * @Column(type="datetime", nullable=false, options={"comment": "Record creation date/time"})
     *
     * @var DateTime
     */
    protected $pCreatedOn;

    /**
     * Last date/time when the translatable strings changed.
     *
     * @Column(type="datetime", nullable=false, options={"comment": "Last date/time when the translatable strings changed"})
     *
     * @var DateTime
     */
    protected $pUpdatedOn;

    /**
     * Places associated to this string.
     *
     * @OneToMany(targetEntity="Concrete\Package\CommunityTranslation\Src\Translatable\Place\Place", mappedBy="tpPackage")
     *
     * @var ArrayCollection
     */
    protected $places;

    /**
     * Stats associated to this string.
     *
     * @OneToMany(targetEntity="Concrete\Package\CommunityTranslation\Src\Stats\Stats", mappedBy="sPackage")
     *
     * @var ArrayCollection
     */
    protected $stats;

    // Constructor

    public function __construct()
    {
        $this->pCreatedOn = new DateTime();
        $this->pUpdatedOn = new DateTime();
        $this->places = new ArrayCollection();
        $this->stats = new ArrayCollection();
    }

    // Getters & setters

    /**
     * Get the package ID.
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->pID;
    }

    /**
     * Get the ackage handle (empty for core).
     *
     * @return string
     */
    public function getHandle()
    {
        return $this->pHandle;
    }

    /**
     * Get the package version (starting with Package::DEV_PREFIX for development branches).
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->pVersion;
    }

    /**
     * Get the package display name.
     *
     * @param bool $omitVersion
     *
     * @return string
     */
    public function getDisplayName($omitVersion = false)
    {
        $result = ($this->pHandle === '') ? t('concrete5 core') : $this->pHandle;
        if (!$omitVersion) {
            $result .= ' '.$this->getVersionDisplayName();
        }

        return $result;
    }

    /**
     * Get the package version display name
     *
     * @return string
     */
    public function getVersionDisplayName()
    {
        if ($this->isDevVersion()) {
            return t(/*i18n: %s is a version*/'%s development series', substr($this->pVersion, strlen(static::DEV_PREFIX)));
        } else {
            return $this->pVersion;
        }
    }

    /**
     * Is this a development version?
     *
     * @return bool
     */
    public function isDevVersion()
    {
        return (strpos($this->pVersion, static::DEV_PREFIX) === 0) ? true : false;
    }

    /**
     * Get the record creation date/time.
     *
     * @return DateTime
     */
    public function getCreatedOn()
    {
        return $this->pCreatedOn;
    }

    /**
     * Get the last date/time when the translatable strings changed.
     *
     * @return DateTime
     */
    public function getUpdatedOn()
    {
        return $this->pUpdatedOn;
    }

    /**
     * Set the last date/time when the translatable strings changed.
     *
     * @param DateTime $value
     */
    public function setUpdatedOn(DateTime $value)
    {
        $this->pUpdatedOn = $value;
    }

    /**
     * Create a new (unsaved) instance.
     *
     * @param string $handle
     * @param string $version
     *
     * @return self
     */
    public static function create($handle, $version)
    {
        $package = new self();
        $package->pHandle = trim((string) $handle);
        $package->pVersion = trim((string) $version);

        return $package;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->pHandle.'@'.$this->pVersion;
    }
}
