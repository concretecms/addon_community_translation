<?php
namespace CommunityTranslation\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use ReflectionClass;

/**
 * Notificatons.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\Notification",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationNotifications",
 *     options={"comment": "Notificatons"}
 * )
 */
class Notification
{
    /**
     * Create a new (unsaved) instance.
     *
     * @param string $fqnClass The fully qualified name of the category class
     * @param array $notificationData Data specific to the notification class
     * @param int|null $priority The notification priority (bigger values for higher priorities)
     *
     * @return static
     */
    public static function create($fqnClass, array $notificationData = [], $priority = null)
    {
        if (!is_numeric($priority)) {
            $priority = 0;
            if (class_exists($fqnClass) && class_exists(ReflectionClass::class)) {
                $reflectionClass = new ReflectionClass($fqnClass);
                $classConstants = $reflectionClass->getConstants();
                if (isset($classConstants['PRIORITY'])) {
                    $priority = (int) $classConstants['PRIORITY'];
                }
            }
        }
        $result = new static();
        $result->createdOn = new DateTime();
        $result->updatedOn = new DateTime();
        $result->fqnClass = (string) $fqnClass;
        $result->priority = (int) $priority;
        $result->notificationData = $notificationData;
        $result->deliveryAttempts = 0;
        $result->sentOn = null;
        $result->sentCountPotential = null;
        $result->sentCountActual = null;
        $result->deliveryErrors = [];

        return $result;
    }

    protected function __construct()
    {
    }

    /**
     * Notification ID.
     *
     * @ORM\Column(type="integer", options={"unsigned": true, "comment": "Notification ID"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $id;

    /**
     * Get the Notification ID.
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Date/time of the record creation.
     *
     * @ORM\Column(type="datetime", nullable=false, options={"comment": "Date/time of the record creation"})
     *
     * @var DateTime
     */
    protected $createdOn;

    /**
     * Get the date/time of the record creation.
     *
     * @return DateTime
     */
    public function getCreatedOn()
    {
        return $this->createdOn;
    }

    /**
     * Date/time when the data was last modified.
     *
     * @ORM\Column(type="datetime", nullable=false, options={"comment": "Date/time when the data was last modified"})
     *
     * @var DateTime
     */
    protected $updatedOn;

    /**
     * Get date/time when the data was last modified.
     *
     * @return DateTime
     */
    public function getUpdatedOn()
    {
        return $this->updatedOn;
    }

    /**
     * Set date/time when the data was last modified.
     *
     * @param DateTime $value
     *
     * @return static
     */
    public function setUpdatedOn(DateTime $value)
    {
        $this->updatedOn = $value;

        return $this;
    }

    /**
     * Fully qualified name of the category class.
     *
     * @ORM\Column(type="string", length=255, nullable=false, options={"comment": "Fully qualified name of the category class"})
     *
     * @var string
     */
    protected $fqnClass;

    /**
     * Get the fully qualified name of the category class.
     *
     * @return string
     */
    public function getFQNClass()
    {
        return $this->fqnClass;
    }

    /**
     * The notification priority (bigger values for higher priorities).
     *
     * @ORM\Column(type="smallint", nullable=false, options={"unsigned": false, "default" : 0, "comment": "Notification priority (bigger values for higher priorities)"})
     *
     * @var int
     */
    protected $priority;

    /**
     * Get the notification priority (bigger values for higher priorities).
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Data specific to the notification class.
     *
     * @ORM\Column(type="array", nullable=false, options={"comment": "Data specific to the notification class"})
     *
     * @var array
     */
    protected $notificationData;

    /**
     * Get the data specific to the notification class.
     *
     * @return array
     */
    public function getNotificationData()
    {
        return $this->notificationData;
    }

    /**
     * Number of delivery attempts.
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "Number of delivery attempts"})
     *
     * @var int
     */
    protected $deliveryAttempts;

    /**
     * Get the number of delivery attempts.
     *
     * @return int
     */
    public function getDeliveryAttempts()
    {
        return $this->deliveryAttempts;
    }

