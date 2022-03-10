<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity;

use Concrete\Core\Entity\User\User as UserEntity;
use DateTimeImmutable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * User's subscription for packages.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\PackageSubscription",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationPackageSubscriptions",
 *     options={
 *         "comment": "User's subscription for packages"
 *     }
 * )
 */
class PackageSubscription
{
    /**
     * Associated user.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Concrete\Core\Entity\User\User")
     * @Doctrine\ORM\Mapping\JoinColumn(name="user", referencedColumnName="uID", nullable=false, onDelete="CASCADE")
     * @Doctrine\ORM\Mapping\Id
     */
    protected UserEntity $user;

    /**
     * Associated package.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Package")
     * @Doctrine\ORM\Mapping\JoinColumn(name="package", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Doctrine\ORM\Mapping\Id
     */
    protected Package $package;

    /**
     * Send notifications for events after this date/time.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime_immutable", nullable=false, options={"comment": "Send notifications for events after this date/time"})
     */
    protected DateTimeImmutable $sendNotificationsAfter;

    /**
     * Notify user when there are new package versions?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment": "Notify user when there are new package versions?"})
     */
    protected bool $notifyNewVersions;

    /**
     * @param \Concrete\Core\Entity\User\User $user The user associated to this subscription
     * @param Package $package The package associated to this subscription
     * @param bool $notifyNewVersions Send notifications about new package versions?
     * @param \DateTimeImmutable $sendNotificationsAfter Send notifications for events after this date/time (if null: current date/time)
     */
    public function __construct(UserEntity $user, Package $package, bool $notifyNewVersions = false, ?DateTimeImmutable $sendNotificationsAfter = null)
    {
        $this->user = $user;
        $this->package = $package;
        $this->notifyNewVersions = $notifyNewVersions;
        $this->sendNotificationsAfter = $sendNotificationsAfter === null ? new DateTimeImmutable() : null;
    }

    /**
     * Get the user associated to this subscription.
     */
    public function getUser(): UserEntity
    {
        return $this->user;
    }

    /**
     * Get the package associated to this subscription.
     */
    public function getPackage(): Package
    {
        return $this->package;
    }

    /**
     * Send notifications for events after this date/time.
     *
     * @return $this
     */
    public function setSendNotificationsAfter(DateTimeImmutable $value): self
    {
        $this->sendNotificationsAfter = $value;

        return $this;
    }

    /**
     * Send notifications for events after this date/time.
     */
    public function getSendNotificationsAfter(): DateTimeImmutable
    {
        return $this->sendNotificationsAfter;
    }

    /**
     * Subscribe to new package versions?
     *
     * @return $this
     */
    public function setNotifyNewVersions(bool $value): self
    {
        $this->notifyNewVersions = $value;

        return $this;
    }

    /**
     *  Subscribe to new package versions?
     */
    public function isNotifyNewVersions(): bool
    {
        return $this->notifyNewVersions;
    }
}
