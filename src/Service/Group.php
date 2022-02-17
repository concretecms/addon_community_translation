<?php

declare(strict_types=1);

namespace CommunityTranslation\Service;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\Application\Application;
use Concrete\Core\Entity\Package as PackageEntity;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Package\PackageService;
use Concrete\Core\User\Group\Command\AddGroupCommand;
use Concrete\Core\User\Group\Command\DeleteGroupCommand;
use Concrete\Core\User\Group\Group as GroupObject;
use Concrete\Core\User\Group\GroupRepository;

defined('C5_EXECUTE') or die('Access Denied.');

class Group
{
    /**
     * Group name of the global administrators.
     *
     * @var string
     */
    private const GROUPNAME_GLOBAL_ADMINISTRATORS = 'Global Locale Administrators';

    /**
     * Name of the parent group of each locale administrator groups.
     *
     * @var string
     */
    private const GROUPNAME_LOCALE_ADMINISTRATORS = 'Locale Administrators';

    /**
     * Name of the parent group of each locale translators groups.
     *
     * @var string
     */
    private const GROUPNAME_TRANSLATORS = 'Translators';

    /**
     * Name of the parent group of every locale translators.
     *
     * @var string
     */
    private const GROUPNAME_ASPIRING_TRANSLATORS = 'Aspiring Translators';

    private GroupRepository $groupRepository;

    private LocaleRepository $localeRepository;

    private Application $app;

    private ?PackageEntity $pkg = null;

    /**
     * The global administrators group.
     */
    private ?GroupObject $globalAdministrators = null;

    /**
     * @var [[\Concrete\Core\User\Group\Group]]
     */
    private array $localeGroups = [];

    public function __construct(GroupRepository $groupRepository, LocaleRepository $localeRepository, Application $app)
    {
        $this->groupRepository = $groupRepository;
        $this->localeRepository = $localeRepository;
        $this->app = $app;
    }

    /**
     * Get the global administrators group.
     */
    public function getGlobalAdministrators(): GroupObject
    {
        if ($this->globalAdministrators === null) {
            $this->globalAdministrators = $this->getGroup(self::GROUPNAME_GLOBAL_ADMINISTRATORS);
        }

        return $this->globalAdministrators;
    }

    /**
     * Get the group of the administrators of a specific locale.
     *
     * @param \CommunityTranslation\Entity\Locale|string $locale
     */
    public function getAdministrators($locale): GroupObject
    {
        return $this->getLocaleGroup(self::GROUPNAME_LOCALE_ADMINISTRATORS, $locale);
    }

    /**
     * Get the group of the translators of a specific locale.
     *
     * @param \CommunityTranslation\Entity\Locale|string $locale
     */
    public function getTranslators($locale): GroupObject
    {
        return $this->getLocaleGroup(self::GROUPNAME_TRANSLATORS, $locale);
    }

    /**
     * Get the group of the people that want to translate a specific locale.
     *
     * @param \CommunityTranslation\Entity\Locale|string $locale
     */
    public function getAspiringTranslators($locale): GroupObject
    {
        return $this->getLocaleGroup(self::GROUPNAME_ASPIRING_TRANSLATORS, $locale);
    }

    /**
     * Check if a group is an aspiring translators group. If so returns the associated locale entity.
     */
    public function decodeAspiringTranslatorsGroup(GroupObject $group): ?LocaleEntity
    {
        $match = null;
        if (preg_match('/^\/' . preg_quote(self::GROUPNAME_ASPIRING_TRANSLATORS, '/') . '\/(.+)$/', $group->getGroupPath(), $match)) {
            return $this->localeRepository->findApproved($match[1]);
        }

        return null;
    }

    /**
     * Delete the user groups associated to a locale ID.
     */
    public function deleteLocaleGroups(string $localeID): void
    {
        foreach ([
            self::GROUPNAME_LOCALE_ADMINISTRATORS,
            self::GROUPNAME_TRANSLATORS,
            self::GROUPNAME_ASPIRING_TRANSLATORS,
        ] as $parentGroupName) {
            $path = "/{$parentGroupName}/{$localeID}";
            $group = $this->groupRepository->getGroupByPath($path);
            if ($group !== null) {
                $this->app->executeCommand(new DeleteGroupCommand((int) $group->getGroupID()));
            }
        }
    }

    private function getPackage(): PackageEntity
    {
        if ($this->pkg === null) {
            $this->pkg = $this->app->make(PackageService::class)->getByHandle('community_translation');
        }

        return $this->pkg;
    }

    /**
     * Get a user group (create it if it does not exist).
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function getGroup(string $name, ?GroupObject $parentGroup = null): GroupObject
    {
        $name = trim($name, '/');
        if ($parentGroup === null) {
            $path = '/' . $name;
        } else {
            $path = "/{$parentGroup->getGroupName()}/{$name}";
        }
        $group = $this->groupRepository->getGroupByPath($path);
        if (!$group) {
            $command = new AddGroupCommand();
            $command
                ->setName($name)
                ->setPackageID($this->getPackage()->getPackageID())
            ;
            if ($parentGroup !== null) {
                $command->setParentGroupID($parentGroup->getGroupID());
            }
            $group = $this->app->executeCommand($command);
            if (!$group) {
                if ($parentGroup !== null) {
                    throw new UserMessageException(t("Failed to create a user group with name '%1\$s' as a child of '%2\$s'", $name, $parentGroup->getGroupName()));
                }
                throw new UserMessageException(t("Failed to create a user group with name '%s'", $name));
            }
        }

        return $group;
    }

    /**
     * Get a locale group given its parent group name.
     *
     * @param LocaleEntity|string $locale
     */
    private function getLocaleGroup(string $parentName, $locale)
    {
        if (!($locale instanceof LocaleEntity)) {
            $l = is_string($locale) && $locale !== '' ? $this->localeRepository->findApproved($locale) : null;
            if ($l === null) {
                throw new UserMessageException(t("The locale identifier '%s' is not valid", is_string($locale) ? $locale : gettype($locale)));
            }
            $locale = $l;
        }
        $localeID = $locale->getID();
        if (!isset($this->localeGroups[$parentName])) {
            $this->localeGroups[$parentName] = [];
        }
        if (!isset($this->localeGroups[$parentName][$localeID])) {
            $this->localeGroups[$parentName][$localeID] = $this->getGroup($localeID, $this->getGroup($parentName));
        }

        return $this->localeGroups[$parentName][$localeID];
    }
}
