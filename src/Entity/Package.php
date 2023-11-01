<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity;

use CommunityTranslation\Entity\Package\Version;
use CommunityTranslation\Service\VersionComparer;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represent a package that can have translations.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Package",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationPackages",
 *     uniqueConstraints={
 *         @Doctrine\ORM\Mapping\UniqueConstraint(name="IDX_CTPackageHandle", columns={"handle"})
 *     },
 *     options={
 *         "comment": "List of all package that can have translaitons"
 *     }
 * )
 */
class Package
{
    /**
     * Package ID.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned": true, "comment": "Package ID"})
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id;

    /**
     * Package handle.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=64, nullable=false, options={"comment": "Package handle"})
     */
    protected string $handle;

    /**
     * Package name.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment": "Package name"})
     */
    protected string $name;

    /**
     * Package URL.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", length=2000, nullable=false, options={"comment": "Package URL"})
     */
    protected string $url;

    /**
     * Record creation date/time.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=false, options={"comment": "Record creation date/time"})
     */
    protected DateTimeImmutable $createdOn;

    /**
     * Is this package created/updated from a remote package?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment": "Is this package created/updated from a remote package?"}))
     */
    protected bool $fromRemotePackage;

    /**
     * Is this package created/updated from a git repository?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment": "Is this package created/updated from a git repository?"}))
     */
    protected bool $fromGitRepository;

    /**
     * Is this package created/updated from an API request?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment": "Is this package created/updated from an API request?"}))
     */
    protected bool $fromApiRequest;

    /**
     * Latest package version.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Package\Version", inversedBy="package")
     * @Doctrine\ORM\Mapping\JoinColumn(name="latestVersion", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected ?Version $latestVersion;

    /**
     * Package versions.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Package\Version", mappedBy="package")
     */
    protected Collection $versions;

    /**
     * Package aliases.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Package\Alias", mappedBy="package")
     */
    protected Collection $aliases;

    public function __construct(string $handle, string $name, string $url = '')
    {
        $this->id = null;
        $this->handle = $handle;
        $this->name = $name;
        $this->url = $url;
        $this->fromRemotePackage = false;
        $this->fromGitRepository = false;
        $this->fromApiRequest = false;
        $this->latestVersion = null;
        $this->createdOn = new DateTimeImmutable();
        $this->versions = new ArrayCollection();
        $this->aliases = new ArrayCollection();
    }

    /**
     * Get the package ID.
     */
    public function getID(): ?int
    {
        return $this->id;
    }

    /**
     * Get the package handle.
     */
    public function getHandle(): string
    {
        return $this->handle;
    }

    /**
     * Set the package handle.
     *
     * @return $this
     */
    public function setHandle(string $value): self
    {
        $this->handle = $value;

        return $this;
    }

