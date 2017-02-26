<?php
namespace CommunityTranslation\Entity;

use Concrete\Core\Entity\User\User;
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
     * Unspecified recipient (should be evaluated when the notification is sent).
     *
     * @var int
     */
    const RECIPIENT_UNSPECIFIED = 0x0000;

    /**
     * Notification for a single user.
     *
     * @var int
     */
    const RECIPIENT_USER = 0x0001;

    /**
     * Notification for global administrators.
     *
     * @var int
     */
    const RECIPIENT_GLOBAL_ADMINISTRATORS = 0x0002;

    /**
     * Notification for locale administrators.
     *
     * @var int
     */
    const RECIPIENT_LOCALE_ADMINISTRATORS = 0x0004;

    /**
     * Notification for locale translators.
     *
     * @var int
     */
    const RECIPIENT_LOCALE_TRANSLATORS = 0x0008;

    /**
     * Notification for locale aspiring ptranslators.
     *
     * @var int
     */
    const RECIPIENT_LOCALE_ASPIRINGTRANSLATORS = 0x0010;

    /**
     * Create a new (unsaved) instance.
     *
     * @param string $classHandle The notification class handle
     * @param int $recipientClasses One or more values of Notification::RECIPIENT_... constants
     * @param array $notificationData Data specific to the notification class
     * @param Locale|null $locale Associated locale
     * @param User|null $user Associated useer
     *
     * @return static
     */
    public static function create($classHandle, $recipientClasses, array $notificationData = [], Locale $locale = null, User $user = null)
    {
        $result = new static();
        $result->classHandle = (string) $classHandle;
        $result->createdOn = new DateTime();
        $result->sentOn = null;
        $result->sentCount = null;
        $result->deliveryErrors = [];
        $result->recipientClasses = (int) $recipientClasses;
        $result->notificationData = $notificationData;
        $result->locale = $locale;
        $result->user = $user;

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

    /**
     * The class of the recipients (one or more values of Notification::RECIPIENT_... constants).
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned": true, "comment": "The class of the recipients (one or more values of Notification::RECIPIENT_... constants)"})
     *
     * @var int
     */
    protected $recipientClasses;

    /**
     * Get the class of the recipients (one or more values of Notification::RECIPIENT_... constants).
     *
     * @return int
     */
    public function getRecipientClasses()
    {
        return $this->recipientClasses;
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
     * Associated Locale.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Locale")
     * @ORM\JoinColumn(name="locale", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     *
     * @var Locale|null
     */
    protected $locale;

    /**
     * Get the associated Locale.
     *
     * @return Locale|null
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Associated user.
     *
     * @ORM\ManyToOne(targetEntity="Concrete\Core\Entity\User\User")
     * @ORM\JoinColumn(name="user", referencedColumnName="uID", nullable=true, onDelete="CASCADE")
     *
     * @var User|null
     */
    protected $user;

    /**
     * Get the associated user.
     *
     * @return User|null
     */
    public function getUser()
    {
        return $this->user;
    }
}
