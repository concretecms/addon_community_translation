<?php
namespace CommunityTranslation\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an translatable string.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Translatable",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationTranslatables",
 *     indexes={
 *         @ORM\Index(name="IDX_CTTranslatablesTextFT", columns={"text"}, flags={"fulltext"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="IDX_CTTranslatablesHash", columns={"hash"})
 *     },
 *     options={"comment": "List of all translatable strings"}
 * )
 */
class Translatable
{
    public function __construct()
    {
        $this->places = new ArrayCollection();
        $this->translations = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    /**
     * Translatable ID.
     *
     * @ORM\Column(type="integer", options={"unsigned": true, "comment": "Translatable ID"})
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Id
     *
     * @var int|null
     */
    protected $id;

    /**
     * Get the translatable ID.
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Translatable hash (MD5 of context.\004.text (if plural: .\005.plural)).
     *
     * @ORM\Column(type="string", length=32, nullable=false, options={"fixed": true, "comment": "Translatable hash (MD5 of context.\004.text (if plural: .\005.plural))"})
     *
     * @var string
     */
    protected $hash;

    /**
     * Get the translatable hash (MD5 of context.\004.text (if plural: .\005.plural)).
     *
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * String context.
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "String context"})
     *
     * @var string
     */
    protected $context;

    /**
     * Get the string context.
     *
     * @return string
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Translatable text.
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Translatable text"})
     *
     * @var string
     */
    protected $text;

    /**
     * Get the translatable string.
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Translatable plural text.
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Translatable plural text"})
     *
     * @var string
     */
    protected $plural;

    /**
     * Get the translatable plural string.
     *
     * @return string
     */
    public function getPlural()
    {
        return $this->plural;
    }

    /**
     * Places associated to this translatable string.
     *
     * @ORM\OneToMany(targetEntity="CommunityTranslation\Entity\Translatable\Place", mappedBy="translatable")
     *
     * @var ArrayCollection
     */
    protected $places;

    /**
     * Translations associated to this translatable string.
     *
     * @ORM\OneToMany(targetEntity="CommunityTranslation\Entity\Translation", mappedBy="translatable")
     *
     * @var ArrayCollection
     */
    protected $translations;

    /**
     * Comments about this translatable string.
     *
     * @ORM\OneToMany(targetEntity="CommunityTranslation\Entity\Translatable\Comment", mappedBy="translatable")
     *
     * @var ArrayCollection
     */
    protected $comments;

    /**
     * Set the translatable text.
     *
     * @param string $context The string context
     * @param string $text The string text (singular form)
     * @param string $plural The string text (plural form)
     */
    public function setString($context, $text, $plural = '')
    {
        $this->context = (string) $context;
        $this->text = (string) $text;
        $this->plural = (string) $plural;
        $this->hash = md5($this->plural ? $this->context . "\004" . $this->text . "\005" . $this->plural : $this->context . "\004" . $this->text);
    }

    /**
     * Get all the places where this string is used.
     *
     * @return \CommunityTranslation\Entity\Translatable\Place[]|ArrayCollection
     */
    public function getPlaces()
    {
        return $this->places;
    }
}
