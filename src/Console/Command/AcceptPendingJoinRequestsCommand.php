<?php
namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Service\Access as AccessHelper;
use CommunityTranslation\Service\Groups as GroupsHelper;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\User\Group\Group as CoreGroup;
use Concrete\Core\User\User as CoreUser;
use DateTime;
use Doctrine\ORM\EntityManager;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class AcceptPendingJoinRequestsCommand extends Command
{
    const RETURN_CODE_ON_FAILURE = 3;

    protected function configure()
    {
        $errExitCode = static::RETURN_CODE_ON_FAILURE;
        $this
            ->setName('ct:accept-requests')
            ->setDescription('Accept pending Translation Team join requests')
            ->addOption('locale', 'l', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'One or more locale ID to limit the action to')
            ->addArgument('age', InputArgument::REQUIRED, 'The age (in days) of the requests to be accepted (0 means all the requests)')
            ->setHelp(<<<EOT
When someone wants to help translating a language, she/he ask to join a Translation Team.

Team coordinators then should answer to those join requests, accepting or denying them.

BTW it may happen that team coordinators don't answer them.

This command can be scheduled to automatically accept join requests that are older than a specified amount of days.

Returns codes:
  0 no applicable join request has been found
  1 some join request has been accpted
  2 errors occurred but some join request have been accepted
  $errExitCode errors occurred and no join has been request accepted
EOT
            )
        ;
    }

    /**
     * @var EntityManager|null
     */
    private $em;

    /**
     * @var GroupsHelper|null
     */
    private $groupsHelper;

    /**
     * @var AccessHelper|null
     */
    private $accessHelper;

    /**
     * @var NotificationRepository|null
     */
    private $notificationRepository;

    /**
     * @var LocaleEntity[]|null
     */
    private $approvedLocales;

    /**
     * @var LocaleEntity[]|null
     */
    private $localesToProcess;

    /**
     * @var DateTime|null
     */
    private $dateLimit;

    protected function executeWithLogger()
    {
        $this->initializeState();
        $this->readOptions();
        $this->logger->info(($this->dateLimit === null) ? 'Accepting all the pending join requests' : sprintf('Accepting the pending join requests made before %s', $this->dateLimit->format('r')));
        $someErrors = false;
        $totalAcceptedUsers = 0;
        foreach ($this->localesToProcess as $locale) {
            try {
                $totalAcceptedUsers += $this->processLocale($locale);
            } catch (Exception $x) {
                $this->logger->error($this->formatThrowable($x));
                $someErrors = true;
            } catch (Throwable $x) {
                $this->logger->error($this->formatThrowable($x));
                $someErrors = true;
            }
        }

        $this->logger->info(sprintf('All done. %d locales processed, %d requests accepted.', count($this->localesToProcess), $totalAcceptedUsers));
        if ($someErrors) {
            $rc = ($totalAcceptedUsers === 0) ? static::RETURN_CODE_ON_FAILURE : 2;
        } else {
            $rc = ($totalAcceptedUsers === 0) ? 0 : 1;
        }

        return $rc;
    }

    private function initializeState()
    {
        $this->em = $this->app->make(EntityManager::class);
        $this->groupsHelper = $this->app->make(GroupsHelper::class);
        $this->accessHelper = $this->app->make(AccessHelper::class);
        $this->notificationRepository = $this->app->make(NotificationRepository::class);
        $this->approvedLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
    }

    /**
     * @throws Exception
     */
    private function readOptions()
    {
        $localeFilter = [];
        foreach ($this->input->getOption('locale') as $localeID) {
            $locale = null;
            foreach ($this->approvedLocales as $l) {
                if (strcasecmp(str_replace('-', '_', $localeID), $l->getID()) === 0) {
                    $locale = $l;
                    break;
                }
            }
            if ($locale === null) {
                throw new Exception('Unable to find a locale with ID ' . $localeID);
            }
            if (!in_array($locale, $localeFilter, true)) {
                $localeFilter[] = $locale;
            }
        }
        $this->localesToProcess = empty($localeFilter) ? $this->approvedLocales : $localeFilter;
        $age = $this->input->getArgument('age');
        if (!preg_match('/^\d+$/', $age)) {
            throw new Exception('The age argument must be a (not negative) integer');
        }
        $age = (int) $age;
        if ($age === 0) {
            $this->dateLimit = null;
        } else {
            $this->dateLimit = new DateTime("-$age days");
        }
    }

    /**
     * @param LocaleEntity $locale
     *
     * @return int The number of accepted join requests
     */
    private function processLocale(LocaleEntity $locale)
    {
        $result = 0;
        $this->logger->info(sprintf('Processing %s (%s)', $locale->getName(), $locale->getID()));
        $aspiringGroup = $this->groupsHelper->getAspiringTranslators($locale);
        $aspiringGroupMemberIDs = $aspiringGroup->getGroupMemberIDs();
        $numberOfAspiringMembers = count($aspiringGroupMemberIDs);
        if ($numberOfAspiringMembers === 0) {
            $this->logger->info('  - No pending requests found.');
        } else {
            foreach ($aspiringGroupMemberIDs as $memberID) {
                if ($this->processAspiring($locale, $aspiringGroup, $memberID)) {
                    ++$result;
                }
            }
            $this->logger->info(sprintf('  - %d out of %d requests accepted.', $result, $numberOfAspiringMembers));
        }

        return $result;
    }

    /**
     * @param LocaleEntity $locale
     * @param CoreGroup $aspiringGroup
     * @param int $memberID
     *
     * @return bool Returns true if the user has been promoted to the translators group
     */
    private function processAspiring(LocaleEntity $locale, CoreGroup $aspiringGroup, $memberID)
    {
        $result = false;
        if ($this->dateLimit === null) {
            $dateLimitReached = true;
        } else {
            $enteredOnString = $aspiringGroup->getGroupDateTimeEntered($memberID);
            if ($enteredOnString === null) {
                $dateLimitReached = true;
            } else {
                $enteredOnDateTime = new DateTime($enteredOnString);
                $dateLimitReached = ($enteredOnDateTime < $this->dateLimit) ? true : false;
            }
        }
        if ($dateLimitReached === true) {
            $accessLevel = $this->accessHelper->getLocaleAccess($locale, $memberID);
            if ($accessLevel > AccessHelper::ASPRIRING) {
                // The user belongs to the aspiring group, but is also a member of a user group with higher privileges.
                // In this case, let's simply remove it from the aspiring group
                $user = CoreUser::getByUserID($memberID);
                if ($user) {
                    $user->exitGroup($aspiringGroup);
                }
            } else {
                $userEntity = $this->em->find(UserEntity::class, $memberID);
                $this->accessHelper->setLocaleAccess($locale, AccessHelper::TRANSLATE, $memberID);
                $this->notificationRepository->newTranslatorApproved($locale, $memberID, USER_SUPER_ID, true);
                $this->logger->info(sprintf('  - User accepted: %s (ID: %d)', $userEntity->getUserName(), $userEntity->getUserID()));
                $result = true;
            }
        } else {
            $userEntity = $this->em->find(UserEntity::class, $memberID);
            $this->logger->debug(sprintf('  - Request from %s (ID: %d) still too recent', $userEntity->getUserName(), $userEntity->getUserID()));
        }

        return $result;
    }
}
