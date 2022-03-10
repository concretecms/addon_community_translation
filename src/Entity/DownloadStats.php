<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity;

use DateTimeImmutable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents statistical data about translation downloads.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\DownloadStats",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationDownloadStats",
 *     options={
 *         "comment": "Statistical data about translation downloads"
 *     }
 * )
 */
class DownloadStats
{
    /**
     * Associated Locale.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale")
     * @Doctrine\ORM\Mapping\JoinColumn(name="locale", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Doctrine\ORM\Mapping\Id
     */
    protected Locale $locale;

    /**
     * Associated package version.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Package\Version")
     * @Doctrine\ORM\Mapping\JoinColumn(name="packageVersion", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Doctrine\ORM\Mapping\Id
     */
    protected Package\Version $packageVersion;

    /**
     * The date/time of the first download.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=false, options={"comment": "Date/time of the first download"})
     */
    protected DateTimeImmutable $firstDowload;

    /**
     * The date/time of the last download.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=false, options={"comment": "Date/time of the last download"})
     */
    protected DateTimeImmutable $lastDowload;

    /**
     * The download count.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Download count"})
     */
    protected int $downloadCount;

    /**
     * At the moment the creation of new DownloadStats records is done via direct SQL.
     */
    protected function __construct()
    {
    }

    /**
     * Get the associated locale.
     */
    public function getLocale(): Locale
    {
        return $this->locale;
    }

    /**
     * Get the associated package version.
     */
    public function getPackageVersion(): Package\Version
    {
        return $this->packageVersion;
    }

    /**
     * Get the date/time of the first download.
     */
    public function getFirstDownload(): DateTimeImmutable
    {
        return $this->firstDowload;
    }

    /**
     * Get the date/time of the last download.
     */
    public function getLastDownload(): DateTimeImmutable
    {
        return $this->lastDowload;
    }

    /**
     * Get the download count.
     */
    public function getDownloadCount(): int
    {
        return $this->downloadCount;
    }
}
