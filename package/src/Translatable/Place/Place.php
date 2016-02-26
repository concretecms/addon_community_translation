<?php
namespace Concrete\Package\CommunityTranslation\Src\Translatable\Place;

use Concrete\Package\CommunityTranslation\Src\Translatable\Translatable;

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
     * Package handle ('' for core).
     *
     * @Id
     * @Column(type="string", length=64, nullable=false, options={"comment": "Package handle ('' for core)"})
     *
     * @var string
     */
    protected $tpPackage;

    /**
     * Package version ('dev-...' for development versions).
     *
     * @Id 
     * @Column(type="string", length=64, nullable=false, options={"comment": "Package version ('dev-...' for development versions)"})
     *
     * @var string
     */
    protected $tpVersion;

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

    // Constructor

    public function __construct()
    {
        $this->tpComments = array();
        $this->tpLocations = array();
    }

    // Getters and setters

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
     * Set the associated Translatable string.
     *
     * @param Translatable $value
     */
    public function setTranslatable(Translatable $value)
    {
        $this->tpTranslatable = $value;
    }

    /**
     * Get the package handle ('' for core).
     *
     * @return string 
     */
    public function getPackage()
    {
        return $this->tpPackage;
    }

    /**
     * Set the package handle ('' for core).
     *
     * @param string $value
     */
    public function setPackage($value)
    {
        $this->tpPackage = (string) $value;
    }

    /**
     * Get the package version ('dev-...' for development versions).
     *
     * @return string 
     */
    public function getVersion()
    {
        return $this->tpVersion;
    }

    /**
     * Set the package version ('dev-...' for development versions).
     *
     * @param string value
     */
    public function setVersion($value)
    {
        $this->tpVersion = (string) $value;
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
}
