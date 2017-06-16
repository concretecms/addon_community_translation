<?php

namespace CommunityTranslation\Entity\Package;

use CommunityTranslation\Entity\Package;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represent a package version.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Package\Version",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationPackageVersions",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="IDX_CTPackageVersionPackageVersion", columns={"package", "version"})
 *     },
 *     options={
 *         "comment": "List of versions for each package"
 *     }
 * )
 */
class Version
{
    /**
     * Prefix for development versions.
     *
     * @var string
     */
    const DEV_PREFIX = 'dev-';

    /**
     * @param Package $package
     * @param string $version
     *
     * @return static
     */
    public static function create(Package $package, $version)
    {
        $result = new static();
        $result->package = $package;
        $result->version = (string) $version;
        $result->createdOn = new DateTime();
        $result->updatedOn = new DateTime();

        return $result;
    }

    protected function __construct()
    {
        $this->places = new ArrayCollection();
        $this->stats = new ArrayCollection();
    }

    /**
     * Package version ID.
     *
     * @ORM\Column(type="integer", options={"unsigned": true, "comment": "Package version ID"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $id = null;

    /**
     * Get the package version ID.
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Package associated to this version.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Package", inversedBy="versions")
     * @ORM\JoinColumn(name="package", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     *
     * @var Package|null
     */
    protected $package = null;

    /**
     * Get the package associated to this version.
     *
     * @return Package|null
     */
    public function getPackage()
    {
        return $this->package;
    }

    /**
     * Package version (starting with Version::DEV_PREFIX for development branches).
     *
     * @ORM\Column(type="string", length=64, nullable=false, options={"comment": "Package version (starting with Version::DEV_PREFIX for development branches)"})
     *
     * @var string
     */
    protected $version;

    /**
     * Get the package version.
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Get the package and package version display name.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return sprintf('%s %s', $this->getPackage()->getDisplayName(), $this->getDisplayVersion());
    }

    /**
     * Get the package version display name.
     *
     * @return string
     */
    public function getDisplayVersion()
    {
        if ($this->isDevVersion()) {
            return t(/*i18n: %s is a version*/'%s development series', substr($this->version, strlen(static::DEV_PREFIX)));
        } else {
            return $this->version;
        }
    }

    /**
     * Is this a development version?
     *
     * @return bool
     */
    public function isDevVersion()
    {
        return (strpos($this->version, static::DEV_PREFIX) === 0) ? true : false;
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
     * Last date/time when the translatable strings changed.
     *
     * @ORM\Column(type="datetime", nullable=false, options={"comment": "Last date/time when the translatable strings changed"})
     *
     * @var DateTime
     */
    protected $updatedOn;

    /**
     * Get the last date/time when the translatable strings changed.
     *
     * @return DateTime
     */
    public function getUpdatedOn()
    {
        return $this->updatedOn;
    }

    /**
     * Set the last date/time when the translatable strings changed.
     *
     * @param DateTime $value
     */
    public function setUpdatedOn(DateTime $value)
    {
        $this->updatedOn = $value;
    }

    /**
     * Places associated to this string.
     *
     * @ORM\OneToMany(targetEntity="CommunityTranslation\Entity\Translatable\Place", mappedBy="packageVersion")
     *
     * @var ArrayCollection
     */
    protected $places;

    /**
     * Stats associated to this string.
     *
     * @ORM\OneToMany(targetEntity="CommunityTranslation\Entity\Stats", mappedBy="packageVersion")
     *
     * @var ArrayCollection
     */
    protected $stats;
}
