<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity\Package;

use CommunityTranslation\Entity\Package;
use DateTimeImmutable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represent an alias of a package.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Package\Alias",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationPackageAliases",
 *     options={
 *         "comment": "List of versions package aliases"
 *     }
 * )
 */
class Alias
{
    /**
     * Actual package associated to this alias.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Package", inversedBy="aliases")
     * @Doctrine\ORM\Mapping\JoinColumn(name="package", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected Package $package;

    /**
     * Alias handle of the package.
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\Column(type="string", length=64, nullable=false, options={"comment": "Alias handle of the package"})
     */
    protected string $handle;

    /**
     * Record creation date/time.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=false, options={"comment": "Record creation date/time"})
     */
    protected DateTimeImmutable $createdOn;

    public function __construct(Package $package, string $handle)
    {
        $this->package = $package;
        $this->handle = $handle;
        $this->createdOn = new DateTimeImmutable();
    }

    /**
     * Get the actual package associated to this alias..
     */
    public function getPackage(): Package
    {
        return $this->package;
    }

    /**
     * Get the alias handle of the package.
     */
    public function getHandle(): string
    {
        return $this->handle;
    }

    /**
     * Get the record creation date/time.
     */
    public function getCreatedOn(): DateTimeImmutable
    {
        return $this->createdOn;
    }
}
