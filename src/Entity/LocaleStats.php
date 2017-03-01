<?php
namespace CommunityTranslation\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Statistical data about a locale.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\LocaleStats",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationLocaleStats",
 *     options={"comment": "Statistical data about a locale"}
 * )
 */
class LocaleStats
{
    /**
     * Create a new (unsaved) instance.
     *
     * @param Locale $locale
     * @param int $numTranslatable
     * @param int $numApprovedTranslations
     *
     * @return static
     */
    public static function create(Locale $locale, $numTranslatable, $numApprovedTranslations)
    {
        $result = new static();
        $result->locale = $locale;
        $result->numTranslatable = (int) $numTranslatable;
        $result->numApprovedTranslations = (int) $numApprovedTranslations;

        return $result;
    }

    protected function __construct()
    {
    }

    /**
     * Associated Locale.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale", inversedBy="translations")
     * @ORM\JoinColumn(name="locale", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @ORM\Id
     *
     * @var Locale
     */
    protected $locale;

    /**
     * Number of translatable strings.
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Number of translatable strings"})
     *
     * @var int
     */
    protected $numTranslatable;

    /**
     * Get the number of translatable strings.
     *
     * @return int
     */
    public function getNumTranslatable()
    {
        return $this->numTranslatable;
    }

    /**
     * Number of approved translations.
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Number of approved translations"})
     *
     * @var int
     */
    protected $numApprovedTranslations;

    /**
     * Get the number of approved translations.
     *
     * @return int
     */
    public function getNumApprovedTranslations()
    {
        return $this->numApprovedTranslations;
    }

    /**
     * Get the translation progress.
     *
     * @param bool $round
     *
     * @return int|float
     */
    public function getPercentage($round = true)
    {
        if ($this->numApprovedTranslations === 0 || $this->numTranslatable === 0) {
            $result = $round ? 0 : 0.0;
        } elseif ($this->numApprovedTranslations === $this->numTranslatable) {
            $result = $round ? 100 : 100.0;
        } else {
            $result = $this->numApprovedTranslations * 100.0 / $this->numTranslatable;
            if ($round) {
                $result = max(1, min(99, (int) round($result)));
            }
        }

        return $result;
    }
}
