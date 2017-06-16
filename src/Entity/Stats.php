<?php

namespace CommunityTranslation\Entity;

use CommunityTranslation\Entity\Package\Version;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an translatable string.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Stats",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationStats",
 *     options={"comment": "Translation statistics"}
 * )
 */
class Stats
{
    /**
     * @param Version $packageVersion
     * @param Locale $locale
     *
     * @return static
     */
    public static function create(Version $packageVersion, Locale $locale)
    {
        $result = new static();
        $result->packageVersion = $packageVersion;
        $result->locale = $locale;
        $result->lastUpdated = null;
        $result->total = 0;
        $result->translated = 0;

        return $result;
    }

    protected function __construct()
    {
    }

    /**
     * Associated package version.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Package\Version", inversedBy="stats")
     * @ORM\JoinColumn(name="packageVersion", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @ORM\Id
     *
     * @var Version
     */
    protected $packageVersion;

    /**
     * Get the associated Package.
     *
     * @return Version
     */
    public function getPackageVersion()
    {
        return $this->packageVersion;
    }

    /**
     * Associated Locale.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale", inversedBy="stats")
     * @ORM\JoinColumn(name="locale", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @ORM\Id
     *
     * @var Locale
     */
    protected $locale;

    /**
     * Get the associated Locale.
     *
     * @return Locale
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Total number translatable strings.
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Total number translatable strings"})
     *
     * @var int
     */
    protected $total;

    /**
     * Get the total number translatable strings.
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Set the total number translatable strings.
     *
     * @param int $value
     *
     * @return static
     */
    public function setTotal($value)
    {
        $this->total = (int) $value;

        return $this;
    }

    /**
     * Number translated strings.
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Number translated strings"})
     *
     * @var int
     */
    protected $translated;

    /**
     * Get the number translated strings.
     *
     * @return int
     */
    public function getTranslated()
    {
        return $this->translated;
    }

    /**
     * Set the number translated strings.
     *
     * @param int $value
     *
     * @return static
     */
    public function setTranslated($value)
    {
        $this->translated = (int) $value;

        return $this;
    }

    /**
     * Date/time of the last updated translations for this package/locale (null if no translations).
     *
     * @ORM\Column(type="datetime", nullable=true, options={"comment": "Date/time of the last updated translations for this package/locale (null if no translations)"})
     *
     * @var DateTime|null
     */
    protected $lastUpdated;

    /**
     * Set the date/time of the last updated translations for this package/locale (null if no translations).
     *
     * @return DateTime|null
     */
    public function getLastUpdated()
    {
        return $this->lastUpdated;
    }

    /**
     * Set the date/time of the last updated translations for this package/locale (null if no translations).
     *
     * @param DateTime|null $value
     *
     * @return static
     */
    public function setLastUpdated(DateTime $value = null)
    {
        $this->lastUpdated = $value;

        return $this;
    }

    /**
     * Get the number of untranslated strings.
     *
     * @return int
     */
    public function getUntranslated()
    {
        return $this->total - $this->translated;
    }

    /**
     * Get the translation percentage.
     *
     * @param bool $round Set to true to get a rounded value (0 if no translations at all, 100 if all strings are translated, 1...99 otherwise),
     *
     * @return int|float
     */
    public function getPercentage($round = true)
    {
        if ($this->translated === 0 || $this->total === 0) {
            $result = $round ? 0 : 0.0;
        } elseif ($this->translated === $this->total) {
            $result = $round ? 100 : 100.0;
        } else {
            $result = $this->translated * 100.0 / $this->total;
            if ($round) {
                $result = max(1, min(99, (int) round($result)));
            }
        }

        return $result;
    }
}
