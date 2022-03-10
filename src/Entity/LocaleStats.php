<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Statistical data about a locale.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\LocaleStats",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationLocaleStats",
 *     options={"comment": "Statistical data about a locale"}
 * )
 */
class LocaleStats
{
    /**
     * Associated Locale.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale", inversedBy="translations")
     * @Doctrine\ORM\Mapping\JoinColumn(name="locale", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Doctrine\ORM\Mapping\Id
     */
    protected Locale $locale;

    /**
     * Number of translatable strings.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Number of translatable strings"})
     */
    protected int $numTranslatable;

    /**
     * Number of approved translations.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Number of approved translations"})
     */
    protected int $numApprovedTranslations;

    public function __construct(Locale $locale, int $numTranslatable, int $numApprovedTranslations)
    {
        $this->locale = $locale;
        $this->numTranslatable = $numTranslatable;
        $this->numApprovedTranslations = $numApprovedTranslations;
    }

    /**
     * Get the number of translatable strings.
     */
    public function getNumTranslatable(): int
    {
        return $this->numTranslatable;
    }

    /**
     * Get the number of approved translations.
     */
    public function getNumApprovedTranslations(): int
    {
        return $this->numApprovedTranslations;
    }

    /**
     * Get the actual translation progress.
     */
    public function getPercentage(): float
    {
        if ($this->numApprovedTranslations === 0 || $this->numTranslatable === 0) {
            return 0.0;
        }
        if ($this->numApprovedTranslations === $this->numTranslatable) {
            return 100.0;
        }

        return $this->numApprovedTranslations * 100.0 / $this->numTranslatable;
    }

    /**
     * Get the rounded translation progress (0 it exactly 0%, 100 if exactly 100%, a number between 1 and 99 otherwise).
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
