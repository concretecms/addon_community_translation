<?php
namespace CommunityTranslation\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represent a remote package to be imported.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\RemotePackage",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationRemotePackages",
 *     options={
 *         "comment": "List of all remote packages to be imported"
 *     }
 * )
 */
class RemotePackage
{
    /**
     * @param string $handle
     * @param string $version
     * @param string $archiveUrl
     *
     * @return static
     */
    public static function create($handle, $version, $archiveUrl)
    {
        $result = new static();
        $result->id = null;
        $result->handle = (string) $handle;
        $result->name = '';
        $result->url = '';
        $result->approved = true;
        $result->version = (string) $version;
        $result->archiveUrl = (string) $archiveUrl;
        $result->createdOn = new DateTime();
        $result->processedOn = null;
        $result->failCount = 0;
        $result->lastError = '';

        return $result;
    }

    protected function __construct()
    {
    }

    /**
     * Remote package ID.
     *
     * @ORM\Column(type="integer", options={"unsigned": true, "comment": "Remote package ID"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $id;

    /**
     * Get the remote package ID.
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Remote package handle.
     *
     * @ORM\Column(type="string", length=64, nullable=false, options={"comment": "Remote package handle"})
     *
     * @var string
     */
    protected $handle;

    /**
     * Get the remote package handle.
     *
     * @return string
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * Set the remote package handle.
     *
     * @param string $value
     *
     * @return static
     */
    public function setHandle($value)
    {
        $this->handle = (string) $value;

        return $this;
    }

    /**
     * Remote package name.
     *
     * @ORM\Column(type="string", length=255, nullable=false, options={"comment": "Remote package name"})
     *
     * @var string
     */
    protected $name;

    /**
     * Get the remote package name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the remote package name.
     *
     * @param string $value
     *
     * @return static
     */
    public function setName($value)
    {
        $this->name = (string) $value;

        return $this;
    }

    /**
     * Remote package is approved?
     *
     * @ORM\Column(type="boolean", nullable=false, options={"comment": "Remote package is approved?"})
     *
     * @var bool
     */
    protected $approved;

    /**
     * Is the remote package approved?
     *
     * @return bool
     */
    public function isApproved()
    {
        return $this->approved;
    }

    /**
     * Is the remote package approved?
     *
     * @param bool $value
     *
     * @return static
     */
    public function setIsApproved($value)
    {
        $this->approved = $value ? true : false;

        return $this;
    }

    /**
     * Remote package URL.
     *
     * @ORM\Column(type="string", length=2000, nullable=false, options={"comment": "Remote package URL"})
     *
     * @var string
     */
    protected $url;

    /**
     * Get the remote package URL.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the remote package URL.
     *
     * @param string $value
     *
     * @return static
     */
    public function setUrl($value)
    {
        $this->url = (string) $value;

        return $this;
    }

    /**
     * Remote package version.
     *
     * @ORM\Column(type="string", length=64, nullable=false, options={"comment": "Remote package version"})
     *
     * @var string
     */
    protected $version;

    /**
     * Get the remote package version.
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set the remote package version.
     *
     * @param string $value
     *
     * @return static
     */
    public function setVersion($value)
    {
        $this->version = (string) $value;

        return $this;
    }

    /**
     * URL of the remote package.
     *
     * @ORM\Column(type="string", length=2000, nullable=false, options={"comment": "URL of the remote package"})
     *
     * @var string
     */
    protected $archiveUrl;

    /**
     * Get the URL of the remote package.
     *
     * @return string
     */
    public function getArchiveUrl()
    {
        return $this->archiveUrl;
    }

    /**
     * Set the URL of the remote package.
     *
     * @param string $value
     *
     * @return static
     */
    public function setArchiveUrl($value)
    {
        $this->archiveUrl = (string) $value;

        return $this;
    }

    /**
     * Record creation date/time.
     *
     * @ORM\Column(type="datetime", nullable=false, options={"comment": "Record creation date/time"})
     *
     * @var DateTime
     */
    protected $createdOn;

    /**
     * Get the record creation date/time.
     *
     * @return DateTime
     */
    public function getCreatedOn()
    {
        return $this->createdOn;
    }

    /**
     * Processed date/time (NULL if still to be processed).
     *
     * @ORM\Column(type="datetime", nullable=true, options={"comment": "Processed date/time (NULL if still to be processed)"})
     *
     * @var DateTime|null
     */
    protected $processedOn;

    /**
     * Get the processed date/time (NULL if still to be processed).
     *
     * @return DateTime|null
     */
    public function getProcessedOn()
    {
        return $this->processedOn;
    }

    /**
     * Set the processed date/time (NULL if still to be processed).
     *
     * @param DateTime|null $value
     *
     * @return static
     */
    public function setProcessedOn(DateTime $value = null)
    {
        $this->processedOn = $value;

        return $this;
    }

    /**
     * Number of process failures.
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Number of process failures"})
     *
     * @var int
     */
    protected $failCount;
    
    /**
     * Get the umber of process failures.
     *
     * @return int
     */
    public function getFailCount()
    {
        return $this->failCount;
    }
    
    /**
     * Set the umber of process failures.
     *
     * @param int $value
     *
     * @return static
     */
    public function setFailCount($value)
    {
        $this->failCount = (int) $value;
        
        return $this;
    }

    
    /**
     * The last process error.
     *
     * @ORM\Column(type="text", nullable=false, options={"comment": "Last process error"})
     *
     * @var string
     */
    protected $lastError;
    
    /**
     * Get the last process error.
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }
    
    /**
     * Set the last process error.
     *
     * @param string $value
     *
     * @return static
     */
    public function setLastError($value)
    {
        $this->lastError = (string) $value;
        
        return $this;
    }}
