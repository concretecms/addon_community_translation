<?php
namespace CommunityTranslation\Service;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\UserException;
use Concrete\Core\Application\Application;
use Concrete\Core\Package\PackageService;
use Concrete\Core\User\Group\Group;

class Groups
{
    /**
     * Group name of the global administrators.
     *
     * @var string
     */
    const GROUPNAME_GLOBAL_ADMINISTRATORS = 'Global Locale Administrators';

    /**
     * Name of the parent group of each locale administrator groups.
     *
     * @var string
     */
    const GROUPNAME_LOCALE_ADMINISTRATORS = 'Locale Administrators';

    /**
     * Name of the parent group of each locale translators groups.
     *
     * @var string
     */
    const GROUPNAME_TRANSLATORS = 'Translators';

    /**
     * Name of the parent group of every locale translators.
     *
     * @var string
     */
    const GROUPNAME_ASPIRING_TRANSLATORS = 'Aspiring Translators';

    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @var \Concrete\Core\Entity\Package|null
     */
    protected $pkg = null;

    /**
     * @return \Concrete\Core\Entity\Package
     */
    protected function getPackage()
    {
        if ($this->pkg === null) {
            $this->pkg = $this->app->make(PackageService::class)->getByHandle('community_translation');
        }

        return $this->pkg;
    }

    /**
     * Get a user group (create it if it does not exist).
     *
     * @param string $name the group name
     * @param string|Group|null $parent the parent group
     *
     * @return \Group
     */
    protected function getGroup($name, $parent = null)
    {
        $name = trim($name, '/');
        if ($parent === null || $parent === false || $parent === '') {
            $parent = null;
            $path = '/' . $name;
        } else {
            if (!$parent instanceof Group) {
                $parent = $this->getGroup($parent);
            }
            $path = '/' . $parent->getGroupName() . '/' . $name;
        }
        $result = \Group::getByPath($path);
        if (!$result) {
            $result = \Group::add($name, '', $parent, $this->getPackage());
            if (!$result) {
                if ($parent) {
                    throw new UserException(t("Failed to create a user group with name '%1\$s' as a child of '%2\$s'", $name, $parent->getGroupName()));
                } else {
                    throw new UserException(t("Failed to create a user group with name '%s'", $name));
                }
            }
        }

        return $result;
    }

    /**
     * @var [[\Group]]
     */
    private $localeGroups = [];

    /**
     * Get a locale group given its parent group name.
     *
     * @param string $parentName
     * @param LocaleEntity|string $locale
     */
    protected function getLocaleGroup($parentName, $locale)
    {
        if (!($locale instanceof LocaleEntity)) {
            $l = $this->app->make(LocaleRepository::class)->findApproved($locale);
            if ($l === null) {
                throw new UserException(t("The locale identifier '%s' is not valid", $locale));
            }
            $locale = $l;
        }
        $localeID = $locale->getID();
        if (!isset($this->localeGroups[$parentName])) {
            $this->localeGroups[$parentName] = [];
        }
        if (!isset($this->localeGroups[$parentName][$localeID])) {
            $this->localeGroups[$parentName][$localeID] = $this->getGroup($localeID, $parentName);
        }

        return $this->localeGroups[$parentName][$localeID];
    }

    /**
     * The global administrators group.
     *
     * @var \Group|null
     */
    private $globalAdministrators = null;

    /**
     * Get the global administrators group.
     *
     * @return \Group
     */
    public function getGlobalAdministrators()
    {
        if ($this->globalAdministrators === null) {
            $this->globalAdministrators = $this->getGroup(self::GROUPNAME_GLOBAL_ADMINISTRATORS);
        }

        return $this->globalAdministrators;
    }

    /**
     * Get the group of the administrators of a specific locale.
     *
     * @param LocaleEntity|string $locale
     *
     * @return \Group
     */
    public function getAdministrators($locale)
    {
        return $this->getLocaleGroup(self::GROUPNAME_LOCALE_ADMINISTRATORS, $locale);
    }

    /**
     * Get the group of the translators of a specific locale.
     *
     * @param LocaleEntity|string $locale
     *
     * @return \Group
     */
    public function getTranslators($locale)
    {
        return $this->getLocaleGroup(self::GROUPNAME_TRANSLATORS, $locale);
    }

    /**
     * Get the group of the people that want to translate a specific locale.
     *
     * @param LocaleEntity|string $locale
     *
     * @return \Group
     */
    public function getAspiringTranslators($locale)
    {
        return $this->getLocaleGroup(self::GROUPNAME_ASPIRING_TRANSLATORS, $locale);
    }

    /**
     * Check if a group is an aspiring translators group. If so returns the associated locale entity.
     *
     * @param Group $group
     *
     * @return LocaleEntity|null
     */
    public function decodeAspiringTranslatorsGroup(Group $group)
    {
        $result = null;
        if (preg_match('/^\/' . preg_quote(self::GROUPNAME_ASPIRING_TRANSLATORS, '/') . '\/(.+)$/', $group->getGroupPath(), $match)) {
            $result = $this->app->make(LocaleRepository::class)->findApproved($match[1]);
        }

        return $result;
    }
}