    /**
     * Get the package name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the package display name.
     */
    public function getDisplayName(): string
    {
        if ($this->name !== '') {
            return $this->name;
        }
        $segments = preg_split('/[_-]+/', $this->handle, -1, PREG_SPLIT_NO_EMPTY);
        if ($segments === []) {
            return $this->handle;
        }
        $parts = [];
        foreach ($segments as $segment) {
            $len = mb_strlen($segment);
            if ($len === 1) {
                $parts[] = mb_strtoupper($segment);
            } else {
                $parts[] = mb_strtoupper(mb_substr($segment, 0, 1)) . mb_substr($segment, 1);
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Set the package name.
     *
     * @return $this
     */
    public function setName(string $value): self
    {
        $this->name = $value;

        return $this;
    }

    /**
     * Get the package URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set the package URL.
     *
     * @return $this
     */
    public function setUrl(string $value): self
    {
        $this->url = $value;

        return $this;
    }

    /**
     * Get the record creation date/time.
     */
    public function getCreatedOn(): DateTimeImmutable
    {
        return $this->createdOn;
    }

    /**
     * Is this package created/updated from a remote package?
     */
    public function isFromRemotePackage(): bool
    {
        return $this->fromRemotePackage;
    }

    /**
     * Is this package created/updated from a remote package?
     *
     * @return $this
     */
    public function setFromRemotePackage(bool $value): self
    {
        $this->fromRemotePackage = $value;

        return $this;
    }

    /**
     * Is this package created/updated from a git repository?
     */
    public function isFromGitRepository()
    {
        return $this->fromGitRepository;
    }

    /**
     * Is this package created/updated from a git repository?
     *
     * @return $this
     */
    public function setFromGitRepository(bool $value): self
    {
        $this->fromGitRepository = $value;

        return $this;
    }

    /**
     * Is this package created/updated from a git repository?
     */
    public function isFromApiRequest()
    {
        return $this->fromApiRequest;
    }

    /**
     * Is this package created/updated from an API request?
     *
     * @return $this
     */
    public function setFromApiRequest(bool $value): self
    {
        $this->fromApiRequest = $value;

        return $this;
    }

    /**
     * Is this package created/updated from an API request?
     *
     * Get the latest package version.
     */
    public function getLatestVersion(): ?Version
    {
        return $this->latestVersion;
    }

    /**
     * Set the latest package version.
     *
     * @return $this
     */
    public function setLatestVersion(?Version $value): self
    {
        $this->latestVersion = $value;

        return $this;
    }

    /**
     * Get the package versions.
     *
     * @return \CommunityTranslation\Entity\Package\Version[]
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    /**
     * Get the package aliases.
     *
     * @return \CommunityTranslation\Entity\Package\Alias[]
     */
    public function getAliases(): Collection
    {
        return $this->aliases;
    }

    /**
     * Get the development versions, sorted in ascending or descending order.
     *
     * @return \CommunityTranslation\Entity\Package\Version[]
     */
    public function getSortedDevelopmentVersions(bool $descending = false): array
    {
        foreach ($this->getVersions() as $v) {
            if (strpos($v->getVersion(), Package\Version::DEV_PREFIX) === 0) {
                $versions[] = $v;
            }
        }
        usort(
            $versions,
            static function (Package\Version $a, Package\Version $b) use ($descending): int {
                $cmp = version_compare($a->getVersion(), $b->getVersion());

                return $descending ? -$cmp : $cmp;
            }
        );

        return $versions;
    }

    /**
     * Get the production versions, sorted in ascending or descending order.
     *
     * @return \CommunityTranslation\Entity\Package\Version[]
     */
    public function getSortedProductionVersions(bool $descending = false): array
    {
        $versions = [];
        foreach ($this->getVersions() as $v) {
            if (strpos($v->getVersion(), Package\Version::DEV_PREFIX) !== 0) {
                $versions[] = $v;
            }
        }
        usort(
            $versions,
            static function (Package\Version $a, Package\Version $b) use ($descending): int {
                $cmp = version_compare($a->getVersion(), $b->getVersion());

                return $descending ? -$cmp : $cmp;
            }
        );

        return $versions;
    }

    /**
     * Get the versions, sorted in ascending or descending order, with development or production versions first.
     *
     * @param bool|null $developmentVersionsFirst If null, development versions are placed among the production versions
     *
     * @return \CommunityTranslation\Entity\Package\Version[]
     */
    public function getSortedVersions(bool $descending = false, ?bool $developmentVersionsFirst = null): array
    {
        if ($developmentVersionsFirst === null) {
            $versionComparer = new VersionComparer();

            return $versionComparer->sortPackageVersionEntities($this->getVersions()->toArray(), $descending);
        }
        $devVersions = $this->getSortedDevelopmentVersions($descending);
        $prodVersions = $this->getSortedProductionVersions($descending);

        return array_merge(
            $developmentVersionsFirst ? $devVersions : $prodVersions,
            $developmentVersionsFirst ? $prodVersions : $devVersions
        );
    }
}
