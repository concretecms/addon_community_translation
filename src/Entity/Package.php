<?php
namespace CommunityTranslation\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represent a package that can have translations.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Package",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationPackages",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="IDX_CTPackageHandle", columns={"handle"}
 *         )
 *     },
 *     options={
 *         "comment": "List of all package that can have translaitons"
 *     }
 * )
 */
class Package
{
    /**
     * @param string $handle
     * @param string $name
     *
     * @return static
     */
    public static function create($handle, $name = '')
    {
        $result = new static();
        $result->handle = (string) $handle;
        $result->name = (string) $name;
        $result->createdOn = new DateTime();

        return $result;
    }

    protected function __construct()
    {
        $this->versions = new ArrayCollection();
    }

    /**
     * Package ID.
     *
     * @ORM\Column(type="integer", options={"unsigned": true, "comment": "Package ID"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $id;

    /**
     * Get the package ID.
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Package handle.
     *
     * @ORM\Column(type="string", length=64, nullable=false, options={"comment": "Package handle"})
     *
     * @var string
     */
    protected $handle;

    /**
     * Get the package handle.
     *
     * @return string
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * Set the package handle.
     *
     * @param string $value
     *
     * @return static
     */
    public function setHandle($value)
    {
        $this->handle = (string) $value;

        return $this;
    }

    /**
     * Package name.
     *
     * @ORM\Column(type="string", length=255, nullable=false, options={"comment": "Package name"})
     *
     * @var string
     */
    protected $name;

    /**
     * Get the package name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the package display name.
     *
     * @return string
     */
    public function getDisplayName()
    {
        if ($this->name !== '') {
            $result = $this->name;
        } else {
            $result = camelcase($this->handle);
        }

        return $result;
    }

    /**
     * Set the package name.
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
     * Package versions.
     *
     * @ORM\OneToMany(targetEntity="CommunityTranslation\Entity\Package\Version", mappedBy="package")
     *
     * @var ArrayCollection
     */
    protected $versions;

    /**
     * Get the package versions.
     *
     * @return \CommunityTranslation\Entity\Package\Version[]|ArrayCollection
     */
    public function getVersions()
    {
        return $this->versions;
    }

    /**
     * Get the development versions, sorted in ascending or descending order.
     *
     * @param bool $descending
     *
     * @return \CommunityTranslation\Entity\Package\Version[]
     */
    public function getSortedDevelopmentVersions($descending = false)
    {
        $versions = [];
        foreach ($this->getVersions() as $v) {
            if (strpos($v->getVersion(), Package\Version::DEV_PREFIX) === 0) {
                $versions[] = $v;
            }
        }
        usort($versions, function (Package\Version $a, Package\Version $b) use ($descending) {
            return version_compare($a->getVersion(), $b->getVersion()) * ($descending ? -1 : 1);
        });

        return $versions;
    }

    /**
     * Get the production versions, sorted in ascending or descending order.
     *
     * @param bool $descending
     *
     * @return \CommunityTranslation\Entity\Package\Version[]
     */
    public function getSortedProductionVersions($descending = false)
    {
        $versions = [];
        foreach ($this->getVersions() as $v) {
            if (strpos($v->getVersion(), Package\Version::DEV_PREFIX) !== 0) {
                $versions[] = $v;
            }
        }
        usort($versions, function (Package\Version $a, Package\Version $b) use ($descending) {
            return version_compare($a->getVersion(), $b->getVersion()) * ($descending ? -1 : 1);
        });

        return $versions;
    }

    /**
     * Get the versions, sorted in ascending or descending order, with development or production versions first.
     *
     * @param bool $descending
     * @param bool $developmentVersionsFirst
     *
     * @return array
     */
    public function getSortedVersions($descending = false, $developmentVersionsFirst = false)
    {
        $devVersions = $this->getSortedDevelopmentVersions($descending);
        $prodVersions = $this->getSortedProductionVersions($descending);

        if ($developmentVersionsFirst) {
            $result = array_merge($devVersions, $prodVersions);
        } else {
            $result = array_merge($prodVersions, $devVersions);
        }

        return $result;
    }
}
