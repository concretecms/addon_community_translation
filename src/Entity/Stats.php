<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity;

use CommunityTranslation\Entity\Package\Version;
use DateTimeImmutable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents an translatable string.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Stats",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationStats",
 *     options={"comment": "Translation statistics"}
 * )
 */
class Stats
{
    /**
     * Associated package version.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Package\Version", inversedBy="stats")
     * @Doctrine\ORM\Mapping\JoinColumn(name="packageVersion", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Doctrine\ORM\Mapping\Id
     */
    protected Version $packageVersion;

    /**
     * Associated Locale.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale", inversedBy="stats")
     * @Doctrine\ORM\Mapping\JoinColumn(name="locale", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Doctrine\ORM\Mapping\Id
     */
    protected Locale $locale;

    /**
     * Total number translatable strings.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Total number translatable strings"})
     */
    protected int $total;

    /**
     * Number translated strings.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Number translated strings"})
     */
    protected int $translated;

    /**
     * Date/time of the last updated translations for this package/locale (null if no translations).
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=true, options={"comment": "Date/time of the last updated translations for this package/locale (null if no translations)"})
     */
    protected ?DateTimeImmutable $lastUpdated;

    public function __construct(Version $packageVersion, Locale $locale, int $total = 0)
    {
        $this->packageVersion = $packageVersion;
        $this->locale = $locale;
        $this->total = $total;
        $this->translated = 0;
        $this->lastUpdated = null;
    }

    /**
     * Get the associated Package.
     */
    public function getPackageVersion(): Version
    {
        return $this->packageVersion;
    }

    /**
     * Get the associated Locale.
     */
    public function getLocale(): Locale
    {
        return $this->locale;
    }

    /**
     * Get the total number translatable strings.
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Set the total number translatable strings.
     *
     * @return $this
     */
    public function setTotal(int $value): self
    {
        $this->total = $value;

        return $this;
    }

    /**
     * Get the number translated strings.
     */
    public function getTranslated(): int
    {
        return $this->translated;
    }

    /**
     * Set the number translated strings.
     *
     * @return $this
     */
    public function setTranslated(int $value): self
    {
        $this->translated = $value;

        return $this;
    }

    /**
     * Set the date/time of the last updated translations for this package/locale (null if no translations).
     */
    public function getLastUpdated(): ?DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    /**
     * Set the date/time of the last updated translations for this package/locale (null if no translations).
     *
     * @return $this
     */
    public function setLastUpdated(?DateTimeImmutable $value): self
    {
        $this->lastUpdated = $value;

        return $this;
    }

    /**
     * Get the number of untranslated strings.
     */
    public function getUntranslated(): int
    {
        return $this->total - $this->translated;
    }

    /**
     * Get the translation percentage.
     *
     * @param bool $round Set to true to get a rounded value (0 if no translations at all, 100 if all strings are translated, 1...99 otherwise),
     */
    public function getPercentage(): float
    {
        if ($this->translated === 0 || $this->total === 0) {
            return 0.0;
        }
        if ($this->translated === $this->total) {
            return 100.0;
        }

        return $this->translated * 100.0 / $this->total;
    }

    /**
     * Get the rounded translation percentage (0 it exactly 0%, 100 if exactly 100%, a number between 1 and 99 otherwise).
     */
    public function getRoundedPercentage(): int
    {
        $float = $this->getPercentage();
        if ($float === 0.0) {
            return 0;
        }
        if ($float === 100.0) {
            return 100;
        }
        $int = (int) round($float);
        if ($int < 1) {
            return 1;
        }
        if ($int > 99) {
            return 99;
        }

        return $int;
    }
}
