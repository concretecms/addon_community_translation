<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity\Translatable;

use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\Translatable as TranslatableEntity;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Package versions where translatable strings are defined.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Translatable\Place",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationTranslatablePlaces",
 *     options={"comment": "Package versions where translatable strings are defined"}
 * )
 */
class Place
{
    /**
     * Associated package version.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Package\Version", inversedBy="places")
     * @Doctrine\ORM\Mapping\JoinColumn(name="packageVersion", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Doctrine\ORM\Mapping\Id
     */
    protected PackageVersionEntity $packageVersion;

    /**
     * Associated translatable string.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Translatable", inversedBy="places")
     * @Doctrine\ORM\Mapping\JoinColumn(name="translatable", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Doctrine\ORM\Mapping\Id
     */
    protected TranslatableEntity $translatable;

    /**
     * File paths where the translatable string is defined.
     *
     * @Doctrine\ORM\Mapping\Column(type="array", nullable=false, options={"comment": "File paths where the translatable string is defined"})
     *
     * @var string
     */
    protected array $locations;

    /**
     * Comments for the translation.
     *
     * @Doctrine\ORM\Mapping\Column(type="array", nullable=false, options={"comment": "Comments for the translation"})
     *
     * @var string[]
     */
    protected array $comments;

    /**
     * Sorting key for a translation in a locale.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Sorting key for a translation in a locale"})
     */
    protected int $sort;

    /**
     * At the moment the creation of new Place records is done via direct SQL.
     */
    protected function __construct()
    {
    }

    /**
     * Get the associated package version.
     */
    public function getPackageVersion(): PackageVersionEntity
    {
        return $this->packageVersion;
    }

    /**
     * Get the associated translatable string.
     */
    public function getTranslatable(): TranslatableEntity
    {
        return $this->translatable;
    }

    /**
     * Get the file paths where the translatable string is defined.
     *
     * @return string[]
     */
    public function getLocations(): array
    {
        return $this->locations;
    }

    /**
     * Get the comments for the translation.
     *
     * @return string[]
     */
    public function getComments(): array
    {
        return $this->comments;
    }

    /**
     * Get the sorting key for a translation in a locale.
     */
    public function getSort(): int
    {
        return $this->sort;
    }
}
