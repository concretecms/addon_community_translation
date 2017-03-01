<?php
namespace CommunityTranslation\Service;

use Concrete\Core\Application\Application;
use Concrete\Core\Entity\User\User as ConcreteUserEntity;
use Concrete\Core\User\User as ConcreteUser;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;

class User
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Format a username.
     *
     * @param int|ConcreteUser|UserInfo|ConcreteUserEntity $user
     *
     * @return string
     */
    public function format($user)
    {
        $id = null;
        $name = '';
        $userInfo = null;
        if (isset($user) && $user) {
            if (is_int($user) || (is_string($user) && is_numeric($user))) {
                $user = \User::getByUserID($user);
            }
            if ($user instanceof ConcreteUser && $user->getUserID() && $user->isRegistered()) {
                $id = (int) $user->getUserID();
                $name = $user->getUserName();
            } elseif ($user instanceof UserInfo && $user->getUserID()) {
                $id = (int) $user->getUserID();
                $name = $user->getUserName();
                $userInfo = $user;
            } elseif ($user instanceof ConcreteUserEntity && $user->getUserID()) {
                $id = (int) $user->getUserID();
                $name = $user->getUserName();
            }
        }
        if ($id === null) {
            $result = '<i class="comtra-user comtra-user-removed">' . t('removed user') . '</i>';
        } elseif ($id == USER_SUPER_ID) {
            $result = '<i class="comtra-user comtra-user-system">' . t('system') . '</i>';
        } else {
            $profileURL = null;
            if ($userInfo === null) {
                $userInfo = $this->app->make(UserInfoRepository::class)->getByID($id);
            }
            if ($userInfo !== null) {
                $profileURL = $userInfo->getUserPublicProfileUrl();
            }
            if ($profileURL === null) {
                $result = '<span';
            } else {
                $result = '<a href="' . h((string) $profileURL) . '"';
            }
            $result .= ' class="comtra-user comtra-user-found">' . h($name);
            if ($profileURL === null) {
                $result .= '</span>';
            } else {
                $result .= '</a>';
            }
        }

        return $result;
    }
}
