<?php

declare(strict_types=1);

namespace CommunityTranslation\Entity;

use Concrete\Core\Entity\User\User as UserEntity;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * User's subscription for package versions.
 *
 * @Doctrine\ORM\Mapping\Entity(
 *     repositoryClass="CommunityTranslation\Repository\PackageVersionSubscription",
 * )
 * @Doctrine\ORM\Mapping\Table(
 *     name="CommunityTranslationPackageVersionSubscriptions",
 *     options={
 *         "comment": "User's subscription for package versions"
 *     }
 * )
 */
class PackageVersionSubscription
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
     * Associated package version.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CommunityTranslation\Entity\Package\Version")
     * @Doctrine\ORM\Mapping\JoinColumn(name="packageVersion", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Doctrine\ORM\Mapping\Id
     */
    protected Package\Version $packageVersion;

    /**
     * Notify updates to this package version?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment": "Notify updates to this package version?"})
     */
    protected bool $notifyUpdates;

    /**
     * @param \Concrete\Core\Entity\User\User $user The user associated to this subscription
     * @param \CommunityTranslation\Entity\Package\Version $packageVersion The package associated to this subscription
     * @param bool $notifyUpdates Send notifications about updates to this package versions?
     */
    public function __construct(UserEntity $user, Package\Version $packageVersion, bool $notifyUpdates)
    {
        $this->user = $user;
        $this->packageVersion = $packageVersion;
        $this->notifyUpdates = $notifyUpdates;
    }

    /**
     * Get the user associated to this subscription.
     */
    public function getUser(): UserEntity
    {
        return $this->user;
    }

    /**
     * Get the package version associated to this subscription.
     */
    public function getPackageVersion(): Package\Version
    {
        return $this->packageVersion;
    }

    /**
     * Notify updates to this package version?
     *
     * @return $this
     */
    public function setNotifyUpdates(bool $value): self
    {
        $this->notifyUpdates = $value;

        return $this;
    }

    /**
     * Notify updates to this package version?
     */
    public function isNotifyUpdates(): bool
    {
        return $this->notifyUpdates;
    }
}
