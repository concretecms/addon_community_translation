<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

use Concrete\Core\User\User as ConcreteUser;
use Concrete\Core\User\UserInfo;

class User
{
    /**
     * Format a username.
     *
     * @param int|ConcreteUser|UserInfo $user
     *
     * @return string
     */
    public function format($user)
    {
        $id = 0;
        $name = '';
        if (isset($user) && $user) {
            if (is_int($user) || (is_string($user) && is_numeric($user))) {
                $user = \UserInfo::getByID($user);
            }
            if ($user instanceof ConcreteUser && $user->getUserID() && $user->isRegistered()) {
                $id = $user->getUserID();
                $name = $user->getUserName();
            } elseif ($user instanceof UserInfo && $user->getUserID()) {
                $id = (int) $user->getUserID();
                $name = $user->getUserName();
            }
        }
        if ($id === null) {
            return '<i class="comtra-user comtra-user-removed">'.t('removed user').'</i>';
        } elseif ($id == USER_SUPER_ID) {
            return '<i class="comtra-user comtra-user-system">'.t('system').'</i>';
        } else {
            return '<span class="comtra-user comtra-user-system">'.h($name).'</span>';
        }
    }
}
