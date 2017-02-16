<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Teams;

use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Service\Access;
use CommunityTranslation\Service\Groups;
use CommunityTranslation\UserException;
use Concrete\Core\Page\Controller\PageController;

class Details extends PageController
{
    protected function getGroupMembers(\Concrete\Core\User\Group\Group $group)
    {
        $result = [];
        foreach ($group->getGroupMembers() as $m) {
            $result[] = ['ui' => $m, 'since' => $group->getGroupDateTimeEntered($m)];
        }
        usort($result, function ($a, $b) {
            return strcasecmp($a['ui']->getUserName(), $b['ui']->getUserName());
        });

        return $result;
    }

    public function view($localeID = '')
    {
        $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
        if ($locale === null) {
            $this->redirect('/teams');
        }
        $this->app->make('helper/seo')->addTitleSegment($locale->getDisplayName());
        $this->set('locale', $locale);
        $this->set('token', $this->app->make('helper/validation/token'));
        $this->set('dh', $this->app->make('helper/date'));
        $this->set('access', $this->app->make(Access::class)->getLocaleAccess($locale));
        $g = $this->app->make(Groups::class);
        $this->set('globalAdmins', $this->getGroupMembers($g->getGlobalAdministrators()));
        $this->set('admins', $this->getGroupMembers($g->getAdministrators($locale)));
        $this->set('translators', $this->getGroupMembers($g->getTranslators($locale)));
        $this->set('aspiring', $this->getGroupMembers($g->getAspiringTranslators($locale)));
    }

    public function join($localeID)
    {
        $gotoLocale = null;
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_join' . $localeID)) {
                throw new UserException($token->getErrorMessage());
            }
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            if ($access !== Access::NONE) {
                throw new UserException(t('Invalid user rights'));
            }
            $this->app->make(Access::class)->setLocaleAccess($locale, Access::ASPRIRING);
            $this->flash('message', t('Your request to join the %s translation group has been submitted. Thank you!', $locale->getDisplayName()));
            $this->redirect('/teams/details', $locale->getID());
        } catch (UserException $x) {
            $this->flash('error', $x->getMessage());
            if ($gotoLocale === null) {
                $this->redirect('/teams');
            } else {
                $this->redirect('/teams/details', $gotoLocale->getID());
            }
        }
    }

    public function leave($localeID)
    {
        $gotoLocale = null;
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_leave' . $localeID)) {
                throw new UserException($token->getErrorMessage());
            }
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            switch ($access) {
                case Access::ASPRIRING:
                    $message = t("Your request to join the '%s' translation group has been canceled.", $locale->getDisplayName());
                    break;
                case Access::ADMIN:
                case Access::TRANSLATE:
                    $message = t("You left the '%s' translation group.", $locale->getDisplayName());
                    break;
                default:
                    throw new UserException(t('Invalid user rights'));
            }
            $this->app->make(Access::class)->setLocaleAccess($locale, Access::NONE);
            $this->flash('message', $message);
            $this->redirect('/teams/details', $locale->getID());
        } catch (UserException $x) {
            $this->flash('error', $x->getMessage());
            if ($gotoLocale === null) {
                $this->redirect('/teams');
            } else {
                $this->redirect('/teams/details', $gotoLocale->getID());
            }
        }
    }

    public function answer($localeID, $userID, $approve)
    {
        $gotoLocale = null;
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_answer' . $localeID . '#' . $userID . ':' . $approve)) {
                throw new UserException($token->getErrorMessage());
            }
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            switch ($access) {
                case Access::ADMIN:
                case Access::GLOBAL_ADMIN:
                    break;
                default:
                    throw new UserException(t('Invalid user rights'));
            }
            $user = \User::getByUserID($userID);
            if ($user) {
                if ($this->app->make(Access::class)->getLocaleAccess($locale, $user) !== Access::ASPRIRING) {
                    $user = null;
                }
            }
            if (!$user) {
                throw new UserException(t('Invalid user'));
            }
            if ($approve) {
                $this->app->make(Access::class)->setLocaleAccess($locale, Access::TRANSLATE, $user);
                $this->app->make(NotificationRepository::class)->newTranslatorApprovedByCurrentUser($locale, $user);
                $this->flash('message', t('User %s has been accepted as translator', $user->getUserName()));
            } else {
                $this->app->make(Access::class)->setLocaleAccess($locale, Access::NONE, $user);
                $this->app->make(NotificationRepository::class)->newTranslatorRejectedByCurrentUser($locale, $user);
                $this->flash('message', t('The request by %s has been refused', $user->getUserName()));
            }
            $this->redirect('/teams/details', $locale->getID());
        } catch (UserException $x) {
            $this->flash('error', $x->getMessage());
            if ($gotoLocale === null) {
                $this->redirect('/teams');
            } else {
                $this->redirect('/teams/details', $gotoLocale->getID());
            }
        }
    }

    public function change_access($localeID, $userID, $newAccess)
    {
        $gotoLocale = null;
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('change_access' . $localeID . '#' . $userID . ':' . $newAccess)) {
                throw new UserException($token->getErrorMessage());
            }
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $user = \User::getByUserID($userID);
            if (!$user) {
                throw new UserException(t('Invalid user'));
            }
            $myAccess = $this->app->make(Access::class)->getLocaleAccess($locale);
            $oldAccess = $this->app->make(Access::class)->getLocaleAccess($locale, $user);
            $newAccess = (int) $newAccess;
            if (
                $myAccess < Access::ADMIN
                || $oldAccess > $myAccess || $newAccess > $myAccess
                || $oldAccess < Access::TRANSLATE || $oldAccess > Access::ADMIN
                || $newAccess < Access::NONE || $newAccess > Access::ADMIN
                || $newAccess === $oldAccess
            ) {
                throw new UserException(t('Invalid user rights'));
            }
            $this->app->make(Access::class)->setLocaleAccess($locale, $newAccess, $user);
            $this->flash('message', t('The role of %s has been updated', $user->getUserName()));
            $this->redirect('/teams/details', $locale->getID());
        } catch (UserException $x) {
            $this->flash('error', $x->getMessage());
            if ($gotoLocale === null) {
                $this->redirect('/teams');
            } else {
                $this->redirect('/teams/details', $gotoLocale->getID());
            }
        }
    }
}
