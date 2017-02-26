<?php
namespace Concrete\Package\CommunityTranslation\Block\DownloadTranslations;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Service\Access;
use Punic\Comparer;
use CommunityTranslation\UserException;

class Controller extends BlockController
{
    const ALLOWEDUSERS_EVERYBODY = 'everybody';
    const ALLOWEDUSERS_TRANSLATORS = 'translators';
    const ALLOWEDUSERS_LOCALEADMINS = 'locale_admins';
    const ALLOWEDUSERS_GLOBALADMINS = 'global_admins';

    const FORMAT_JED = 'Jed';
    const FORMAT_JSONDICTIONARY = 'JsonDictionary';
    const FORMAT_MO = 'Mo';
    const FORMAT_PHPARRAY = 'PhpArray';
    const FORMAT_PO = 'Po';

    public $helpers = [];

    protected $btTable = 'btCTDownloadTranslations';

    protected $btInterfaceWidth = 600;
    protected $btInterfaceHeight = 465;

    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = true;
    protected $btCacheBlockOutputOnPost = false;
    protected $btCacheBlockOutputForRegisteredUsers = true;

    protected $btCacheBlockOutputLifetime = 0; // 86400 = 1 day

    protected $btSupportsInlineEdit = false;
    protected $btSupportsInlineAdd = false;

    public $allowedUsers;
    public $packageHandle;
    public $packageHandleFixed;
    public $packageVersion;
    public $packageVersionFixed;
    public $allowedFormats;

    public function getBlockTypeName()
    {
        return t('Download translations');
    }

    public function getBlockTypeDescription()
    {
        return t('Allow users to download translations.');
    }

    public function add()
    {
        $this->allowedFormats = self::FORMAT_MO . ',' . self::FORMAT_PO;
        $this->edit();
    }

    public function edit()
    {
        $allowedUsersOptions = [];
        if (!$this->allowedUsers) {
            $allowedUsersOptions = ['' => t('Please Select')];
        }
        $allowedUsersOptions += [
            self::ALLOWEDUSERS_EVERYBODY => t('Everybody'),
            self::ALLOWEDUSERS_TRANSLATORS => t('Only translators'),
            self::ALLOWEDUSERS_LOCALEADMINS => t('Only locale administrators'),
            self::ALLOWEDUSERS_GLOBALADMINS => t('Only global administrators'),
        ];
        $this->set('form', $this->app->make('helper/form'));
        $this->set('allowedUsersOptions', $allowedUsersOptions);
        $this->set('allowedUsers', (string) $this->allowedUsers);
        $this->set('packageHandle', (string) $this->packageHandle);
        $this->set('packageHandleFixed', (bool) $this->packageHandleFixed);
        $this->set('packageVersion', (string) $this->packageVersion);
        $this->set('packageVersionFixed', (bool) $this->packageVersionFixed);
        $this->set('allowedFormats', $this->allowedFormats ? explode(',', $this->allowedFormats) : []);
        $this->set('allowedFormatList', $this->getAvailableFormats());
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::normalizeArgs()
     */
    protected function normalizeArgs(array $args)
    {
        $error = $this->app->make('helper/validation/error');
        $normalized = [];
        $normalized['allowedUsers'] = $this->post('allowedUsers');
        switch (is_string($normalized['allowedUsers']) ? $normalized['allowedUsers'] : '') {
            case self::ALLOWEDUSERS_EVERYBODY:
            case self::ALLOWEDUSERS_TRANSLATORS:
            case self::ALLOWEDUSERS_LOCALEADMINS:
            case self::ALLOWEDUSERS_GLOBALADMINS:
                break;
            default:
                $error->add('Please specify who can download the translations');
                break;
        }
        $normalized['packageHandle'] = $this->post('packageHandle');
        $package = null;
        $ok = false;
        if (is_string($normalized['packageHandle'])) {
            if ($normalized['packageHandle'] === '') {
                $ok = true;
            } else {
                $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $normalized['packageHandle']]);
                $ok = null !== $package;
            }
        }
        if (!$ok) {
            $error->add('The specified package is not valid');
        }
        if ($package === null) {
            $normalized['packageHandleFixed'] = 0;
            $normalized['packageVersion'] = '';
            $normalized['packageVersionFixed'] = 0;
        } else {
            $normalized['packageHandleFixed'] = $this->post('packageHandleFixed') ? 1 : 0;
            $normalized['packageVersion'] = $this->post('packageVersion');
            $packageVersion = null;
            $ok = false;
            if (is_string($normalized['packageVersion'])) {
                if ($normalized['packageVersion'] === '') {
                    $ok = true;
                } else {
                    $packageVersion = $this->app->make(PackageVersionRepository::class)->findOneBy(['package' => $package, 'version' => $normalized['packageVersion']]);
                    $ok = null !== $packageVersion;
                }
            }
            if (!$ok) {
                $error->add('The specified package version is not valid');
            }
            if ($packageVersion === null) {
                $normalized['packageVersionFixed'] = 0;
            } else {
                $normalized['packageVersionFixed'] = $this->post('packageVersionFixed') ? 1 : 0;
            }
        }

