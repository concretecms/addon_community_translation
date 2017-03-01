<?php
namespace CommunityTranslation\Entity\Translatable;

use CommunityTranslation\Entity\Package\Version;
use CommunityTranslation\Entity\Translatable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Package versions where translatable strings are defined.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Translatable\Place",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationTranslatablePlaces",
 *     options={"comment": "Package versions where translatable strings are defined"}
 * )
 */
class Place
{
    /**
     * Associated package version.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Package\Version", inversedBy="places")
     * @ORM\JoinColumn(name="packageVersion", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @ORM\Id
     *
     * @var Version
     */
    protected $packageVersion;

    /**
     * Get the associated package version.
     *
     * @return Version
     */
    public function getPackageVersion()
    {
        return $this->packageVersion;
    }

    /**
     * Set the associated package version.
     *
     * @param Version $value
     *
     * @return static
     */
    public function setPackageVersion(Version $value)
    {
        $this->packageVersion = $value;

        return $this;
    }

    /**
     * Associated translatable string.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Translatable", inversedBy="places")
     * @ORM\JoinColumn(name="translatable", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @ORM\Id
     *
     * @var Translatable
     */
    protected $translatable;

    /**
     * Get the associated translatable string.
     *
     * @return Translatable
     */
    public function getTranslatable()
    {
        return $this->translatable;
    }

    /**
     * Set the associated translatable string.
     *
     * @param Translatable $value
     *
     * @return static
     */
    public function setTranslatable(Translatable $value)
    {
        $this->translatable = $value;

        return $this;
    }

    /**
     * File paths where the translatable string is defined.
     *
     * @ORM\Column(type="array", nullable=false, options={"comment": "File paths where the translatable string is defined"})
     *
     * @var string
     */
    protected $locations = [];

    /**
     * Get the file paths where the translatable string is defined.
     *
     * @return string[]
     */
    public function getLocations()
    {
        return $this->locations;
    }

    /**
     * Set the file paths where the translatable string is defined.
     *
     * @param string[] $value
     *
     * @return static
     */
    public function setLocations(array $value)
    {
        $this->locations = $value;

        return $this;
    }

    /**
     * Comments for the translation.
     *
     * @ORM\Column(type="array", nullable=false, options={"comment": "Comments for the translation"})
     *
     * @var string
     */
    protected $comments = [];

    /**
     * Set the comments for the translation.
     *
     * @return string[]
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * Set the comments for the translation.
     *
     * @param string[] $value
     *
     * @return static
     */
    public function setComments(array $value)
    {
        $this->comments = $value;

        return $this;
    }

    /**
     * Sorting key for a translation in a locale.
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Sorting key for a translation in a locale"})
     *
     * @var int
     */
    protected $sort;

    /**
     * Get the sorting key for a translation in a locale.
     *
     * @return int
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * Set the sorting key for a translation in a locale.
     *
     * @param int $value
     *
     * @return static
     */
    public function setSort($value)
    {
        $this->sort = (int) $value;

        return $this;
    }
}
