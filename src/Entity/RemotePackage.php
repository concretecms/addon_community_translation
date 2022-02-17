<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity;

use DateTimeImmutable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represent a remote package to be imported.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\RemotePackage",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationRemotePackages",
 *     options={
 *         "comment": "List of all remote packages to be imported"
 *     }
 * )
 */
class RemotePackage
{
    /**
     * Remote package ID.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned": true, "comment": "Remote package ID"})
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id;

    /**
     * Remote package handle.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=64, nullable=false, options={"comment": "Remote package handle"})
     */
    protected string $handle;

    /**
     * Remote package name.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment": "Remote package name"})
     */
    protected string $name;

    /**
     * Remote package is approved?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment": "Remote package is approved?"})
     */
    protected bool $approved;

    /**
     * Remote package URL.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=2000, nullable=false, options={"comment": "Remote package URL"})
     */
    protected string $url;

    /**
     * Remote package version.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=64, nullable=false, options={"comment": "Remote package version"})
     */
    protected string $version;

    /**
     * URL of the remote package.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=2000, nullable=false, options={"comment": "URL of the remote package"})
     */
    protected string $archiveUrl;

    /**
     * Record creation date/time.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=false, options={"comment": "Record creation date/time"})
     *
     * @var DateTimeImmutable
     */
    protected DateTimeImmutable $createdOn;

    /**
     * Processed date/time (NULL if still to be processed).
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=true, options={"comment": "Processed date/time (NULL if still to be processed)"})
     */
    protected ?DateTimeImmutable $processedOn;

    /**
     * Number of process failures.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Number of process failures"})
     */
    protected int $failCount;

    /**
     * The last process error.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment": "Last process error"})
     */
    protected string $lastError;

    public function __construct(string $handle, string $version, string $archiveUrl)
    {
        $this->id = null;
        $this->handle = $handle;
        $this->name = '';
        $this->approved = true;
        $this->url = '';
        $this->version = $version;
        $this->archiveUrl = $archiveUrl;
        $this->createdOn = new DateTimeImmutable();
        $this->processedOn = null;
        $this->failCount = 0;
        $this->lastError = '';
    }

    /**
     * Get the remote package ID.
     */
    public function getID(): ?int
    {
        return $this->id;
    }

    /**
     * Get the remote package handle.
     */
    public function getHandle(): string
    {
        return $this->handle;
    }

    /**
     * Set the remote package handle.
     *
     * @return $this
     */
    public function setHandle(string $value): self
    {
        $this->handle = $value;

        return $this;
    }

    /**
     * Get the remote package name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the remote package name.
     *
     * @return $this
     */
    public function setName(string $value): self
    {
        $this->name = $value;

        return $this;
    }

    /**
     * Is the remote package approved?
     */
    public function isApproved(): bool
    {
        return $this->approved;
    }

    /**
     * Is the remote package approved?
     *
     * @return $this
     */
    public function setIsApproved(bool $value): self
    {
        $this->approved = $value;

        return $this;
    }

    /**
     * Get the remote package URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set the remote package URL.
     *
     * @return $this
     */
    public function setUrl(string $value): self
    {
        $this->url = $value;

        return $this;
    }

    /**
     * Get the remote package version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Set the remote package version.
     *
     * @return $this
     */
    public function setVersion(string $value): self
    {
        $this->version = $value;

        return $this;
    }

    /**
     * Get the URL of the remote package.
     */
    public function getArchiveUrl(): string
    {
        return $this->archiveUrl;
    }

    /**
     * Set the URL of the remote package.
     *
     * @return $this
     */
    public function setArchiveUrl(string $value): self
    {
        $this->archiveUrl = $value;

        return $this;
    }

    /**
     * Get the record creation date/time.
     */
    public function getCreatedOn(): DateTimeImmutable
    {
        return $this->createdOn;
    }

    /**
     * Get the processed date/time (NULL if still to be processed).
     */
    public function getProcessedOn(): ?DateTimeImmutable
    {
        return $this->processedOn;
    }

    /**
     * Set the processed date/time (NULL if still to be processed).
     *
     * @return $this
     */
    public function setProcessedOn(?DateTimeImmutable $value): self
    {
        $this->processedOn = $value;

        return $this;
    }

    /**
     * Get the umber of process failures.
     */
    public function getFailCount(): int
    {
        return $this->failCount;
    }

    /**
     * Set the umber of process failures.
     *
     * @return $this
     */
    public function setFailCount(int $value): self
    {
        $this->failCount = $value;

        return $this;
    }

    /**
     * Get the last process error.
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Set the last process error.
     *
     * @return $this
     */
    public function setLastError(string $value): self
    {
        $this->lastError = $value;

        return $this;
    }
}
