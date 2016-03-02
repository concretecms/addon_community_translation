<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

use Concrete\Core\Application\Application;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Group;
use Concrete\Package\CommunityTranslation\Src\UserException;

class Groups implements \Concrete\Core\Application\ApplicationAwareInterface
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
     * Set the application object.
     *
     * @param Application $application
     */
    public function setApplication(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a user group (create it if it does not exist).
     *
     * @param string $name The group name.
     * @param string|Group|null $parent The parent group.
     *
     * @return Group
     */
    protected function getGroup($name, $parent = null)
    {
        static $pkg;
        if (!isset($pkgID)) {
            $pkg = \Package::getByHandle('community_translation');
        }
        if ($parent === '') {
            $parent = null;
        }
        if ($parent === null || $parent === '' || $parent === false) {
            $path = '/'.$name;
        } else {
            if (!$parent instanceof Group) {
                $parent = $this->getGroup($parent);
            }
            $path = '/'.$parent->getGroupName().'/'.$name;
        }
        $result = Group::getByPath($path);
        if (!$result) {
            $result = Group::add($name, '', $parent, $pkg);
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

    private $localeGroups = array();
    /**
     * Get a locale group given its parent group name.
     *
     * @param string $parentName
     * @param Locale|string $locale
     */
    protected function getLocaleGroup($parentName, $locale)
    {
        if (!$locale instanceof Locale) {
            $l = $this->app->make('community_translation/locale')->find($locale);
            if ($l === null) {
                throw new UserException(t("The locale identifier '%s' is not valid", $locale));
            }
            $locale = $l;
        }
        $localeID = $locale->getID();
        if (!isset($this->localeGroups[$parentName])) {
            $this->localeGroups[$parentName] = array();
        }
        if (!isset($this->localeGroups[$parentName][$localeID])) {
            $this->localeGroups[$parentName][$localeID] = $this->getGroup($localeID, $parentName);
        }

        return $this->localeGroups[$parentName][$localeID];
    }

    /**
     * The global administrators group.
     *
     * @var Group|null
     */
    private $globalAdministrators = null;
    /**
     * Get the global administrators group.
     *
     * @return string
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
     * @param Locale|string $locale
     *
     * @return Group
     */
    public function getAdministrators($locale)
    {
        return $this->getLocaleGroup(self::GROUPNAME_LOCALE_ADMINISTRATORS, $locale);
    }

    /**
     * Get the group of the translators of a specific locale.
     *
     * @param Locale|string $locale
     *
     * @return Group
     */
    public function getTranslators($locale)
    {
        return $this->getLocaleGroup(self::GROUPNAME_TRANSLATORS, $locale);
    }

    /**
     * Get the group of the people that want to translate a specific locale.
     *
     * @param Locale|string $locale
     *
     * @return Group
     */
    public function getAspiringTranslators($locale)
    {
        return $this->getLocaleGroup(self::GROUPNAME_ASPIRING_TRANSLATORS, $locale);
    }

    /**
     * Check if a group is an aspiring translators group. If so returns the associated Locale.
     *
     * @param \Concrete\Core\User\Group\Group $group
     *
     * @return Locale|null
     */
    public function decodeAspiringTranslatorsGroup(\Concrete\Core\User\Group\Group $group)
    {
        $result = null;
        if (preg_match('/^\/'.preg_quote(self::GROUPNAME_ASPIRING_TRANSLATORS, '/').'\/(.+)$/', $group->getGroupPath(), $match)) {
            $result = $this->app->make('community_translation/locale')->find($match[1]);
        }

        return $result;
    }
}
