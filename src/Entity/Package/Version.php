<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity\Package;

use CommunityTranslation\Entity\Package;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represent a package version.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Package\Version",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationPackageVersions",
 *     uniqueConstraints={
 *         @Doctrine\ORM\Mapping\UniqueConstraint(name="IDX_CTPackageVersionPackageVersion", columns={"package", "version"})
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
    public const DEV_PREFIX = 'dev-';

    /**
     * Package version ID.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned": true, "comment": "Package version ID"})
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id;

    /**
     * Package associated to this version.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Package", inversedBy="versions")
     * @Doctrine\ORM\Mapping\JoinColumn(name="package", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected Package $package;

    /**
     * Package version (starting with Version::DEV_PREFIX for development branches).
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=64, nullable=false, options={"comment": "Package version (starting with Version::DEV_PREFIX for development branches)"})
     */
    protected string $version;

    /**
     * Record creation date/time.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=false, options={"comment": "Record creation date/time"})
     */
    protected DateTimeImmutable $createdOn;

    /**
     * Last date/time when the translatable strings changed.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=false, options={"comment": "Last date/time when the translatable strings changed"})
     */
    protected DateTimeImmutable $updatedOn;

    /**
     * Places associated to this string.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Translatable\Place", mappedBy="packageVersion")
     */
    protected Collection $places;

    /**
     * Stats associated to this string.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Stats", mappedBy="packageVersion")
     */
    protected Collection $stats;

    public function __construct(Package $package, string $version)
    {
        $this->id = null;
        $this->package = $package;
        $this->version = $version;
        $this->createdOn = new DateTimeImmutable();
        $this->updatedOn = new DateTimeImmutable();
        $this->places = new ArrayCollection();
        $this->stats = new ArrayCollection();
    }

    /**
     * Get the package version ID.
     */
    public function getID(): ?int
    {
        return $this->id;
    }

    /**
     * Get the package associated to this version.
     */
    public function getPackage(): Package
    {
        return $this->package;
    }

    /**
     * Get the package version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get the package and package version display name.
     */
    public function getDisplayName(): string
    {
        return sprintf('%s %s', $this->getPackage()->getDisplayName(), $this->getDisplayVersion());
    }

    /**
     * Get the package version display name.
     */
    public function getDisplayVersion(): string
    {
        if ($this->isDevVersion()) {
            return t(/*i18n: %s is a version*/'%s development series', substr($this->version, strlen(static::DEV_PREFIX)));
        }

        return $this->version;
    }

    /**
     * Is this a development version?
     */
    public function isDevVersion(): bool
    {
        return strpos($this->version, static::DEV_PREFIX) === 0;
    }

    /**
     * Get the record creation date/time.
     */
    public function getCreatedOn(): DateTimeImmutable
    {
        return $this->createdOn;
    }

    /**
     * Get the last date/time when the translatable strings changed.
     */
    public function getUpdatedOn(): DateTimeImmutable
    {
        return $this->updatedOn;
    }

    /**
     * Set the last date/time when the translatable strings changed.
     *
     * @return $this
     */
    public function setUpdatedOn(DateTimeImmutable $value): self
    {
        $this->updatedOn = $value;
    }
}
