<?php
namespace CommunityTranslation\Entity;

use Concrete\Core\Entity\User\User as UserEntity;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * User's subscription for packages.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\PackageSubscription",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationPackageSubscriptions",
 *     options={
 *         "comment": "User's subscription for packages"
 *     }
 * )
 */
class PackageSubscription
{
    /**
     * @param UserEntity $user User associated to this subscription
     * @param Package $package package associated to this subscription
     * @param bool $notifyNewVersions Send notifications about new package versions?
     * @param DateTime|null $sendNotificationsAfter Send notifications for events after this date/time (if null: current date/time)
     *
     * @return static
     */
    public static function create(UserEntity $user, Package $package, $notifyNewVersions = false, DateTime $sendNotificationsAfter = null)
    {
        $result = new static();
        $result->user = $user;
        $result->package = $package;
        $result->notifyNewVersions = $notifyNewVersions ? true : false;
        $result->sendNotificationsAfter = $sendNotificationsAfter === null ? new DateTime() : null;

        return $result;
    }

    protected function __construct()
    {
    }

    /**
     * Associated user.
     *
     * @ORM\ManyToOne(targetEntity="Concrete\Core\Entity\User\User")
     * @ORM\JoinColumn(name="user", referencedColumnName="uID", nullable=false, onDelete="CASCADE")
     * @ORM\Id
     *
     * @var UserEntity
     */
    protected $user;

    /**
     * Get the user associated to this subscription.
     *
     * @return UserEntity
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Associated package.
     *
     * @ORM\ManyToOne(targetEntity="CommunityTranslation\Entity\Package")
     * @ORM\JoinColumn(name="package", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @ORM\Id
     *
     * @var Package
     */
    protected $package;

    /**
     * Get the package associated to this subscription.
     *
     * @return Package
     */
    public function getPackage()
    {
        return $this->package;
    }

    /**
     * Send notifications for events after this date/time.
     *
     * @ORM\Column(type="datetime", nullable=false, options={"comment": "Send notifications for events after this date/time"})
     *
     * @var DateTime
     */
    protected $sendNotificationsAfter;

    /**
     * Send notifications for events after this date/time.
     *
     * @param DateTime $value
     *
     * @return static
     */
    public function setSendNotificationsAfter(DateTime $value)
    {
        $this->sendNotificationsAfter = $value;

        return $this;
    }

    /**
     * Send notifications for events after this date/time.
     *
     * @return DateTime
     */
    public function getSendNotificationsAfter()
    {
        return $this->sendNotificationsAfter;
    }

    /**
     * Notify user when there are new package versions?
     *
     * @ORM\Column(type="boolean", nullable=false, options={"comment": "Notify user when there are new package versions?"})
     *
     * @var bool
     */
    protected $notifyNewVersions;

    /**
     * Subscribe to new package versions?
     *
     * @param bool
     *
     * @return static
     */
    public function setNotifyNewVersions($value)
    {
        $this->notifyNewVersions = $value ? true : false;

        return $this;
    }

    /**
     *  Subscribe to new package versions?
     *
     * @return bool
     */
    public function notifyNewVersions()
    {
        return $this->notifyNewVersions;
    }
}
