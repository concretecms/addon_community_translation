<?php

namespace Concrete\Package\CommunityTranslation\Src\Stats;

use Concrete\Package\CommunityTranslation\Src\Package\Package;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use DateTime;

/**
 * Represents an translatable string.
 *
 * @Entity(
 *     repositoryClass="Concrete\Package\CommunityTranslation\Src\Stats\Repository",
 * )
 * @Table(
 *     name="TranslationStats",
 *     options={"comment": "Translation statistics"}
 * )
 */
class Stats
{
    // Properties

    /**
     * Associated Package.
     *
     * @Id
     * @ManyToOne(targetEntity="Concrete\Package\CommunityTranslation\Src\Package\Package", inversedBy="stats")
     * @JoinColumn(name="sPackage", referencedColumnName="pID", nullable=false, onDelete="CASCADE")
     *
     * @var Package
     */
    protected $sPackage;

    /**
     * Associated Locale.
     *
     * @Id
     * @ManyToOne(targetEntity="Concrete\Package\CommunityTranslation\Src\Locale\Locale", inversedBy="stats")
     * @JoinColumn(name="sLocale", referencedColumnName="lID", nullable=false, onDelete="CASCADE")
     *
     * @var Locale
     */
    protected $sLocale;

    /**
     * Total number translatable strings.
     *
     * @Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Total number translatable strings"})
     *
     * @var int
     */
    protected $sTotal;

    /**
     * Number translated strings.
     *
     * @Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Number translated strings"})
     *
     * @var int
     */
    protected $sTranslated;

    /**
     * Date/time of the last updated translations for this package/locale (null if no translations).
     *
     * @Column(type="datetime", nullable=true, options={"comment": "Date/time of the last updated translations for this package/locale (null if no translations)"})
     *
     * @var DateTime|null
     */
    protected $sLastUpdated;

    // Getters & setters

    /**
     * Get the associated Package.
     *
     * @return Package
     */
    public function getPackage()
    {
        return $this->sPackage;
    }

    /**
     * Get the associated Locale.
     *
     * @return Locale
     */
    public function getLocale()
    {
        return $this->sLocale;
    }

    /**
     * Get the total number translatable strings.
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->sTotal;
    }

    /**
     * Set the total number translatable strings.
     *
     * @param int $value
     */
    public function setTotal($value)
    {
        $this->sTotal = (int) $value;
    }

    /**
     * Get the number translated strings.
     *
     * @return int
     */
    public function getTranslated()
    {
        return $this->sTranslated;
    }

    /**
     * Set the number translated strings.
     *
     * @param int $value
     */
    public function setTranslated($value)
    {
        $this->sTranslated = (int) $value;
    }

    /**
     * Get the number of untranslated strings.
     *
     * @return int
     */
    public function getUntranslated()
    {
        return $this->sTotal - $this->sTranslated;
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
        if ($this->sTranslated === 0 || $this->sTotal === 0) {
            return $round ? 0 : 0.0;
        }
        if ($this->sTranslated === $this->sTotal) {
            return $round ? 100 : 100.0;
        }
        $result = $this->sTranslated * 100.0 / $this->sTotal;
        if ($round) {
            $result = max(1, min(99, (int) round($result)));
        }

        return $result;
    }

    /**
     * Set the date/time of the last updated translations for this package/locale (null if no translations).
     *
     * @return DateTime|null
     */
    public function getLastUpdated()
    {
        return $this->sLastUpdated;
    }

    /**
     * Set the date/time of the last updated translations for this package/locale (null if no translations).
     *
     * @return DateTime|null
     */
    public function setLastUpdated(DateTime $value = null)
    {
        $this->sLastUpdated = $value;
    }

    /**
     * Create a new (unsaved) instance.
     *
     * @param Package $package
     * @param Locale $locale
     *
     * @return self
     */
    public static function create(Package $package, Locale $locale)
    {
        $stats = new self();
        $stats->sPackage = $package;
        $stats->sLocale = $locale;
        $stats->sTotal = 0;
        $stats->sTranslated = 0;
        $stats->sLastUpdated = null;

        return $stats;
    }
}
