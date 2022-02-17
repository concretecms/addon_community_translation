<?php

declare(strict_types=1);

namespace CommunityTranslation\Service;

use Concrete\Core\Application\Application;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\User\User as UserObject;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * User-related service class.
 */
final class User
{
    public const CURRENT_USER_KEY = 'current';

    private EntityManagerInterface $em;

    private UserInfoRepository $userInfoRepository;

    private Application $app;

    public function __construct(EntityManagerInterface $em, UserInfoRepository $userInfoRepository, Application $app)
    {
        $this->em = $em;
        $this->userInfoRepository = $userInfoRepository;
        $this->app = $app;
    }

    public function isLoggedIn(): bool
    {
        return $this->getUserObject(self::CURRENT_USER_KEY) !== null;
    }

    /**
     * Resolve a User service instance.
     *
     * @param \Concrete\Core\User\User|\Concrete\Core\Entity\User\User|\Concrete\Core\User\UserInfo|int|'current'|mixed $user
     */
    public function getUserObject($user): ?UserObject
    {
        if ($user === self::CURRENT_USER_KEY) {
            $user = $this->app->make(UserObject::class);
        } elseif ($user instanceof UserEntity) {
            $user = UserObject::getByUserID($user->getUserID());
        } elseif ($user instanceof UserInfo) {
            $user = $user->getUserObject();
        } elseif (is_numeric($user) && $user) {
            $user = UserObject::getByUserID((int) $user);
        }
        if ($user instanceof UserObject) {
            return $user->isRegistered() ? $user : null;
        }

        return null;
    }

    /**
     * Resolve a User entity instance.
     *
     * @param \Concrete\Core\User\User|\Concrete\Core\Entity\User\User|\Concrete\Core\User\UserInfo|int|'current'|mixed $user
     */
    public function getUserEntity($user): ?UserEntity
    {
        if ($user instanceof UserEntity) {
            return $user;
        }
        $u = $this->getUserObject($user);
        if ($u === null) {
            return null;
        }

        return $this->em->find(UserEntity::class, (int) $u->getUserID());
    }

    /**
     * Resolve a User info instance.
     *
     * @param \Concrete\Core\User\User|\Concrete\Core\Entity\User\User|\Concrete\Core\User\UserInfo|int|'current'|mixed $user
     */
    public function getUserInfo($user): ?UserInfo
    {
        if ($user instanceof UserInfo) {
            return $user;
        }
        $u = $this->getUserObject($user);
        if ($u === null) {
            return null;
        }

        return $this->userInfoRepository->getByID((int) $u->getUserID());
    }

    /**
     * Format a username.
     *
     * @param \Concrete\Core\User\User|\Concrete\Core\Entity\User\User|\Concrete\Core\User\UserInfo|int|'current'|mixed $user
     */
    public function format($user, bool $openInNewWindow = false): string
    {
        $userInfo = $this->getUserInfo($user);
        if ($userInfo === null) {
            return '<i class="comtra-user comtra-user-removed">' . t('removed user') . '</i>';
        }
        if ((int) $userInfo->getUserID() === USER_SUPER_ID) {
            return '<i class="comtra-user comtra-user-system">' . t('system') . '</i>';
        }
        $profileURL = (string) $userInfo->getUserPublicProfileUrl();
        if ($profileURL === '') {
            $open = '<span';
            $close = '</span>';
        } else {
            $open = '<a href="' . h($profileURL) . '"';
            if ($openInNewWindow) {
                $open .= ' target="_blank"';
            }
            $close = '</a>';
        }

        return $open . ' class="comtra-user comtra-user-found">' . h($userInfo->getUserName()) . $close;
    }
}