    /**
     * Set the number of delivery attempts.
     *
     * @param int $value
     *
     * @return static
     */
    public function setDeliveryAttempts($value)
    {
        $this->deliveryAttempts = (int) $value;

        return $this;
    }

    /**
     * Set the data specific to the notification class.
     *
     * @param array $value
     *
     * @return static
     */
    public function setNotificationData(array $value)
    {
        $this->notificationData = $value;

        return $this;
    }

    /**
     * Date/time of the notification delivery (null if not yed delivered).
     *
     * @ORM\Column(type="datetime", nullable=true, options={"comment": "Date/time of the notification delivery (null if not yed delivered)"})
     *
     * @var DateTime
     */
    protected $sentOn;

    /**
     * Get the date/time of the notification delivery (null if not yed delivered).
     *
     * @return DateTime|null
     */
    public function getSentOn()
    {
        return $this->sentOn;
    }

    /**
     * Set the date/time of the notification delivery (null if not yed delivered).
     *
     * @param DateTime|null $value
     *
     * @return static
     */
    public function setSentOn(DateTime $value = null)
    {
        $this->sentOn = $value;

        return $this;
    }

    /**
     * Number of potential recipients notified (null if not yed delivered).
     *
     * @ORM\Column(type="integer", nullable=true, options={"unsigned": true, "comment": "Number of potential recipients notified (null if not yed delivered)"})
     *
     * @var int|null
     */
    protected $sentCountPotential;

    /**
     * Get the number of potential recipients notified (null if not yed delivered).
     *
     * @return int|null
     */
    public function getSentCountPotential()
    {
        return $this->sentCountPotential;
    }

    /**
     * Set the number of actual recipients notified (null if not yed delivered).
     *
     * @param int|null $value
     *
     * @return static
     */
    public function setSentCountPotential($value = null)
    {
        if ($value === null || $value === '' || $value === false) {
            $this->sentCountPotential = null;
        } else {
            $this->sentCountPotential = (int) $value;
        }

        return $this;
    }

    /**
     * Number of actual recipients notified (null if not yed delivered).
     *
     * @ORM\Column(type="integer", nullable=true, options={"unsigned": true, "comment": "Number of actual recipients notified (null if not yed delivered)"})
     *
     * @var int|null
     */
    protected $sentCountActual;

    /**
     * Get the number of actual recipients notified (null if not yed delivered).
     *
     * @return int|null
     */
    public function getSentCountActual()
    {
        return $this->sentCountActual;
    }

    /**
     * Set the number of actual recipients notified (null if not yed delivered).
     *
     * @param int|null $value
     *
     * @return static
     */
    public function setSentCountActual($value = null)
    {
        if ($value === null || $value === '' || $value === false) {
            $this->sentCountActual = null;
        } else {
            $this->sentCountActual = (int) $value;
        }

        return $this;
    }

    /**
     * List of errors throws during delivery (empty if not yet delivered or if no errors occurred).
     *
     * @ORM\Column(type="array", nullable=false, options={"comment": "List of errors throws during delivery (empty if not yet delivered or if no errors occurred)"})
     *
     * @var string[]
     */
    protected $deliveryErrors;

    /**
     * Get list of errors throws during delivery (empty if not yet delivered or if no errors occurred).
     *
     * @return string[]
     */
    public function getDeliveryErrors()
    {
        return $this->deliveryErrors;
    }

    /**
     * Add am error thrown during delivery.
     *
     * @param string[] $value
     *
     * @return static
     */
    public function addDeliveryError($value)
    {
        $this->deliveryErrors[] = (string) $value;

        return $this;
    }

    /**
     * Set list of errors throws during delivery (empty if not yet delivered or if no errors occurred).
     *
     * @param string[] $value
     *
     * @return static
     */
    public function setDeliveryErrors(array $value)
    {
        $this->deliveryErrors = $value;

        return $this;
    }
}
