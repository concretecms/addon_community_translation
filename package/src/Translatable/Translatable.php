<?php
namespace Concrete\Package\CommunityTranslation\Src\Translatable;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Represents an translatable string.
 *
 * @Entity
 * @Table(
 *     name="Translatables",
 *     options={"comment": "List of all translatable strings"}
 * )
 */
class Translatable
{
    // Properties

    /**
     * Translatable ID.
     *
     * @Id @Column(type="integer", options={"unsigned": true, "comment": "Translatable ID"})
     * @GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $tID;

    /**
     * Translatable hash (MD5 of tContext.\004.tText (if plural: .\005.tPlural)).
     *
     * @Column(type="string", length=32, nullable=false, unique=true, options={"fixed": true, "comment": "Translatable hash (MD5 of tContext.\004.tText (if plural: .\005.tPlural))"})
     *
     * @var string
     */
    protected $tHash;

    /**
     * String context.
     *
     * @Column(type="text", nullable=false, options={"comment": "String context"})
     *
     * @var string
     */
    protected $tContext;

    /**
     * Translatable text.
     *
     * @Column(type="text", nullable=false, options={"comment": "Translatable text"})
     *
     * @var string
     */
    protected $tText;

    /**
     * Translatable plural text.
     *
     * @Column(type="text", nullable=false, options={"comment": "Translatable plural text"})
     *
     * @var string
     */
    protected $tPlural;

    /**
     * Places associated to this translatable string.
     *
     * @OneToMany(targetEntity="Concrete\Package\CommunityTranslation\Src\Translatable\Place\Place", mappedBy="tpTranslatable")
     */
    protected $places;

    /**
     * Translations associated to this translatable string.
     *
     * @OneToMany(targetEntity="Concrete\Package\CommunityTranslation\Src\Translation\Translation", mappedBy="tTranslatable")
     */
    protected $translations;

    // Constructor

    public function __construct()
    {
        $this->places = new ArrayCollection();
        $this->translations = new ArrayCollection();
    }

    // Getters & setters

    /**
     * Get the translatable ID.
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->tID;
    }

    /**
     * Get the translatable hash (MD5 of tContext.\004.tText (if plural: .\005.tPlural)).
     *
     * @return string 
     */
    public function getHash()
    {
        return $this->tHash;
    }

    /**
     * Get the string context.
     *
     * @return string 
     */
    public function getContext()
    {
        return $this->tContext;
    }

    /**
     * Get the translatable string.
     *
     * @return string
     */
    public function getText()
    {
        return $this->tText;
    }

    /**
     * Get the translatable plural string.
     *
     * @return string
     */
    public function getPlural()
    {
        return $this->tPlural;
    }

    /**
     * Set the translatable text.
     *
     * @param string $context
     * @param string $text
     * @param string $plural
     */
    public function setString($context, $text, $plural = '')
    {
        $this->tContext = (string) $context;
        $this->tText = (string) $text;
        $this->tPlural = (string) $plural;
        $this->tHash = md5($this->tPlural ? $this->tContext."\004".$this->tText."\005".$this->tPlural : $this->tContext."\004".$this->tText);
    }
}
