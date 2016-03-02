<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

use Concrete\Core\Application\Application;
use Concrete\Core\User\User;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Concrete\Package\CommunityTranslation\Src\UserException;

class Access implements \Concrete\Core\Application\ApplicationAwareInterface
{
    /**
     * User is not logged in.
     *
     * @var int
     */
    const NOT_LOGGED_IN = 0;

    /**
     * No access.
     *
     * @var int
     */
    const NONE = 1;

    /**
     * Aspiring to translate a specific locale.
     *
     * @var int
     */
    const ASPRIRING = 2;

    /**
     * Translate a specific locale.
     *
     * @var int
     */
    const TRANSLATE = 3;

    /**
     * Manage a specific locale.
     *
     * @var int
     */
    const ADMIN = 4;

    /**
     * Manage a specific locale.
     *
     * @var int
     */
    const GLOBAL_ADMIN = 5;

    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * Set the application object.
     *
     * @param Application $application
     */
    public function setApplication(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Parse the $user parameter of the functions.
     *
     * @param mixed $user
     *
     * @return User|null
     */
    protected function getUser($user)
    {
        if ($user === 'current') {
            $user = new \User();
        } elseif (is_int($user) || (is_string($user) && is_numeric($user))) {
            $user = \User::getByUserID($user);
        }

        return (is_object($user) && $user->isRegistered()) ? $user : null;
    }

    /**
     * Get the access level to a specific locale.
     *
     * @param Locale|string $locale
     * @param User|int $user
     *
     * @return int One of the Access constants
     */
    public function getLocaleAccess($locale, $user = 'current')
    {
        $result = self::NONE;
        $user = $this->getUser($user);
        if ($user === null) {
            $result = self::NOT_LOGGED_IN;
        } else {
            $result = self::NONE;
            if ($user->getUserID() == USER_SUPER_ID) {
                $result = self::GLOBAL_ADMIN;
            } else {
                if (!$locale instanceof Locale) {
                    $locale = $this->app->make('community_translation/locale')->find($locale);
                }
                if ($locale !== null) {
                    $groups = $this->app->make('community_translation/groups');
                    /* @var \Concrete\Package\CommunityTranslation\Src\Service\Groups */
                    if ($user->inGroup($groups->getGlobalAdministrators())) {
                        $result = self::GLOBAL_ADMIN;
                    } elseif ($user->inGroup($groups->getAdministrators($locale))) {
                        $result = self::ADMIN;
                    } elseif ($user->inGroup($groups->getTranslators($locale))) {
                        $result = self::TRANSLATE;
                    } elseif ($user->inGroup($groups->getAspiringTranslators($locale))) {
                        $result = self::ASPRIRING;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get the access level to a specific locale.
     *
     * @param Locale|string $locale
     * @param int $access One of the Access constants
     * @param User|int $user
     *
     * @return int One of the Access constants
     */
    public function setLocaleAccess($locale, $access, $user = 'current')
    {
        $user = $this->getUser($user);
        if ($user === null) {
            throw new UserException(t('Invalid user'));
        }
        if (!$locale instanceof Locale) {
            $l = $this->app->make('community_translation/locale')->find($locale);
            if ($l === null) {
                throw new UserException(t("The locale identifier '%s' is not valid", $locale));
            }
            $locale = $locale;
        }
        if ($user->getUserID() === USER_SUPER_ID) {
            return;
        }
        $access = (int) $access;
        if ($access === self::GLOBAL_ADMIN) {
            $this->setGlobalAccess(true, $user);

            return;
        }
        $oldAccess = $this->getLocaleAccess($locale, $user);
        if ($oldAccess === self::GLOBAL_ADMIN && $access !== self::NONE) {
            throw new UserException(t('User is a global locale administrator'));
        }
        $groups = $this->app->make('community_translation/groups');
        switch ($access) {
            case self::ADMIN:
                $newGroup = $groups->getAdministrators($locale);
                break;
            case self::TRANSLATE:
                $newGroup = $groups->getTranslators($locale);
                break;
            case self::ASPRIRING:
                $newGroup = $groups->getAspiringTranslators($locale);
                break;
            case self::NONE:
                $newGroup = null;
                break;
            default:
                throw new UserException(t('Invalid access level specified'));
        }
        if ($newGroup !== null) {
            $user->enterGroup($newGroup);
        }
        switch ($oldAccess) {
            case self::GLOBAL_ADMIN:
                /* @noinspection PhpMissingBreakStatementInspection */
            case self::ADMIN:
                $g = $groups->getAdministrators($locale);
                if ($g !== $newGroup && $user->inGroup($g)) {
                    $user->exitGroup($g);
                }
                /* @noinspection PhpMissingBreakStatementInspection */
            case self::TRANSLATE:
                $g = $groups->getTranslators($locale);
                if ($g !== $newGroup && $user->inGroup($g)) {
                    $user->exitGroup($g);
                }
                /* @noinspection PhpMissingBreakStatementInspection */
            case self::ASPRIRING:
                $g = $groups->getAspiringTranslators($locale);
                if ($g !== $newGroup && $user->inGroup($g)) {
                    $user->exitGroup($g);
                }
                break;
        }
    }

    /**
     * Set or unset global administr.
     *
     * @param unknown $enable
     * @param string $user
     */
    public function setGlobalAccess($enable, $user = 'current')
    {
        $user = $this->getUser($user);
        if ($user === null) {
            throw new UserException(t('Invalid user'));
        }
        if ($user->getUserID() === USER_SUPER_ID) {
            return;
        }
        $group = $groups->getGlobalAdministrators();
        if ($enable) {
            foreach ($this->app->make('community_translation/locale')->findBy(array('lIsApproved' => true)) as $locale) {
                $this->setLocaleAccess($locale, self::NONE, $user);
            }
            if (!$user->inGroup($group)) {
                $user->enterGroup($group);
            }
        } else {
            if ($user->inGroup($group)) {
                $user->exitGroup($group);
            }
        }
    }
}
