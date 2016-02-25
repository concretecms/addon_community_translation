<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage;

use Concrete\Core\Page\Controller\PageController;
use Concrete\Package\CommunityTranslation\Src\Exception;
use Concrete\Package\CommunityTranslation\Src\Service\Access;

class Teams extends PageController
{
    public function view()
    {
        $this->requireAsset('community_translation/common');
        $this->set('token', $this->app->make('helper/validation/token'));
        $this->set('dh', $this->app->make('helper/date'));
        $me = new \User();
        if (!$me->isLoggedIn()) {
            $me = null;
        }
        $this->set('me', $me);
        $repo = $this->app->make('community_translation/locale');
        $access = $this->app->make('community_translation/access');
        $approved = array();
        foreach ($repo->findBy(array('lIsApproved' => true, 'lIsSource' => false)) as $locale) {
            $approved[] = array(
                'id' => $locale->getID(),
                'name' => $locale->getDisplayName(),
                'access' => $me ? $access->getLocaleAccess($locale, $me) : null,
            );
        }
        usort($approved, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        $this->set('approved', $approved);

        $requested = array();
        $iAmGlobalAdmin = $me ? ($me->getUserID() == USER_SUPER_ID || $me->inGroup($this->app->make('community_translation/groups')->getGlobalAdministrators())) : false;
        foreach ($repo->findBy(array('lIsApproved' => false)) as $locale) {
            $requested[] = array(
                'id' => $locale->getID(),
                'name' => $locale->getDisplayName(),
                'requestedOn' => $locale->getRequestedOn(),
                'requestedBy' => \User::getByUserID($locale->getRequestedBy()),
                'canApprove' => $iAmGlobalAdmin,
                'canCancel' => $iAmGlobalAdmin || ($me && $locale->getRequestedBy() == $me->getUserID()),
            );
        }
        usort($approved, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        $this->set('requested', $requested);
    }

    public function join($localeID)
    {
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_join'.$localeID)) {
                throw new Exception($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || !$locale->isApproved() || $locale->isSource()) {
                throw new Exception(t("The locale identifier '%s' is not valid", $localeID));
            }
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            if ($access !== Access::NONE) {
                throw new Exception(t('Invalid user rights'));
            }
            $this->app->make('community_translation/access')->setLocaleAccess($locale, Access::ASPRIRING);
            $this->flash('message', t('Your request to join the %s translation group has been submitted. Thank you!', $locale->getDisplayName()));
            $this->redirect('/teams');
        } catch (Exception $x) {
            $this->flash('error', $x->getMessage());
            $this->redirect('/teams');
        }
    }

    public function cancel_request($localeID)
    {
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_cancel_request'.$localeID)) {
                throw new Exception($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || !$locale->isApproved() || $locale->isSource()) {
                throw new Exception(t("The locale identifier '%s' is not valid", $localeID));
            }
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            if ($access !== Access::ASPRIRING) {
                throw new Exception(t('Invalid user rights'));
            }
            $this->app->make('community_translation/access')->setLocaleAccess($locale, Access::NONE);
            $this->flash('message', t('Your request to join the %s translation group has been submitted. Thank you!'));
            $this->redirect('/teams');
        } catch (Exception $x) {
            $this->flash('error', $x->getMessage());
            $this->redirect('/teams');
        }
    }

    public function leave($localeID)
    {
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_leave'.$localeID)) {
                throw new Exception($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || !$locale->isApproved() || $locale->isSource()) {
                throw new Exception(t("The locale identifier '%s' is not valid", $localeID));
            }
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
            $this->redirect('/teams');
        } catch (Exception $x) {
            $this->flash('error', $x->getMessage());
            $this->redirect('/teams');
        }
    }

    public function approve_locale($localeID)
    {
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_approve_locale'.$localeID)) {
                throw new Exception($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || $locale->isApproved() || $locale->isSource()) {
                throw new Exception(t("The locale identifier '%s' is not valid", $localeID));
            }
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            switch ($access) {
                case Access::GLOBAL_ADMIN:
                    break;
                default:
                    throw new Exception(t('Invalid user rights'));
            }
            $locale->setIsApproved(true);
            $em = $this->app->make('community_translation/em');
            $em->persist($locale);
            $em->flush();
            $this->flash('message', t("The '%s' translation group has been approved", $locale->getDisplayName()));
            try {
                $this->app->make('director')->dispatch('community_translation.on_locale_approved', new \Concrete\Package\CommunityTranslation\Src\Service\Event\LocaleApproved($locale));
                
            } catch (\Exception $foo) {
            }
            $this->redirect('/teams');
        } catch (Exception $x) {
            $this->flash('error', $x->getMessage());
            $this->redirect('/teams');
        }
    }

    public function cancel_locale($localeID)
    {
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_cancel_locale'.$localeID)) {
                throw new Exception($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || $locale->isApproved() || $locale->isSource()) {
                throw new Exception(t("The locale identifier '%s' is not valid", $localeID));
            }
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            switch ($access) {
                 case Access::GLOBAL_ADMIN:
                     break;
                 default:
                     $me = new \User();
                     if (!$me->isLoggedIn() || $me->getUserID() != $locale->getRequestedBy()) {
                         throw new Exception(t('Invalid user rights'));
                     }
             }
            $em = $this->app->make('community_translation/em');
            $em->remove($locale);
            $em->flush();
            $this->flash('message', t("The request to create the '%s' translation group has been canceled", $locale->getDisplayName()));
            $this->redirect('/teams');
        } catch (Exception $x) {
            $this->flash('error', $x->getMessage());
            $this->redirect('/teams');
        }
    }
}
