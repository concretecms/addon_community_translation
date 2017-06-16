<?php

namespace Concrete\Package\CommunityTranslation\Block\TranslationTeams;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Service\Access;
use CommunityTranslation\Service\Groups;
use CommunityTranslation\Service\User as UserService;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\User\Group\Group;
use Concrete\Core\User\User as CoreUser;
use Doctrine\ORM\EntityManager;
use Exception;
use Page;
use Punic\Comparer;
use Throwable;

class Controller extends BlockController
{
    public $helpers = [];

    protected $btTable = 'btCTTranslationTeams';

    protected $btInterfaceWidth = 600;
    protected $btInterfaceHeight = 300;

    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = false;
    protected $btCacheBlockOutputOnPost = false;
    protected $btCacheBlockOutputForRegisteredUsers = false;

    protected $btCacheBlockOutputLifetime = 0; // 86400 = 1 day

    protected $btSupportsInlineEdit = false;
    protected $btSupportsInlineAdd = false;

    public $askNewTeamCID;
    public $askNewTeamLink;

    public function getBlockTypeName()
    {
        return t('Translation teams list and details');
    }

    public function getBlockTypeDescription()
    {
        return t('Allow users to view and manage translation teams.');
    }

    public function add()
    {
        $this->edit();
    }

    public function edit()
    {
        $this->set('askNewTeamCID', null);
        $this->set('askNewTeamLink', '');
        if ($this->askNewTeamCID) {
            $this->set('askNewTeamLinkType', 'cid');
            $this->set('askNewTeamCID', (int) $this->askNewTeamCID);
        } elseif ('' !== (string) $this->askNewTeamLink) {
            $this->set('askNewTeamLinkType', 'link');
            $this->set('askNewTeamLink', $this->askNewTeamLink);
        } else {
            $this->set('askNewTeamLinkType', 'none');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::normalizeArgs()
     */
    protected function normalizeArgs(array $args)
    {
        $error = $this->app->make('helper/validation/error');
        $normalized = [
            'askNewTeamCID' => null,
            'askNewTeamLink' => '',
        ];

        switch (isset($args['askNewTeamLinkType']) ? $args['askNewTeamLinkType'] : '') {
            case 'cid':
                if (isset($args['askNewTeamCID']) && ((is_string($args['askNewTeamCID']) && is_numeric($args['askNewTeamCID'])) || is_int($args['askNewTeamCID']))) {
                    $i = (int) $args['askNewTeamCID'];
                    if ($i > 0) {
                        $normalized['askNewTeamCID'] = $i;
                    }
                }
                break;
            case 'link':
                if (isset($args['askNewTeamLink']) && is_string($args['askNewTeamLink'])) {
                    $normalized['askNewTeamLink'] = trim($args['askNewTeamLink']);
                }
                break;
            }

        return $error->has() ? $error : $normalized;
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::isControllerTaskInstanceSpecific($method)
     */
    protected function isControllerTaskInstanceSpecific($method)
    {
        $method = strtolower($method);
        switch ($method) {
            case 'action_details':
                return false;
            default:
                return true;
        }
    }

    /**
     * @return string
     */
    private function getAskNewTeamURL()
    {
        $result = '';
        if ($this->askNewTeamCID) {
            $askNewTeamPage = Page::getByID($this->askNewTeamCID);
            if (is_object($askNewTeamPage) && !$askNewTeamPage->isError()) {
                $result = (string) $this->app->make('url/manager')->resolve([$askNewTeamPage->getCollectionLink()]);
            }
        } elseif ('' !== (string) $this->askNewTeamLink) {
            $sec = $this->app->make('helper/security');
            $result = $sec->sanitizeURL($this->askNewTeamLink);
        }

        return $result;
    }

    public function view()
    {
        $this->showTeamListStep();
    }

    public function action_details($localeID = '')
    {
        $locale = (is_string($localeID) && $localeID !== '') ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
        if ($locale === null) {
            $this->set('showError', t('Unable to find the specified language'));
            $this->showTeamListStep();

            return;
        }
        $this->showTeamDetailsStep($locale);
    }

    public function action_cancel_new_locale_request($localeID)
    {
        try {
            $token = $this->app->make('token');
            if (!$token->validate('comtra_cancel_locale_request' . $localeID)) {
                $this->redirectWithMessage($token->getErrorMessage(), true, '');
            }
            $locale = (is_string($localeID) && $localeID !== '') ? $this->app->make(LocaleRepository::class)->find($localeID) : null;
            if ($locale === null || $locale->isApproved() || $locale->isSource()) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            switch ($access) {
                case Access::GLOBAL_ADMIN:
                    break;
                default:
                    $me = new CoreUser();
                    if (!$me->isRegistered() || !$locale->getRequestedBy() || $me->getUserID() != $locale->getRequestedBy()->getUserID()) {
                        throw new UserMessageException(t('Invalid user rights'));
                    }
                    break;
            }
            $em = $this->app->make(EntityManager::class);
            $em->remove($locale);
            $em->flush();
            $this->app->make(NotificationRepository::class)->newLocaleRequestRejected($locale, (new CoreUser())->getUserID());
            $this->redirectWithMessage(t("The request to create the '%s' translation group has been canceled", $locale->getDisplayName()), false, '');
        } catch (UserMessageException $x) {
            $this->redirectWithMessage($x->getMessage(), true, '');
        }
    }

    public function action_approve_new_locale_request($localeID)
    {
        try {
            $token = $this->app->make('token');
            if (!$token->validate('comtra_approve_new_locale_request' . $localeID)) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $locale = (is_string($localeID) && $localeID !== '') ? $this->app->make(LocaleRepository::class)->find($localeID) : null;
            if ($locale === null || $locale->isApproved() || $locale->isSource()) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            switch ($access) {
                case Access::GLOBAL_ADMIN:
                    break;
                default:
                    throw new UserMessageException(t('Invalid user rights'));
            }
            $locale->setIsApproved(true);
            $em = $this->app->make(EntityManager::class);
            $em->persist($locale);
            $em->flush();
            if ($locale->getRequestedBy() !== null) {
                try {
                    $requester = CoreUser::getByUserID($locale->getRequestedBy()->getUserID());
                    if ($requester) {
                        $accessHelper = $this->app->make(Access::class);
                        $requesterAccess = $accessHelper->getLocaleAccess($locale, $requester);
                        if ($requesterAccess === Access::NONE) {
                            $accessHelper->setLocaleAccess($locale, Access::TRANSLATE, $requester);
                        }
                    }
                } catch (Exception $foo) {
                } catch (Throwable $foo) {
                }
            }
            $this->app->make(NotificationRepository::class)->newLocaleRequestApproved($locale, (new CoreUser())->getUserID());
            $this->redirectWithMessage(t("The language team for '%s' has been approved", $locale->getDisplayName()), false, '');
        } catch (UserMessageException $x) {
            $this->redirectWithMessage($x->getMessage(), true, '');
        }
    }

    public function action_join_translation_group($localeID)
    {
        $gotoLocale = null;
        try {
            $token = $this->app->make('token');
            if (!$token->validate('comtra_join_translation_group' . $localeID)) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $locale = (is_string($localeID) && $localeID !== '') ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $accessHelper = $this->app->make(Access::class);
            $access = $accessHelper->getLocaleAccess($locale);
            if ($access !== Access::NONE) {
                throw new UserMessageException(t('Invalid user rights'));
            }
            $accessHelper->setLocaleAccess($locale, Access::ASPRIRING);
            $this->redirectWithMessage(t('Your request to join the %s translation group has been submitted. Thank you!', $locale->getDisplayName()), false, 'details', $locale->getID());
        } catch (UserMessageException $x) {
            if ($gotoLocale === null) {
                $this->redirectWithMessage($x->getMessage(), true, '');
            } else {
                $this->redirectWithMessage($x->getMessage(), true, 'details', $gotoLocale->getID());
            }
        }
    }

    public function action_leave_translation_group($localeID)
    {
        try {
            $token = $this->app->make('token');
            if (!$token->validate('comtra_leave_translation_group' . $localeID)) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $locale = (is_string($localeID) && $localeID !== '') ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $accessHelper = $this->app->make(Access::class);
            $access = $accessHelper->getLocaleAccess($locale);
            switch ($access) {
                case Access::ASPRIRING:
                    $message = t("Your request to join the '%s' translation group has been canceled.", $locale->getDisplayName());
                    break;
                case Access::ADMIN:
                case Access::TRANSLATE:
                    $message = t("You left the '%s' translation group.", $locale->getDisplayName());
                    break;
                default:
                    throw new UserMessageException(t('Invalid user rights'));
            }
            $accessHelper->setLocaleAccess($locale, Access::NONE);
            $this->redirectWithMessage($message, false, '');
        } catch (UserMessageException $x) {
            $this->redirectWithMessage($x->getMessage(), true, '');
        }
    }

    public function action_answer_join_request($localeID, $userID, $approve)
    {
        $gotoLocale = null;
        try {
            $token = $this->app->make('token');
            if (!$token->validate('comtra_answer_join_request' . $localeID . '#' . $userID . ':' . $approve)) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $accessHelper = $this->app->make(Access::class);
            $access = $accessHelper->getLocaleAccess($locale);
            switch ($access) {
                case Access::ADMIN:
                case Access::GLOBAL_ADMIN:
                    break;
                default:
                    throw new UserMessageException(t('Invalid user rights'));
            }
            $user = CoreUser::getByUserID($userID);
            if ($user) {
                if ($accessHelper->getLocaleAccess($locale, $user) !== Access::ASPRIRING) {
                    $user = null;
                }
            }
            if (!$user) {
                throw new UserMessageException(t('Invalid user'));
            }
            if ($approve) {
                $accessHelper->setLocaleAccess($locale, Access::TRANSLATE, $user);
                $this->app->make(NotificationRepository::class)->newTranslatorApproved($locale, $user->getUserID(), (new CoreUser())->getUserID(), false);
                $message = t('User %s has been accepted as translator', $user->getUserName());
            } else {
                $accessHelper->setLocaleAccess($locale, Access::NONE, $user);
                $this->app->make(NotificationRepository::class)->newTranslatorRejected($locale, $user->getUserID(), (new CoreUser())->getUserID());
                $message = t('The request by %s has been refused', $user->getUserName());
            }
            $this->redirectWithMessage($message, false, 'details', $locale->getID());
        } catch (UserMessageException $x) {
            if ($gotoLocale === null) {
                $this->redirectWithMessage($x->getMessage(), true, '');
            } else {
                $this->redirectWithMessage($x->getMessage(), true, 'details', $gotoLocale->getID());
            }
        }
    }

    public function action_change_access($localeID, $userID, $newAccess)
    {
        $gotoLocale = null;
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_change_access' . $localeID . '#' . $userID . ':' . $newAccess)) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $user = CoreUser::getByUserID($userID);
            if (!$user) {
                throw new UserMessageException(t('Invalid user'));
            }
            $accessHelper = $this->app->make(Access::class);
            $myAccess = $accessHelper->getLocaleAccess($locale);
            $oldAccess = $accessHelper->getLocaleAccess($locale, $user);
            $newAccess = (int) $newAccess;
            if (
                $myAccess < Access::ADMIN
                || $oldAccess > $myAccess || $newAccess > $myAccess
                || $oldAccess < Access::TRANSLATE || $oldAccess > Access::ADMIN
                || $newAccess < Access::NONE || $newAccess > Access::ADMIN
                || $newAccess === $oldAccess
                ) {
                throw new UserMessageException(t('Invalid user rights'));
            }
            $accessHelper->setLocaleAccess($locale, $newAccess, $user);
            $this->redirectWithMessage(t('The role of %s has been updated', $user->getUserName()), false, 'details', $locale->getID());
        } catch (UserMessageException $x) {
            if ($gotoLocale === null) {
                $this->redirectWithMessage($x->getMessage(), true, '');
            } else {
                $this->redirectWithMessage($x->getMessage(), true, 'details', $gotoLocale->getID());
            }
        }
    }

    public function action_delete_translation_group($localeID)
    {
        $gotoLocale = null;
        try {
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_delete_translation_group' . $localeID)) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $accessHelper = $this->app->make(Access::class);
            $myAccess = $accessHelper->getLocaleAccess($locale);
            if ($myAccess < Access::GLOBAL_ADMIN) {
                throw new UserMessageException(t('Invalid user rights'));
            }
            $em = $this->app->make(EntityManager::class);
            $em->remove($locale);
            $em->flush($locale);
            $this->app->make(Groups::class)->deleteLocaleGroups($locale->getID());
            $this->redirectWithMessage(t('The language team for %s has been deleted', $locale->getDisplayName()), false, '');
        } catch (UserMessageException $x) {
            if ($gotoLocale === null) {
                $this->redirectWithMessage($x->getMessage(), true, '');
            } else {
                $this->redirectWithMessage($x->getMessage(), true, 'details', $gotoLocale->getID());
            }
        }
    }

    private function showTeamListStep()
    {
        $this->set('step', 'teamList');
        $this->set('askNewTeamURL', $this->getAskNewTeamURL());
        $this->requireAsset('jquery/scroll-to');
        $this->requireAsset('jquery/ui');
        $this->set('token', $this->app->make('token'));
        $this->set('dh', $this->app->make('date'));
        $this->set('userService', $this->app->make(UserService::class));
        $me = new CoreUser();
        if (!$me->isRegistered()) {
            $me = null;
        }
        $this->set('me', $me);
        $groups = $this->app->make(Groups::class);
        /* @var \CommunityTranslation\Service\Groups $groups */
        $repo = $this->app->make(LocaleRepository::class);
        $accessHelper = $this->app->make(Access::class);

        $approved = [];
        foreach ($repo->getApprovedLocales() as $locale) {
            $approved[] = [
                'id' => $locale->getID(),
                'name' => $locale->getDisplayName(),
                'access' => $me ? $accessHelper->getLocaleAccess($locale, $me) : null,
                'admins' => (int) $groups->getAdministrators($locale)->getGroupMembersNum(),
                'translators' => (int) $groups->getTranslators($locale)->getGroupMembersNum(),
                'aspiring' => (int) $groups->getAspiringTranslators($locale)->getGroupMembersNum(),
            ];
        }
        $this->set('approved', $approved);

        $requested = [];
        $iAmGlobalAdmin = $me ? ($me->getUserID() == USER_SUPER_ID || $me->inGroup($groups->getGlobalAdministrators())) : false;
        foreach ($repo->findBy(['isApproved' => false]) as $locale) {
            /* @var \CommunityTranslation\Entity\Locale $locale */
            $requested[] = [
                'id' => $locale->getID(),
                'name' => $locale->getDisplayName(),
                'requestedOn' => $locale->getRequestedOn(),
                'requestedBy' => $locale->getRequestedBy(),
                'canApprove' => $iAmGlobalAdmin,
                'canCancel' => $iAmGlobalAdmin || ($me && $locale->getRequestedBy() && $locale->getRequestedBy()->getUserID() == $me->getUserID()),
            ];
        }
        $comparer = new Comparer();
        usort($approved, function ($a, $b) use ($comparer) {
            return $comparer->compare($a['name'], $b['name']);
        });
        $this->set('requested', $requested);
    }

    private function showTeamDetailsStep(LocaleEntity $locale)
    {
        $this->set('step', 'teamDetails');
        $this->app->make('helper/seo')->addTitleSegment($locale->getDisplayName());
        $this->set('locale', $locale);
        $this->set('token', $this->app->make('token'));
        $this->set('dh', $this->app->make('helper/date'));
        $this->set('userService', $this->app->make(UserService::class));
        $this->set('access', $this->app->make(Access::class)->getLocaleAccess($locale));
        $g = $this->app->make(Groups::class);
        $this->set('globalAdmins', $this->getGroupMembers($g->getGlobalAdministrators()));
        $this->set('admins', $this->getGroupMembers($g->getAdministrators($locale)));
        $this->set('translators', $this->getGroupMembers($g->getTranslators($locale)));
        $this->set('aspiring', $this->getGroupMembers($g->getAspiringTranslators($locale)));
    }

    private function getGroupMembers(Group $group)
    {
        $result = [];
        foreach ($group->getGroupMembers() as $m) {
            $result[] = ['ui' => $m, 'since' => $group->getGroupDateTimeEntered($m)];
        }
        $comparer = new Comparer();
        usort($result, function ($a, $b) use ($comparer) {
            return $comparer->compare($a['ui']->getUserName(), $b['ui']->getUserName());
        });

        return $result;
    }
}
