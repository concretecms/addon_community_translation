<?php
namespace CommunityTranslation\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

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
     * @param string $classHandle The notification class handle
     * @param array $notificationData Data specific to the notification class
     * @param Locale|null $locale Associated locale
     *
     * @return static
     */
    public static function create($classHandle, array $notificationData = [])
    {
        $result = new static();
        $result->createdOn = new DateTime();
        $result->classHandle = (string) $classHandle;
        $result->notificationData = $notificationData;
        $result->sentOn = null;
        $result->sentCount = null;
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
     * Notification class handle.
     *
     * @ORM\Column(type="string", length=64, nullable=false, options={"comment": "Notification class handle"})
     *
     * @var string
     */
    protected $classHandle;

    /**
     * Get the notification class handle.
     *
     * @return string
     */
    public function getClassHandle()
    {
        return $this->classHandle;
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
     * Number of actual recipients notified (null if not yed delivered).
     *
     * @ORM\Column(type="integer", nullable=true, options={"unsigned": true, "comment": "Number of actual recipients notified (null if not yed delivered)"})
     *
     * @var int|null
     */
    protected $sentCount;

    /**
     * Get the number of actual recipients notified (null if not yed delivered).
     *
     * @return int|null
     */
    public function getSentCount()
    {
        return $this->sentCount;
    }

    /**
     * Set the number of actual recipients notified (null if not yed delivered).
     *
     * @param int|null $value
     *
     * @return static
     */
    public function setSentCount($value = null)
    {
        if ($value === null || $value === '' || $value === false) {
            $this->sentCount = null;
        } else {
            $this->sentCount = (int) $value;
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
