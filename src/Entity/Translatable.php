<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity;

use Doctrine\Common\Collections\Collection;
use Gettext\Translation as GettextTranslation;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents an translatable string.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Translatable",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationTranslatables",
 *     indexes={
 *         @Doctrine\ORM\Mapping\Index(name="IDX_CTTranslatablesTextFT", columns={"text"}, flags={"fulltext"})
 *     },
 *     uniqueConstraints={
 *         @Doctrine\ORM\Mapping\UniqueConstraint(name="IDX_CTTranslatablesHash", columns={"hash"})
 *     },
 *     options={"comment": "List of all translatable strings"}
 * )
 */
class Translatable
{
    /**
     * Translatable ID.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned": true, "comment": "Translatable ID"})
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     * @Doctrine\ORM\Mapping\Id
     */
    protected ?int $id;

    /**
     * Translatable hash.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=32, nullable=false, options={"collation": "ascii_bin", "fixed": true, "comment": "Translatable hash (MD5 of context.\004.text (if plural: .\005.plural))"})
     *
     * @see \CommunityTranslation\Entity\Translatable::generateHashFromValues()
     * @see \CommunityTranslation\Entity\Translatable::generateHashFromGettextKey()
     */
    protected string $hash;

    /**
     * String context.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "String context"})
     */
    protected string $context;

    /**
     * Translatable text.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Translatable text"})
     */
    protected string $text;

    /**
     * Translatable plural text.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Translatable plural text"})
     */
    protected string $plural;

    /**
     * Places associated to this translatable string.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Translatable\Place", mappedBy="translatable")
     */
    protected Collection $places;

    /**
     * Translations associated to this translatable string.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Translation", mappedBy="translatable")
     */
    protected Collection $translations;

    /**
     * Comments about this translatable string.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CommunityTranslation\Entity\Translatable\Comment", mappedBy="translatable")
     */
    protected Collection $comments;

    /**
     * At the moment the creation of new Translatable records is done via direct SQL.
     */
    protected function __construct()
    {
    }

    /**
     * Get the translatable ID.
     */
    public function getID(): ?int
    {
        return $this->id;
    }

    /**
     * Get the translatable hash.
     *
     * @see \CommunityTranslation\Entity\Translatable::generateHashFromValues()
     * @see \CommunityTranslation\Entity\Translatable::generateHashFromGettextKey()
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Get the string context.
     */
    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * Get the translatable string.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Get the translatable plural string.
     */
    public function getPlural(): string
    {
        return $this->plural;
    }

    /**
     * Get all the places where this string is used.
     *
     * @return \CommunityTranslation\Entity\Translatable\Place[]
     */
    public function getPlaces(): Collection
    {
        return $this->places;
    }

    public static function generateHashFromValues(string $context, string $singular, string $plural): string
    {
        return self::generateHashFromGettextKey(GettextTranslation::generateId($context, $singular), $plural);
    }

    public static function generateHashFromGettextKey(string $gettextKey, string $plural): string
    {
        return md5($plural === '' ? $gettextKey : "{$gettextKey}\005{$plural}");
    }
}
