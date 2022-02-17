<?php

declare(strict_types=1);

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Notification;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Service\Access as AccessService;
use CommunityTranslation\Service\Group as GroupService;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\User\Group\Group as CoreGroup;
use Concrete\Core\User\User as CoreUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class AcceptPendingJoinRequestsCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
ct:accept-requests
    {age : The age (in days) of the requests to be accepted (0 means all the requests) }
    {--l|locale=* : One or more locale ID to limit the action to }
EOT
    ;

    private EntityManager $em;

    private GroupService $groupService;

    private AccessService $accessService;

    private NotificationRepository $notificationRepository;

    /**
     * @var \CommunityTranslation\Entity\Locale[]
     */
    private array $locales;

    private ?DateTimeImmutable $dateLimit;

    /**
     * @return int
     */
    public function handle(EntityManager $em, GroupService $groupService, AccessService $accessService): int
    {
        $someErrors = false;
        $totalAcceptedUsers = 0;
        $mutexReleaser = null;
        $this->createLogger();
        try {
            $mutexReleaser = $this->acquireMutex();
            $this->em = $em;
            $this->groupService = $groupService;
            $this->accessService = $accessService;
            $this->notificationRepository = $em->getRepository(Notification::class);
            $this->readOptions();
            $this->logger->info(($this->dateLimit === null) ? 'Accepting all the pending join requests' : sprintf('Accepting the pending join requests made before %s', $this->dateLimit->format('r')));
            foreach ($this->locales as $locale) {
                try {
                    $totalAcceptedUsers += $this->processLocale($locale);
                } catch (Throwable $x) {
                    $this->logger->error($this->formatThrowable($x));
                    $someErrors = true;
                }
            }
            $this->logger->info(sprintf('All done. %d locales processed, %d requests accepted.', count($this->locales), $totalAcceptedUsers));
        } catch (Throwable $x) {
            $this->logger->error($this->formatThrowable($x));
            $someErrors = true;
        } finally {
            if ($mutexReleaser !== null) {
                try {
                    $mutexReleaser();
                } catch (Throwable $x) {
                }
            }
        }
        if ($someErrors) {
            return $totalAcceptedUsers === 0 ? 3 : 2;
        }

        return $totalAcceptedUsers === 0 ? 0 : 1;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::configureUsingFluentDefinition()
     */
    protected function configureUsingFluentDefinition()
    {
        parent::configureUsingFluentDefinition();
        $this
            ->setDescription('Accept pending Translation Team join requests')
            ->setHelp(
                <<<'EOT'
When someone wants to help translating a language, she/he asks to join a Translation Team.

Team coordinators then should answer to those join requests, accepting or denying them.

BTW it may happen that team coordinators don't answer them.

This command can be scheduled to automatically accept join requests that are older than a specified amount of days.

Returns codes:
  0 no applicable join request has been found
  1 some join request has been accpted
  2 errors occurred but some join request have been accepted
  3 errors occurred and no join has been request accepted
EOT
            )
        ;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function readOptions(): void
    {
        $approvedLocales = $this->em->getRepository(LocaleEntity::class)->getApprovedLocales();
        $filteredLocales = [];
        foreach ($this->input->getOption('locale') as $localeID) {
            $locale = null;
            foreach ($approvedLocales as $l) {
                if (strcasecmp(str_replace('-', '_', $localeID), $l->getID()) === 0) {
                    $locale = $l;
                    break;
                }
            }
            if ($locale === null) {
                throw new UserMessageException("Unable to find a locale with ID {$localeID}");
            }
            if (!in_array($locale, $filteredLocales, true)) {
                $filteredLocales[] = $locale;
            }
        }
        $this->locales = $filteredLocales === [] ? $approvedLocales : $filteredLocales;
        $age = $this->input->getArgument('age');
        if (!preg_match('/^\d+$/', $age)) {
            throw new UserMessageException('The age argument must be a (not negative) integer');
        }
        $age = (int) $age;
        if ($age === 0) {
            $this->dateLimit = null;
        } else {
            $this->dateLimit = new DateTimeImmutable("-{$age} days");
        }
    }

    /**
     * @return int The number of accepted join requests
     */
    private function processLocale(LocaleEntity $locale): int
    {
        $this->logger->info(sprintf('Processing %s (%s)', $locale->getName(), $locale->getID()));
        $aspiringGroup = $this->groupService->getAspiringTranslators($locale);
        $aspiringGroupMemberIDs = $aspiringGroup->getGroupMemberIDs();
        $numberOfAspiringMembers = count($aspiringGroupMemberIDs);
        if ($numberOfAspiringMembers === 0) {
            $this->logger->info('  - No pending requests found.');

            return 0;
        }
        $result = 0;
        foreach ($aspiringGroupMemberIDs as $memberID) {
            if ($this->processAspiring($locale, $aspiringGroup, (int) $memberID)) {
                $result++;
            }
        }
        $this->logger->info(sprintf('  - %d out of %d requests accepted.', $result, $numberOfAspiringMembers));

        return $result;
    }

    /**
     * @return bool Returns true if the user has been promoted to the translators group
     */
    private function processAspiring(LocaleEntity $locale, CoreGroup $aspiringGroup, int $memberID): bool
    {
        if ($this->dateLimit !== null) {
            $enteredOnString = $aspiringGroup->getGroupDateTimeEntered($memberID);
            if ($enteredOnString !== null) {
                $enteredOnDateTime = new DateTimeImmutable($enteredOnString);
                if ($enteredOnDateTime >= $this->dateLimit) {
                    $userEntity = $this->em->find(UserEntity::class, $memberID);
                    $this->logger->debug(sprintf('  - Request from %s (ID: %d) still too recent', $userEntity->getUserName(), $userEntity->getUserID()));

                    return false;
                }
            }
        }
        $accessLevel = $this->accessService->getLocaleAccess($locale, $memberID);
        if ($accessLevel > AccessService::ASPRIRING) {
            // The user belongs to the aspiring group, but is also a member of a user group with higher privileges.
            // In this case, let's simply remove it from the aspiring group
            $user = CoreUser::getByUserID($memberID);
            if ($user) {
                $user->exitGroup($aspiringGroup);
            }

            return false;
        }
        $userEntity = $this->em->find(UserEntity::class, $memberID);
        $this->accessService->setLocaleAccess($locale, AccessService::TRANSLATE, $memberID);
        $this->notificationRepository->newTranslatorApproved($locale, $memberID, USER_SUPER_ID, true);
        $this->logger->info(sprintf('  - User accepted: %s (ID: %d)', $userEntity->getUserName(), $userEntity->getUserID()));

        return true;
    }
}
