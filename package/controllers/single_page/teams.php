<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage;

use Concrete\Core\Page\Controller\PageController;
use Concrete\Package\CommunityTranslation\Src\UserException;
use Concrete\Package\CommunityTranslation\Src\Service\Access;

class Teams extends PageController
{
    public function view()
    {
        $this->requireAsset('jquery/scroll-to');
        $this->requireAsset('community_translation/common');
        $this->requireAsset('jquery/ui');
        $this->set('token', $this->app->make('helper/validation/token'));
        $this->set('dh', $this->app->make('helper/date'));
        $me = new \User();
        if (!$me->isRegistered()) {
            $me = null;
        }
        $groups = $this->app->make('community_translation/groups');
        /* @var \Concrete\Package\CommunityTranslation\Src\Service\Groups $groups */
        $this->set('me', $me);
        $repo = $this->app->make('community_translation/locale');
        $access = $this->app->make('community_translation/access');
        $approved = array();
        foreach ($repo->findBy(array('lIsApproved' => true, 'lIsSource' => false)) as $locale) {
            $approved[] = array(
                'id' => $locale->getID(),
                'name' => $locale->getDisplayName(),
                'access' => $me ? $access->getLocaleAccess($locale, $me) : null,
                'translators' => ((int) $groups->getAdministrators($locale)->getGroupMembersNum()) + ((int) $groups->getTranslators($locale)->getGroupMembersNum()),
                'aspiring' => (int) $groups->getAspiringTranslators($locale)->getGroupMembersNum(),
            );
        }
        usort($approved, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        $this->set('approved', $approved);

        $requested = array();
        $iAmGlobalAdmin = $me ? ($me->getUserID() == USER_SUPER_ID || $me->inGroup($groups->getGlobalAdministrators())) : false;
        foreach ($repo->findBy(array('lIsApproved' => false)) as $locale) {
            $requested[] = array(
                'id' => $locale->getID(),
                'name' => $locale->getDisplayName(),
                'requestedOn' => $locale->getRequestedOn(),
                'requestedBy' => $locale->getRequestedBy(),
                'canApprove' => $iAmGlobalAdmin,
                'canCancel' => $iAmGlobalAdmin || ($me && $locale->getRequestedBy() == $me->getUserID()),
            );
        }
        usort($approved, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        $this->set('requested', $requested);
    }

    public function cancel_request($localeID)
    {
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_cancel_request'.$localeID)) {
                throw new UserException($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || !$locale->isApproved() || $locale->isSource()) {
                throw new UserException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            if ($access !== Access::ASPRIRING) {
                throw new UserException(t('Invalid user rights'));
            }
            $this->app->make('community_translation/access')->setLocaleAccess($locale, Access::NONE);
            $this->flash('message', t('Your request to join the %s translation group has been canceled. Thank you!', $locale->getDisplayName()));
            $this->redirect('/teams');
        } catch (UserException $x) {
            $this->flash('error', $x->getMessage());
            $this->redirect('/teams');
        }
    }

    public function leave($localeID)
    {
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_leave'.$localeID)) {
                throw new UserException($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || !$locale->isApproved() || $locale->isSource()) {
                throw new UserException(t("The locale identifier '%s' is not valid", $localeID));
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
                    throw new UserException(t('Invalid user rights'));
            }
            $this->app->make('community_translation/access')->setLocaleAccess($locale, Access::NONE);
            $this->flash('message', $message);
            $this->redirect('/teams');
        } catch (UserException $x) {
            $this->flash('error', $x->getMessage());
            $this->redirect('/teams');
        }
    }

    public function approve_locale($localeID)
    {
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_approve_locale'.$localeID)) {
                throw new UserException($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || $locale->isApproved() || $locale->isSource()) {
                throw new UserException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            switch ($access) {
                case Access::GLOBAL_ADMIN:
                    break;
                default:
                    throw new UserException(t('Invalid user rights'));
            }
            $locale->setIsApproved(true);
            $em = $this->app->make('community_translation/em');
            $em->persist($locale);
            $em->flush();
            try {
                $requester = \User::getByUserID($locale->getRequestedBy());
                if ($requester) {
                    $requesterAccess = $this->app->make('community_translation/access')->getLocaleAccess($locale, $requester);
                    if ($requesterAccess === Access::NONE) {
                        $this->app->make('community_translation/access')->setLocaleAccess($locale, Access::TRANSLATE, $requester);
                    }
                }
            } catch (\Exception $foo) {
            }
            try {
                $this->app->make('community_translation/notify')->newLocaleApproved($locale, new \User());
            } catch (\Exception $foo) {
            }
            $this->redirect('/teams', 'approved', $locale->getID());
        } catch (UserException $x) {
            $this->flash('error', $x->getMessage());
            $this->redirect('/teams');
        }
    }

    public function cancel_locale($localeID)
    {
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_cancel_locale'.$localeID)) {
                throw new UserException($token->getErrorMessage());
            }
            $locale = $this->app->make('community_translation/locale')->find($localeID);
            if ($locale === null || $locale->isApproved() || $locale->isSource()) {
                throw new UserException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            switch ($access) {
                 case Access::GLOBAL_ADMIN:
                     break;
                 default:
                     $me = new \User();
                     if (!$me->isRegistered() || $me->getUserID() != $locale->getRequestedBy()) {
                         throw new UserException(t('Invalid user rights'));
                     }
                     break;
             }
            $em = $this->app->make('community_translation/em');
            $em->remove($locale);
            $em->flush();
            $this->flash('message', t("The request to create the '%s' translation group has been canceled", $locale->getDisplayName()));
            $this->redirect('/teams');
        } catch (UserException $x) {
            $this->flash('error', $x->getMessage());
            $this->redirect('/teams');
        }
    }

    public function requested($localeID)
    {
        $locale = $this->app->make('community_translation/locale')->find($localeID);
        if ($locale !== null) {
            $this->set('message', t("The language team for '%s' has been requested. Thank you!", $locale->getDisplayName()));
            $this->set('highlightLocale', $locale->getID());
        }
        $this->view();
    }

    public function created($localeID)
    {
        $locale = $this->app->make('community_translation/locale')->find($localeID);
        if ($locale !== null) {
            $this->set('message', t("The language team for '%s' has been created", $locale->getDisplayName()));
            $this->set('highlightLocale', $locale->getID());
        }
        $this->view();
    }

    public function approved($localeID)
    {
        $locale = $this->app->make('community_translation/locale')->find($localeID);
        if ($locale !== null) {
            $this->set('message', t("The language team for '%s' has been approved", $locale->getDisplayName()));
            $this->set('highlightLocale', $locale->getID());
        }
        $this->view();
    }
}
