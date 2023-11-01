<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Block\TranslationTeams;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Service\Access;
use CommunityTranslation\Service\Group as GroupService;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Form\Service\DestinationPicker\DestinationPicker;
use Concrete\Core\Page\Page;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Concrete\Core\User\Group\Group;
use Concrete\Core\User\User as UserObject;
use Doctrine\ORM\EntityManager;
use Punic\Comparer;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends BlockController
{
    /**
     * @var int|string|null
     */
    public $askNewTeamCID;

    /**
     * @var string|null
     */
    public $askNewTeamLink;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$helpers
     */
    protected $helpers = [];

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btTable
     */
    protected $btTable = 'btCTTranslationTeams';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btInterfaceWidth
     */
    protected $btInterfaceWidth = 600;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btInterfaceHeight
     */
    protected $btInterfaceHeight = 300;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockRecord
     */
    protected $btCacheBlockRecord = true;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutput
     */
    protected $btCacheBlockOutput = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputOnPost
     */
    protected $btCacheBlockOutputOnPost = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputForRegisteredUsers
     */
    protected $btCacheBlockOutputForRegisteredUsers = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputLifetime
     */
    protected $btCacheBlockOutputLifetime = 0; // 86400 = 1 day

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btSupportsInlineEdit
     */
    protected $btSupportsInlineEdit = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btSupportsInlineAdd
     */
    protected $btSupportsInlineAdd = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::getBlockTypeName()
     */
    public function getBlockTypeName()
    {
        return t('Translation teams list and details');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::getBlockTypeDescription()
     */
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
        $this->set('form', $this->app->make('helper/form'));
        $this->set('destinationPicker', $this->app->make(DestinationPicker::class));
        $this->set('askNewTeamConfig', $this->getDestinationPickerConfiguration());
        if ($this->askNewTeamCID) {
            $this->set('askNewTeamHandle', 'page');
            $this->set('askNewTeamValue', (int) (int) $this->askNewTeamCID);
        } elseif ((string) $this->askNewTeamLink !== '') {
            $this->set('askNewTeamHandle', 'external_url');
            $this->set('askNewTeamValue', $this->askNewTeamLink);
        } else {
            $this->set('askNewTeamHandle', 'none');
            $this->set('askNewTeamValue', null);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::registerViewAssets()
     */
    public function registerViewAssets($outputContent = '')
    {
        $this->requireAsset('javascript', 'jquery');
    }

    public function view(): ?Response
    {
        $this->showTeamListStep();

        return null;
    }

    public function action_details(string $localeID = ''): ?Response
    {
        $locale = $localeID !== '' ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
        if ($locale === null) {
            $this->set('showError', t('Unable to find the specified language'));
            $this->showTeamListStep();
        } else {
            $this->showTeamDetailsStep($locale);
        }

        return null;
    }

    public function action_cancel_new_locale_request(string $localeID): Response
    {
        try {
            $token = $this->app->make('token');
            if (!$token->validate('comtra_cancel_locale_request' . $localeID)) {
                return $this->redirectWithMessage($token->getErrorMessage(), true, '');
            }
            $locale = $localeID !== '' ? $this->app->make(LocaleRepository::class)->find($localeID) : null;
            if ($locale === null || $locale->isApproved() || $locale->isSource()) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            $me = $this->app->make(UserObject::class);
            switch ($access) {
                case Access::GLOBAL_ADMIN:
                    break;
                default:
                    if (!$me->isRegistered() || !$locale->getRequestedBy() || $me->getUserID() != $locale->getRequestedBy()->getUserID()) {
                        throw new UserMessageException(t('Invalid user rights'));
                    }
                    break;
            }
            $em = $this->app->make(EntityManager::class);
            $em->remove($locale);
            $em->flush();
            $this->app->make(NotificationRepository::class)->newLocaleRequestRejected($locale, (int) $me->getUserID());

            return $this->redirectWithMessage(t("The request to create the '%s' translation group has been canceled", $locale->getDisplayName()), false, '');
        } catch (UserMessageException $x) {
            return $this->redirectWithMessage($x->getMessage(), true, '');
        }
    }

    public function action_approve_new_locale_request(string $localeID): Response
    {
        try {
            $token = $this->app->make('token');
            if (!$token->validate('comtra_approve_new_locale_request' . $localeID)) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $locale = $localeID !== '' ? $this->app->make(LocaleRepository::class)->find($localeID) : null;
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
                    $requester = UserObject::getByUserID($locale->getRequestedBy()->getUserID());
                    if ($requester) {
                        $accessService = $this->app->make(Access::class);
                        $requesterAccess = $accessService->getLocaleAccess($locale, $requester);
                        if ($requesterAccess === Access::NONE) {
                            $accessService->setLocaleAccess($locale, Access::TRANSLATE, $requester);
                        }
                    }
                } catch (Throwable $foo) {
                }
            }
            $me = $this->app->make(UserObject::class);
            $this->app->make(NotificationRepository::class)->newLocaleRequestApproved($locale, (int) $me->getUserID());

            return $this->redirectWithMessage(t("The language team for '%s' has been approved", $locale->getDisplayName()), false, '');
        } catch (UserMessageException $x) {
            return $this->redirectWithMessage($x->getMessage(), true, '');
        }
    }

    public function action_join_translation_group(string $localeID): Response
    {
        $gotoLocale = null;
        try {
            $token = $this->app->make('token');
            if (!$token->validate('comtra_join_translation_group' . $localeID)) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $locale = $localeID !== '' ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $accessService = $this->app->make(Access::class);
            $access = $accessService->getLocaleAccess($locale);
            if ($access !== Access::NONE) {
                throw new UserMessageException(t('Invalid user rights'));
            }
            $accessService->setLocaleAccess($locale, Access::ASPRIRING);

            return $this->redirectWithMessage(t('Your request to join the %s translation group has been submitted. Thank you!', $locale->getDisplayName()), false, 'details', $locale->getID());
        } catch (UserMessageException $x) {
            if ($gotoLocale === null) {
                return $this->redirectWithMessage($x->getMessage(), true, '');
            }

            return $this->redirectWithMessage($x->getMessage(), true, 'details', $gotoLocale->getID());
        }
    }

    public function action_leave_translation_group(string $localeID): Response
    {
        try {
            $token = $this->app->make('token');
            if (!$token->validate('comtra_leave_translation_group' . $localeID)) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $locale = $localeID !== '' ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $accessService = $this->app->make(Access::class);
            $access = $accessService->getLocaleAccess($locale);
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
            $accessService->setLocaleAccess($locale, Access::NONE);

            return $this->redirectWithMessage($message, false, '');
        } catch (UserMessageException $x) {
            return $this->redirectWithMessage($x->getMessage(), true, '');
        }
    }

    public function action_answer_join_request(string $localeID, string|int $userID, string $approve): Response
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
            $accessService = $this->app->make(Access::class);
            $access = $accessService->getLocaleAccess($locale);
            switch ($access) {
                case Access::ADMIN:
                case Access::GLOBAL_ADMIN:
                    break;
                default:
                    throw new UserMessageException(t('Invalid user rights'));
            }
            $user = is_numeric($userID) ? UserObject::getByUserID((int) $userID) : null;
            if ($user) {
                if ($accessService->getLocaleAccess($locale, $user) !== Access::ASPRIRING) {
                    $user = null;
                }
            }
            if (!$user) {
                throw new UserMessageException(t('Invalid user'));
            }
            $me = $this->app->make(UserObject::class);
            if ($approve) {
                $accessService->setLocaleAccess($locale, Access::TRANSLATE, $user);
                $this->app->make(NotificationRepository::class)->newTranslatorApproved($locale, (int) $user->getUserID(), (int) $me->getUserID(), false);
                $message = t('User %s has been accepted as translator', $user->getUserName());
            } else {
                $accessService->setLocaleAccess($locale, Access::NONE, $user);
                $this->app->make(NotificationRepository::class)->newTranslatorRejected($locale, (int) $user->getUserID(), (int) $me->getUserID());
                $message = t('The request by %s has been refused', $user->getUserName());
            }

            return $this->redirectWithMessage($message, false, 'details', $locale->getID());
        } catch (UserMessageException $x) {
            if ($gotoLocale === null) {
                return $this->redirectWithMessage($x->getMessage(), true, '');
            }

            return $this->redirectWithMessage($x->getMessage(), true, 'details', $gotoLocale->getID());
        }
    }

    public function action_change_access(string $localeID, string|int $userID, string $newAccess): Response
    {
        $gotoLocale = null;
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_change_access' . $localeID . '#' . $userID . ':' . $newAccess)) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $locale = $localeID === '' ? null : $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $user = is_numeric($userID) ? UserObject::getByUserID((int) $userID) : null;
            if (!$user) {
                throw new UserMessageException(t('Invalid user'));
            }
            $accessService = $this->app->make(Access::class);
            $myAccess = $accessService->getLocaleAccess($locale);
            $oldAccess = $accessService->getLocaleAccess($locale, $user);
            $newAccess = is_numeric($newAccess) ? (int) $newAccess : null;
            if (
                $newAccess === null
                || $myAccess < Access::ADMIN
                || $oldAccess > $myAccess || $newAccess > $myAccess
                || $oldAccess < Access::TRANSLATE || $oldAccess > Access::ADMIN
                || $newAccess < Access::NONE || $newAccess > Access::ADMIN
                || $newAccess === $oldAccess
                ) {
                throw new UserMessageException(t('Invalid user rights'));
            }
            $accessService->setLocaleAccess($locale, $newAccess, $user);

            return $this->redirectWithMessage(t('The role of %s has been updated', $user->getUserName()), false, 'details', $locale->getID());
        } catch (UserMessageException $x) {
            if ($gotoLocale === null) {
                return $this->redirectWithMessage($x->getMessage(), true, '');
            }

            return $this->redirectWithMessage($x->getMessage(), true, 'details', $gotoLocale->getID());
        }
    }

    public function action_delete_translation_group(string $localeID): Response
    {
        $gotoLocale = null;
        try {
            $locale = $localeID === '' ? null : $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", $localeID));
            }
            $gotoLocale = $locale;
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_delete_translation_group' . $localeID)) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $accessService = $this->app->make(Access::class);
            $myAccess = $accessService->getLocaleAccess($locale);
            if ($myAccess < Access::GLOBAL_ADMIN) {
                throw new UserMessageException(t('Invalid user rights'));
            }
            $em = $this->app->make(EntityManager::class);
            $em->remove($locale);
            $em->flush($locale);
            $this->app->make(GroupService::class)->deleteLocaleGroups($locale->getID());

            return $this->redirectWithMessage(t('The language team for %s has been deleted', $locale->getDisplayName()), false, '');
        } catch (UserMessageException $x) {
            if ($gotoLocale === null) {
                return $this->redirectWithMessage($x->getMessage(), true, '');
            }

            return $this->redirectWithMessage($x->getMessage(), true, 'details', $gotoLocale->getID());
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Controller\BlockController::normalizeArgs()
     */
    protected function normalizeArgs(array $args)
    {
        $error = $this->app->make('helper/validation/error');
        [$askNewTeamHandle, $askNewTeamValue] = $this->app->make(DestinationPicker::class)->decode('askNewTeam', $this->getDestinationPickerConfiguration(), $error, t('Link target'), $args);
        $normalized = [
            'askNewTeamCID' => $askNewTeamHandle === 'page' ? $askNewTeamValue : null,
            'askNewTeamLink' => $askNewTeamHandle === 'external_url' ? $askNewTeamValue : '',
        ];

        return $error->has() ? $error : $normalized;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Controller\BlockController::isControllerTaskInstanceSpecific()
     */
    protected function isControllerTaskInstanceSpecific(string $method): bool
    {
        $method = strtolower($method);
        switch ($method) {
            case 'action_details':
                return false;
            default:
                return true;
        }
    }

    private function showTeamListStep(): void
    {
        $me = $this->app->make(UserObject::class);
        if (!$me->isRegistered()) {
            $me = null;
        }
        $groupService = $this->app->make(GroupService::class);
        $repo = $this->app->make(LocaleRepository::class);
        $accessService = $this->app->make(Access::class);
        $approved = [];
        foreach ($repo->getApprovedLocales() as $locale) {
            $approved[] = [
                'id' => $locale->getID(),
                'name' => $locale->getDisplayName(),
                'access' => $me ? $accessService->getLocaleAccess($locale, $me) : null,
                'admins' => (int) $groupService->getAdministrators($locale)->getGroupMembersNum(),
                'translators' => (int) $groupService->getTranslators($locale)->getGroupMembersNum(),
                'aspiring' => (int) $groupService->getAspiringTranslators($locale)->getGroupMembersNum(),
            ];
        }
        $requested = [];
        $iAmGlobalAdmin = $me ? ($me->isSuperUser() || $me->inGroup($groupService->getGlobalAdministrators())) : false;
        foreach ($repo->findBy(['isApproved' => false]) as $locale) {
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
        $this->set('token', $this->app->make('token'));
        $this->set('dh', $this->app->make('date'));
        $this->set('userService', $this->getUserService());
        $this->set('approved', $approved);
        $this->set('requested', $requested);
        $this->set('askNewTeamURL', $this->getAskNewTeamURL());
        $this->render('view.teamlist');
    }

    private function showTeamDetailsStep(LocaleEntity $locale): void
    {
        $this->app->make('helper/seo')->addTitleSegment($locale->getDisplayName());
        $groupService = $this->app->make(GroupService::class);
        $globalAdmins = $this->getGroupMembers($groupService->getGlobalAdministrators());
        $admins = $this->getGroupMembers($groupService->getAdministrators($locale));
        $translators = $this->getGroupMembers($groupService->getTranslators($locale));
        $aspiring = $this->getGroupMembers($groupService->getAspiringTranslators($locale));
        [$translationsCount, $otherTranslators] = $this->getTranslationCount($locale, array_merge($admins, $translators, $aspiring));
        $admins = $this->addGlobalAdminsToAdmins($globalAdmins, $otherTranslators, $admins);
        $globalAdmins = $this->applyTranslationCount([], $globalAdmins);
        $admins = $this->applyTranslationCount($translationsCount, $admins);
        $translators = $this->applyTranslationCount($translationsCount, $translators);
        $aspiring = $this->applyTranslationCount([], $aspiring);
        $globalAdmins = $this->sortMembers($globalAdmins);
        $admins = $this->sortMembers($admins);
        $translators = $this->sortMembers($translators);
        $aspiring = $this->sortMembers($aspiring);
        $this->set('token', $this->app->make('token'));
        $this->set('urlResolver', $this->app->make(ResolverManagerInterface::class));
        $this->set('dh', $this->app->make('helper/date'));
        $this->set('userService', $this->getUserService());
        $this->set('locale', $locale);
        $this->set('access', $this->app->make(Access::class)->getLocaleAccess($locale));
        $this->set('globalAdmins', $globalAdmins);
        $this->set('admins', $admins);
        $this->set('translators', $translators);
        $this->set('aspiring', $aspiring);
        $this->render('view.team');
    }

    private function getGroupMembers(Group $group): array
    {
        $result = [];
        foreach ($group->getGroupMembers() as $m) {
            $result[] = ['ui' => $m, 'since' => $group->getGroupDateTimeEntered($m)];
        }

        return $result;
    }

    private function sortMembers(array $members): array
    {
        $comparer = new Comparer();
        usort($members, static function ($a, $b) use ($comparer): int {
            $delta = $b['totalTranslations'] - $a['totalTranslations'];
            if ($delta === 0) {
                $delta = $b['approvedTranslations'] - $a['approvedTranslations'];
                if ($delta === 0) {
                    $delta = $comparer->compare($a['ui']->getUserName(), $b['ui']->getUserName());
                }
            }

            return $delta;
        });

        return $members;
    }

    private function getTranslationCount(LocaleEntity $locale, array $definedUsers): array
    {
        $db = $this->app->make(Connection::class);
        $translationsCount = [];
        $rs = $db->executeQuery(
            <<<'EOT'
SELECT
    CommunityTranslationTranslations.createdBy AS uID,
    count(*) AS numTranslations,
    CommunityTranslationTranslations.currentSince IS NULL AS notCurrent
FROM
    CommunityTranslationTranslations
WHERE
    CommunityTranslationTranslations.locale = ?
    AND CommunityTranslationTranslations.createdBy <> ?
GROUP BY
    CommunityTranslationTranslations.createdBy,
    CommunityTranslationTranslations.currentSince IS NULL
EOT
            ,
            [$locale->getID(), USER_SUPER_ID]
        );
        while (($row = $rs->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $uID = $row['uID'];
            if (!isset($translationsCount[$uID])) {
                $translationsCount[$uID] = [
                    'notCurrent' => 0,
                    'current' => 0,
                ];
            }
            if ($row['notCurrent']) {
                $translationsCount[$uID]['notCurrent'] = (int) $row['numTranslations'];
            } else {
                $translationsCount[$uID]['current'] = (int) $row['numTranslations'];
            }
        }
        $otherTranslators = [];
        foreach (array_keys($translationsCount) as $uID) {
            $already = false;
            foreach ($definedUsers as $definedUser) {
                if ($definedUser['ui']->getUserID() == $uID) {
                    $already = true;
                    break;
                }
            }
            if ($already === false) {
                $otherTranslators[] = (int) $uID;
            }
        }

        return [$translationsCount, $otherTranslators];
    }

    private function addGlobalAdminsToAdmins(array $globalAdmins, array $otherTranslators, array $admins): array
    {
        foreach ($otherTranslators as $uID) {
            foreach ($globalAdmins as $globalAdmin) {
                if ($globalAdmin['ui']->getUserID() == $uID) {
                    $globalAdmin['actuallyGlobalAdmin'] = true;
                    $admins[] = $globalAdmin;
                    break;
                }
            }
        }

        return $admins;
    }

    private function applyTranslationCount(array $translationsCount, array $members): array
    {
        foreach (array_keys($members) as $index) {
            $members[$index]['approvedTranslations'] = 0;
            $uID = $members[$index]['ui']->getUserID();
            if (isset($translationsCount[$uID])) {
                $members[$index]['totalTranslations'] = $translationsCount[$uID]['notCurrent'] + $translationsCount[$uID]['current'];
                $members[$index]['approvedTranslations'] = $translationsCount[$uID]['current'];
            } else {
                $members[$index]['approvedTranslations'] = $members[$index]['totalTranslations'] = 0;
            }
        }

        return $members;
    }

    private function getDestinationPickerConfiguration(): array
    {
        return [
            'none',
            'page',
            'external_url' => ['maxlength' => 255],
        ];
    }

    private function getAskNewTeamURL(): string
    {
        if ($this->askNewTeamCID) {
            $askNewTeamPage = Page::getByID((int) $this->askNewTeamCID);
            if ($askNewTeamPage && !$askNewTeamPage->isError()) {
                return (string) $this->app->make(ResolverManagerInterface::class)->resolve([$askNewTeamPage]);
            }

            return '';
        }
        if ((string) $this->askNewTeamLink !== '') {
            $sec = $this->app->make('helper/security');

            return $sec->sanitizeURL($this->askNewTeamLink);
        }

        return '';
    }
}