        $allowedFormats = [];
        $a = $this->post('allowedFormats');
        if (is_array($a)) {
            $availableFormats = $this->getAvailableFormats();
            foreach (array_unique($a) as $formatID) {
                if (isset($availableFormats[$formatID])) {
                    $allowedFormats[] = $formatID;
                } else {
                    $error->add(t('Invalid format received'));
                }
            }
        }
        $normalized['allowedFormats'] = implode(',', $allowedFormats);
        if ($allowedFormats === '') {
            $error->add(t('Please specify at least one format'));
        }

        return $error->has() ? $error : $normalized;
    }

    private function getAvailableFormats()
    {
        $list = [
            self::FORMAT_JED => t('JED format'),
            self::FORMAT_JSONDICTIONARY => t('JSON Dictionary'),
            self::FORMAT_MO => t('Gettext MO format'),
            self::FORMAT_PHPARRAY => t('PHP Array'),
            self::FORMAT_PO => t('Gettext PO format'),
        ];
        (new Comparer())->sort($list, true);

        return $list;
    }

    /**
     * @return \CommunityTranslation\Entity\Locale[]
     */
    private function getAllowedLocales()
    {
        $allLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        if ($this->allowedUsers === self::ALLOWEDUSERS_EVERYBODY) {
            $result = $allLocales;
        } else {
            switch ($this->allowedUsers) {
                case self::ALLOWEDUSERS_TRANSLATORS:
                    $minLevel = Access::TRANSLATE;
                    break;
                case self::ALLOWEDUSERS_LOCALEADMINS:
                    $minLevel = Access::ADMIN;
                    break;
                case self::ALLOWEDUSERS_GLOBALADMINS:
                default:
                    $minLevel = Access::GLOBAL_ADMIN;
                    break;
            }
            $accessHelper = $this->app->make(Access::class);
            /* @var Access  $accessHelper */
            foreach ($allLocales as $locale) {
                if ($accessHelper->getLocaleAccess($locale) >= $minLevel) {
                    $result[] = $locale;
                }
            }
        }

        return $result;
    }

    public function view()
    {
        try {
            $allowedLocales = $this->getAllowedLocales();
            if (empty($allowedLocales)) {
                throw new UserException(t('Access Denied'));
            }
            $package = null;
            $packageVersion = null;
            if ($this->packageHandle !== '') {
                $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $this->packageHandle]);
                if ($package === null) {
                    if ($this->packageHandleFixed) {
                        throw new UserException(t("Unable to find a package with handle '%1\$s'", $this->packageHandle));
                    }
                } else {
                    if ($this->packageVersion !== '') {
                        $packageVersion = $this->app->make(PackageVersionRepository::class)->findByHandleAndVersion($this->packageHandle, $this->packageVersion);
                        if ($packageVersion === null) {
                            if ($this->packageVersionFixed) {
                                throw new UserException(t("Unable to find a package with handle '%1\$s' with version '$2\$s'", $this->packageHandle, $this->packageVersion));
                            }
                        }
                    }
                }
            }
            if ($package !== null && ($this->packageVersion !== '' || !$this->packageVersionFixed)) {
                $availableVersions = [];
                foreach ($package->getVersions() as $pv) {
                    $availableVersions[$pv->getVersion()] = $pv->getDisplayVersion();
                }
                if (count($availableVersions) === 0 && $this->packageHandleFixed) {
                    throw new UserException(t("The with handle '%1\$s' doesn't have any available version", $this->packageHandle));
                }
                $this->set('availableVersions', $availableVersions);
            }
            $configuredFormats = explode(',', $this->allowedFormats);
            $allowedFormats = [];
            foreach ($this->getAvailableFormats() as $formatID => $formatName) {
                if (in_array($formatID, $configuredFormats, true)) {
                    $allowedFormats[$formatID] = $formatName;
                }
            }
            $this->set('allowedFormats', $allowedFormats);
            $this->set('allowedLocales', $allowedLocales);
            $this->set('package', $package);
            $this->set('packageVersion', $packageVersion);
            $this->set('fixedPackage', $package && $this->packageHandleFixed);
            $this->set('fixedPackageVersion', $packageVersion && $this->packageHandleFixed && $this->packageVersionFixed);
        } catch (UserException $x) {
            $this->set('stopForError', h($x->getMessage()));
        }
    }
}
