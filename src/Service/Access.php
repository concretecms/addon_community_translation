<?php

declare(strict_types=1);

namespace CommunityTranslation\Service;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\Application\Application;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Events\EventDispatcher;
use Concrete\Core\User\User as UserObject;
use Symfony\Component\EventDispatcher\GenericEvent;

defined('C5_EXECUTE') or die('Access Denied.');

final class Access
{
    /**
     * User is not logged in.
     *
     * @var int
     */
    public const NOT_LOGGED_IN = 0;

    /**
     * No access.
     *
     * @var int
     */
    public const NONE = 1;

    /**
     * Aspiring to translate a specific locale.
     *
     * @var int
     */
    public const ASPRIRING = 2;

    /**
     * Translate a specific locale.
     *
     * @var int
     */
    public const TRANSLATE = 3;

    /**
     * Manage a specific locale.
     *
     * @var int
     */
    public const ADMIN = 4;

    /**
     * Manage all the locales.
     *
     * @var int
     */
    public const GLOBAL_ADMIN = 5;

    /**
     * The application object.
     */
    private Application $app;

    /**
     * The user service.
     */
    private User $userService;

    /**
     * The groups service.
     */
    private Group $groupService;

    public function __construct(Application $app, User $userService, Group $groupService)
    {
        $this->app = $app;
        $this->userService = $userService;
        $this->groupService = $groupService;
    }

    /**
     * Get the access level to a specific locale.
     *
     * @param \CommunityTranslation\Entity\Locale|string|mixed $wantedLocale
     * @param \Concrete\Core\User\User|\Concrete\Core\Entity\User\User|\Concrete\Core\User\UserInfo|int|'current'|mixed $user
     *
     * @return int One of the Access constants
     */
    public function getLocaleAccess($wantedLocale, $user = User::CURRENT_USER_KEY): int
    {
        $userObject = $this->userService->getUserObject($user);
        if ($userObject === null) {
            return self::NOT_LOGGED_IN;
        }
        if ($userObject->isSuperUser() || $userObject->inGroup($this->groupService->getGlobalAdministrators())) {
            return self::GLOBAL_ADMIN;
        }
        $locale = $this->resolveLocale($wantedLocale);
        if ($locale !== null) {
            if ($userObject->inGroup($this->groupService->getAdministrators($locale))) {
                return self::ADMIN;
            }
            if ($userObject->inGroup($this->groupService->getTranslators($locale))) {
                return self::TRANSLATE;
            }
            if ($userObject->inGroup($this->groupService->getAspiringTranslators($locale))) {
                return self::ASPRIRING;
            }
        }

        return self::NONE;
    }

    /**
     * Set the access level to a specific locale.
     *
     * @param \CommunityTranslation\Entity\Locale|string|mixed $wantedLocale
     * @param int $access One of the Access constants
     * @param \Concrete\Core\User\User|\Concrete\Core\Entity\User\User|\Concrete\Core\User\UserInfo|int|'current'|mixed $user
     *
     * @throws \Concrete\Core\Error\UserMessageException in case of wrong values
     */
    public function setLocaleAccess($wantedLocale, int $access, $user = User::CURRENT_USER_KEY): void
    {
        $userObject = $this->userService->getUserObject($user);
        if ($userObject === null) {
            throw new UserMessageException(t('Invalid user'));
        }
        $locale = $this->resolveLocale($wantedLocale);
        if ($locale === null) {
            throw new UserMessageException(t("The locale identifier '%s' is not valid", is_string($wantedLocale) ? $wantedLocale : gettype($wantedLocale)));
        }
        $oldAccess = $this->getLocaleAccess($locale, $userObject);
        if ($oldAccess === $access) {
            return;
        }
        if ($userObject->isSuperUser()) {
            if ($access !== self::GLOBAL_ADMIN) {
                throw new UserMessageException(t('The super user must be a global locales admin'));
            }

            return;
        }
        if ($access === self::GLOBAL_ADMIN) {
            $this->setGlobalAccess(true, $userObject);

            return;
        }
        if ($oldAccess === self::GLOBAL_ADMIN && $access !== self::NONE) {
            throw new UserMessageException(t('User is a global locale administrator'));
        }
        $eventName = '';
        switch ($access) {
            case self::ADMIN:
                $newGroup = $this->groupService->getAdministrators($locale);
                $eventName = 'user_became_coordinator';
                break;
            case self::TRANSLATE:
                $newGroup = $this->groupService->getTranslators($locale);
                break;
            case self::ASPRIRING:
                $newGroup = $this->groupService->getAspiringTranslators($locale);
                break;
            case self::NONE:
                $newGroup = null;
                break;
            default:
                throw new UserMessageException(t('Invalid access level specified'));
        }
        if ($newGroup !== null) {
            $userObject->enterGroup($newGroup);
        }
        switch ($oldAccess) {
            case self::GLOBAL_ADMIN:
            case self::ADMIN:
                $g = $this->groupService->getAdministrators($locale);
                if ($g !== $newGroup && $userObject->inGroup($g)) {
                    $userObject->exitGroup($g);
                }
                // no break
            case self::TRANSLATE:
                $g = $this->groupService->getTranslators($locale);
                if ($g !== $newGroup && $userObject->inGroup($g)) {
                    $userObject->exitGroup($g);
                }
                // no break
            case self::ASPRIRING:
                $g = $this->groupService->getAspiringTranslators($locale);
                if ($g !== $newGroup && $userObject->inGroup($g)) {
                    $userObject->exitGroup($g);
                }
                break;
        }
        if ($eventName !== '') {
            $this->app->make(EventDispatcher::class)->dispatch("community_translation.{$eventName}", new GenericEvent($userObject, ['locale' => $locale]));
        }
    }

    /**
     * Set or unset global administration access.
     */
    private function setGlobalAccess(bool $enable, UserObject $user): void
    {
        if ($user->getUserID() === USER_SUPER_ID) {
            return;
        }
        $group = $this->groupService->getGlobalAdministrators();
        if ($enable) {
            foreach ($this->app->make(LocaleRepository::class)->getApprovedLocales() as $locale) {
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

    /**
     * Parse the $locale parameter of the functions.
     *
     * @param \CommunityTranslation\Entity\Locale|string|mixed $locale
     */
    private function resolveLocale($locale): ?LocaleEntity
    {
        if ($locale instanceof LocaleEntity) {
            return $locale;
        }
        if (is_string($locale) && $locale !== '') {
            return $this->app->make(LocaleRepository::class)->findApproved($locale);
        }

        return null;
    }
}
