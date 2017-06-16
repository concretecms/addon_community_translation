<?php

namespace CommunityTranslation\Entity;

use Concrete\Core\Entity\User\User as UserEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * User's subscription for package versions.
 *
 * @ORM\Entity(
 *     repositoryClass="CommunityTranslation\Repository\PackageVersionSubscription",
 * )
 * @ORM\Table(
 *     name="CommunityTranslationPackageVersionSubscriptions",
 *     options={
 *         "comment": "User's subscription for package versions"
 *     }
 * )
 */
class PackageVersionSubscription
{
    /**
     * @param UserEntity $user User associated to this subscription
     * @param Package $package package associated to this subscription
     * @param bool $notifyUpdates Send notifications about updates to this package versions?
     *
     * @return static
     */
    public static function create(UserEntity $user, Package\Version $packageVersion, $notifyUpdates)
    {
        $result = new static();
        $result->user = $user;
        $result->packageVersion = $packageVersion;
        $result->notifyUpdates = $notifyUpdates ? true : false;

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
     * Get the package version associated to this subscription.
     *
     * @return Package\Version
     */
    public function getPackageVersion()
    {
        return $this->packageVersion;
    }

    /**
     * Notify updates to this package version?
     *
     * @ORM\Column(type="boolean", nullable=false, options={"comment": "Notify updates to this package version?"})
     *
     * @var bool
     */
    protected $notifyUpdates;

    /**
     * Notify updates to this package version?
     *
     * @param bool $value
     *
     * @return static
     */
    public function setNotifyUpdates($value)
    {
        $this->notifyUpdates = $value ? true : false;

        return $this;
    }

    /**
     * Notify updates to this package version?
     *
     * @return bool
     */
    public function notifyUpdates()
    {
        return $this->notifyUpdates;
    }
}
