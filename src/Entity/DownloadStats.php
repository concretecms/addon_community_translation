<?php

namespace CommunityTranslation\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Represents statistical data about translation downloads.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\DownloadStats",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationDownloadStats",
 *     options={
 *         "comment": "Statistical data about translation downloads"
 *     }
 * )
 */
class DownloadStats
{
    protected function __construct()
    {
    }

    /**
     * Associated Locale.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale")
     * @ORM\JoinColumn(name="locale", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @ORM\Id
     *
     * @var Locale
     */
    protected $locale;

    /**
     * Get the associated locale.
     *
     * @return Locale
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Associated package version.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Package\Version")
     * @ORM\JoinColumn(name="packageVersion", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @ORM\Id
     *
     * @var Package\Version
     */
    protected $packageVersion;

    /**
     * Get the associated package version.
     *
     * @return Package\Version
     */
    public function getPackageVersion()
    {
        return $this->packageVersion;
    }

    /**
     * The date/time of the first download.
     *
     * @ORM\Column(type="datetime", nullable=false, options={"comment": "Date/time of the first download"})
     *
     * @var \DateTime
     */
    protected $firstDowload;

    /**
     * Get the date/time of the first download.
     *
     * @return \DateTime
     */
    public function getFirstDownload()
    {
        return $this->firstDowload;
    }

    /**
     * The date/time of the last download.
     *
     * @ORM\Column(type="datetime", nullable=false, options={"comment": "Date/time of the last download"})
     *
     * @var \DateTime
     */
    protected $lastDowload;

    /**
     * Get the date/time of the last download.
     *
     * @return \DateTime
     */
    public function getLastDownload()
    {
        return $this->lastDowload;
    }

    /**
     * The download count.
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Download count"})
     *
     * @var int
     */
    protected $downloadCount;

    /**
     * Get the download count.
     *
     * @return int
     */
    public function getDownloadCount()
    {
        return $this->downloadCount;
    }
}
