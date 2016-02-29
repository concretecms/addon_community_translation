<?php
namespace Concrete\Package\CommunityTranslation\Src\Translatable\Place;

use Concrete\Package\CommunityTranslation\Src\Translatable\Translatable;
use Concrete\Package\CommunityTranslation\Src\Package\Package;

/**
 * Packages where translatable strings are defined.
 *
 * @Entity
 * @Table(
 *     name="TranslatablePlaces",
 *     options={"comment": "Packages where translatable strings are defined"}
 * )
 */
class Place
{
    // Properties

    /**
     * Associated Package.
     *
     * @Id
     * @ManyToOne(targetEntity="Concrete\Package\CommunityTranslation\Src\Package\Package", inversedBy="places")
     * @JoinColumn(name="tpPackage", referencedColumnName="pID", nullable=false, onDelete="CASCADE")
     *
     * @var Package
     */
    protected $tpPackage;

    /**
     * Associated Translatable string.
     *
     * @Id
     * @ManyToOne(targetEntity="Concrete\Package\CommunityTranslation\Src\Translatable\Translatable", inversedBy="places")
     * @JoinColumn(name="tpTranslatable", referencedColumnName="tID", nullable=false, onDelete="CASCADE")
     *
     * @var Translatable
     */
    protected $tpTranslatable;

    /**
     * File paths where the translatable string is defined.
     *
     * @Column(type="array", nullable=false, options={"comment": "File paths where the translatable string is defined"})
     *
     * @var string
     */
    protected $tpLocations;

    /**
     * Comments for the translation.
     *
     * @Column(type="array", nullable=false, options={"comment": "Comments for the translation"})
     *
     * @var string
     */
    protected $tpComments;

    /**
     * Sorting key for a translation in a locale.
     *
     * @Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Sorting key for a translation in a locale"})
     *
     * @var int
     */
    protected $tpSort;

    // Constructor

    public function __construct()
    {
        $this->tpComments = array();
        $this->tpLocations = array();
    }

    // Getters and setters

    /**
     * Get the associated Package.
     *
     * @return Package
     */
    public function getPackage()
    {
        return $this->tpPackage;
    }

    /**
     * Get the associated Translatable string.
     *
     * @return Translatable
     */
    public function getTranslatable()
    {
        return $this->tpTranslatable;
    }

    /**
     * Get the file paths where the translatable string is defined.
     *
     * @return array 
     */
    public function getLocations()
    {
        return $this->tpLocations;
    }

    /**
     * Set the file paths where the translatable string is defined.
     *
     * @param string[] $value
     */
    public function setLocations(array $value)
    {
        $this->tpLocations = $value;
    }

    /**
     * Set the comments for the translation.
     *
     * @return array 
     */
    public function getComments()
    {
        return $this->tpComments;
    }

    /**
     * Set the comments for the translation.
     *
     * @param string[] $value
     */
    public function setComments(array $value)
    {
        $this->tpComments = $value;
    }

    /**
     * Get the sorting key for a translation in a locale.
     *
     * @return int
     */
    public function getSort()
    {
        return $this->tpSort;
    }

    /**
     * Set the sorting key for a translation in a locale.
     *
     * @param int $value
     */
    public function setSort($value)
    {
        $this->tpSort = (int) $value;
    }

    // Helper functions

    /**
     * Create a new (unsaved) instance.
     *
     * @return self
     */
    public static function create(Package $package, Translatable $translatable)
    {
        $place = new self();
        $place->tpPackage = $package;
        $place->tpTranslatable = $translatable;

        return $place;
    }
}
