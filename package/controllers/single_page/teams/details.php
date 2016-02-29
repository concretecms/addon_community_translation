<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Teams;

use Concrete\Core\Page\Controller\PageController;
use Concrete\Package\CommunityTranslation\Src\Service\Access;
use Concrete\Package\CommunityTranslation\Src\Exception;

class Details extends PageController
{
    protected function getGroupMembers(\Concrete\Core\User\Group\Group $group)
    {
        $result = array();
        foreach ($group->getGroupMembers() as $m) {
            $result[] = array('ui' => $m, 'since' => $group->getGroupDateTimeEntered($m));
        }
        usort($result, function ($a, $b) {
            return strcasecmp($a['ui']->getUserName(), $b['ui']->getUserName());
        });

        return $result;
    }
    public function view($localeID = '')
    {
        $locale = $localeID ? $this->app->make('community_translation/locale')->find($localeID) : null;
        if ($locale === null || $locale->isSource() || !$locale->isApproved()) {
            $this->redirect('/teams');
        }
        $this->app->make('helper/seo')->addTitleSegment($locale->getDisplayName());
        $this->set('locale', $locale);
        $this->set('token', $this->app->make('helper/validation/token'));
        $this->set('dh', $this->app->make('helper/date'));
        $this->set('access', $this->app->make('community_translation/access')->getLocaleAccess($locale));
        $g = $this->app->make('community_translation/groups');
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
            if (!$token->validate('comtra_join'.$localeID)) {
                throw new Exception($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || !$locale->isApproved() || $locale->isSource()) {
                throw new Exception(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            if ($access !== Access::NONE) {
                throw new Exception(t('Invalid user rights'));
            }
            $this->app->make('community_translation/access')->setLocaleAccess($locale, Access::ASPRIRING);
            $this->flash('message', t('Your request to join the %s translation group has been submitted. Thank you!', $locale->getDisplayName()));
            $this->redirect('/teams/details', $locale->getID());
        } catch (Exception $x) {
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
            if (!$token->validate('comtra_leave'.$localeID)) {
                throw new Exception($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || !$locale->isApproved() || $locale->isSource()) {
                throw new Exception(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            switch ($access) {
                case Access::ASPRIRING:
                    $message = t("Your request to join the '%s' translation group has been canceled.", $locale->getDisplayName());
                    break;
                case Access::ADMIN:
                case Access::TRANSLATE:
                    $message = t("You left the '%s' translation group.", $locale->getDisplayName());
                    break;
                default:
                    throw new Exception(t('Invalid user rights'));
            }
            $this->app->make('community_translation/access')->setLocaleAccess($locale, Access::NONE);
            $this->flash('message', $message);
            $this->redirect('/teams/details', $locale->getID());
        } catch (Exception $x) {
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
            if (!$token->validate('comtra_answer'.$localeID.'#'.$userID.':'.$approve)) {
                throw new Exception($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || !$locale->isApproved() || $locale->isSource()) {
                throw new Exception(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            switch ($access) {
                case Access::ADMIN:
                case Access::GLOBAL_ADMIN:
                    break;
                default:
                    throw new Exception(t('Invalid user rights'));
            }
            $user = \User::getByUserID($userID);
            if ($user) {
                if ($this->app->make('community_translation/access')->getLocaleAccess($locale, $user) !== Access::ASPRIRING) {
                    $user = null;
                }
            }
            if (!$user) {
                throw new Exception(t('Invalid user'));
            }
            if ($approve) {
                $this->app->make('community_translation/access')->setLocaleAccess($locale, Access::TRANSLATE, $user);
                try {
                    $this->app->make('community_translation/notify')->userApproved($locale, $user);
                } catch (\Exception $foo) {
                }
                $this->flash('message', t('User %s has been accepted as translator', $user->getUserName()));
            } else {
                $this->app->make('community_translation/access')->setLocaleAccess($locale, Access::NONE, $user);
                try {
                    $this->app->make('community_translation/notify')->userDenied($locale, $user);
                } catch (\Exception $foo) {
                }
                $this->flash('message', t('The request by %s has been refused', $user->getUserName()));
            }
            $this->redirect('/teams/details', $locale->getID());
        } catch (Exception $x) {
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
            if (!$token->validate('change_access'.$localeID.'#'.$userID.':'.$newAccess)) {
                throw new Exception($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || !$locale->isApproved() || $locale->isSource()) {
                throw new Exception(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $user = \User::getByUserID($userID);
            if (!$user) {
                throw new Exception(t('Invalid user'));
            }
            $myAccess = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            $oldAccess = $this->app->make('community_translation/access')->getLocaleAccess($locale, $user);
            $newAccess = (int) $newAccess;
            if (
                $myAccess < Access::ADMIN
                || $oldAccess > $myAccess || $newAccess > $myAccess
                || $oldAccess < Access::TRANSLATE || $oldAccess > Access::ADMIN
                || $newAccess < Access::NONE || $newAccess > Access::ADMIN
                || $newAccess === $oldAccess
            ) {
                throw new Exception(t('Invalid user rights'));
            }
            $this->app->make('community_translation/access')->setLocaleAccess($locale, $newAccess, $user);
            $this->flash('message', t('The role of %s has been updated', $user->getUserName()));
            $this->redirect('/teams/details', $locale->getID());
        } catch (Exception $x) {
            $this->flash('error', $x->getMessage());
            if ($gotoLocale === null) {
                $this->redirect('/teams');
            } else {
                $this->redirect('/teams/details', $gotoLocale->getID());
            }
        }
    }
}
