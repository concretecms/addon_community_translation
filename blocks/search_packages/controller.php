<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Block\SearchPackages;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Repository\DownloadStats as DownloadStatsRepository;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Repository\Stats as StatsRepository;
use CommunityTranslation\Service\Access as AccessService;
use CommunityTranslation\Service\User as UserService;
use CommunityTranslation\Translation\FileExporter as TranslationFileExporter;
use CommunityTranslation\TranslationsConverter\Provider as TranslationsConverterProvider;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Concrete\Package\CommunityTranslation\Controller\Search\Packages as SearchController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends BlockController
{
    private const ALLOWDOWNLOADFOR_EVERYBODY = 'everybody';

    private const ALLOWDOWNLOADFOR_REGISTEREDUSERS = 'registered-users';

    private const ALLOWDOWNLOADFOR_TRANSLATORS_ALLLOCALES = 'translators-all-locales';

    private const ALLOWDOWNLOADFOR_TRANSLATORS_OWNLOCALES = 'translators-own-locales';

    private const ALLOWDOWNLOADFOR_LOCALEADMINS_ALLLOCALES = 'localeadmins-all-locales';

    private const ALLOWDOWNLOADFOR_LOCALEADMINS_OWNLOCALES = 'localeadmins-own-locales';

    private const ALLOWDOWNLOADFOR_GLOBALADMINS = 'globaladmins';

    private const ALLOWDOWNLOADFOR_NOBODY = 'nobody';

    /**
     * @var int|string|null
     */
    public $resultsPerPage;

    /**
     * @var string|null
     */
    public $allowedDownloadFormats;

    /**
     * @var string|null
     */
    public $allowedDownloadFor;

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
    protected $btTable = 'btCTSearchPackages';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btInterfaceWidth
     */
    protected $btInterfaceWidth = 400;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btInterfaceHeight
     */
    protected $btInterfaceHeight = 450;

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

    private ?int $translatedThreshold = null;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::getBlockTypeName()
     */
    public function getBlockTypeName()
    {
        return t('Search Packages');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::getBlockTypeDescription()
     */
    public function getBlockTypeDescription()
    {
        return t('Allow users to search translated packages and access their translations.');
    }

    public function add()
    {
        $this->edit();
    }

    public function edit()
    {
        $this->set('form', $this->app->make('helper/form'));
        $this->set('resultsPerPage', $this->resultsPerPage ? (int) $this->resultsPerPage : 10);
        $this->set('ALLOWDOWNLOADFOR_NOBODY', self::ALLOWDOWNLOADFOR_NOBODY);
        $this->set('allowedDownloadFor', $this->allowedDownloadFor ?: self::ALLOWDOWNLOADFOR_NOBODY);
        $this->set('allowedDownloadForList', $this->getDownloadAccessLevels());
        $this->set('allowedDownloadFormats', $this->allowedDownloadFormats ? explode(',', $this->allowedDownloadFormats) : []);
        $converters = [];
        foreach ($this->app->make(TranslationsConverterProvider::class)->getRegisteredConverters() as $converter) {
            if ($converter->canSerializeTranslations()) {
                $converters[] = $converter;
            }
        }
        $this->set('downloadFormats', $converters);
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
        return $this->action_search();
    }

    public function action_search(): ?Response
    {
        $this->setCommonSets();
        $resetSearch = false;
        if ($this->request->isMethod(Request::METHOD_POST)) {
            $token = $this->app->make('token');
            if (!$token->validate("communitytranslations-search_packages-{$this->bID}")) {
                $this->set('showWarning', $token->getErrorMessage());
            } else {
                $resetSearch = true;
            }
        }
        $searchController = $this->app->make(SearchController::class);
        $searchController->getSearchList()->setItemsPerPage((int) $this->resultsPerPage ?: 10);
        $result = $searchController->search($resetSearch);
        $this->set('sticky', $searchController->getStickyRequest()->getSearchRequest());
        $this->set('result', $result);
        $this->requireAsset('community_translation/progress-bar');
        $this->render('view.search');

        return null;
    }

    public function action_package(string $handle = '', string $version = ''): ?Response
    {
        if ($handle === '') {
            return $this->action_search();
        }
        $package = $this->app->make(PackageRepository::class)->getByHandle($handle);
        if ($package === null) {
            $this->set('showWarning', h(t('Unable to find a package with handle "%s"', $handle)));

            return $this->action_search();
        }
        $packageVersions = $package->getSortedVersions(true);
        if ($packageVersions === []) {
            $this->set('showWarning', h(t('The package "%s" does not have any versions.', $package->getDisplayName())));

            return $this->action_search();
        }
        $packageVersion = null;
        if ($version !== '') {
            foreach ($packageVersions as $pv) {
                if ($pv->getVersion() === $version) {
                    $packageVersion = $pv;
                    break;
                }
            }
            if ($packageVersion === null) {
                $this->set('showWarning', h(t('The package "%1$s" does not have a version "%2$s"', $package->getDisplayName(), $version)));
            }
        }
        if ($packageVersion === null) {
            $packageVersion = current($packageVersions);
        }
        $this->setCommonSets();
        $this->set('urlResolver', $this->app->make(ResolverManagerInterface::class));
        $this->set('package', $package);
        $this->set('packageVersions', $packageVersions);
        $this->set('packageVersion', $packageVersion);
        $this->setPackageVersionSets($packageVersion);
        $this->requireAsset('community_translation/table-sortable');
        $this->requireAsset('community_translation/progress-bar');
        $this->addHeaderItem(
            <<<'EOT'
<style>
.community_translation-only-search {
    display: none;
}
</style>
EOT
        );
        $this->render('view.package');

        return null;
    }

    public function action_download_translations_file(string|int $packageVersionID = '', string $localeID = '', string $formatHandle = ''): ?Response
    {
        try {
            $token = $this->app->make('token');
            if (!is_numeric($packageVersionID) || !$token->validate("comtra-download-translations-{$packageVersionID}@{$localeID}.{$formatHandle}")) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $packageVersion = $this->app->make(PackageVersionRepository::class)->find((int) $packageVersionID);
            if ($packageVersion === null) {
                throw new UserMessageException(t('Unable to find the specified package'));
            }
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserMessageException(t('Unable to find the specified language'));
            }
            $formats = $this->getAllowedDownloadFormats($locale);
            $format = $formats[$formatHandle] ?? null;
            if ($format === null) {
                throw new UserMessageException(t('Unable to find the specified translations file format'));
            }
            $response = $this->app->make(TranslationFileExporter::class)->buildSerializedTranslationsFileResponse($packageVersion, $locale, $format);
            $this->app->make(DownloadStatsRepository::class)->logDownload($locale, $packageVersion);

            return $response;
        } catch (UserMessageException $x) {
            return $this->app->make('helper/concrete/ui')->buildErrorResponse(
                t('Error'),
                h($x->getMessage())
            );
        }
    }

    public function percToProgressbarClass(int $perc, ?int $translatedThreshold = null): string
    {
        $classes = [];
        if ($perc >= 100) {
            $classes[] = 'bg-success';
        } elseif ($perc <= 0) {
            $classes[] = 'bg-danger';
        } else {
            if ($translatedThreshold === null) {
                $translatedThreshold = $this->getTranslatedThreshold();
            }
            $classes[] = $perc >= $translatedThreshold ? 'bg-info' : 'bg-warning';
        }
        if ($perc > 0 && $perc < 10) {
            $classes[] = 'progress-bar-minwidth1';
        } elseif ($perc >= 10 && $perc < 100) {
            $classes[] = 'progress-bar-minwidth2';
        }

        return implode(' ', $classes);
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Controller\BlockController::normalizeArgs()
     */
    protected function normalizeArgs(array $args)
    {
        $normalized = [];
        $error = $this->app->make('helper/validation/error');
        $valn = $this->app->make('helper/validation/numbers');
        if ($valn->integer($args['resultsPerPage'] ?? null, 1)) {
            $normalized['resultsPerPage'] = (int) $args['resultsPerPage'];
        } else {
            $error->add(t('Please specify the number of search results per page.'));
        }
        $allowedDownloadFormats = [];
        if (is_array($args['allowedDownloadFormats'] ?? null)) {
            $tcProvider = $this->app->make(TranslationsConverterProvider::class);
            foreach (array_unique($args['allowedDownloadFormats']) as $adf) {
                if (!is_string($adf) || $tcProvider->isRegistered($adf) === false) {
                    $error->add(t('Invalid format identifier received'));
                } else {
                    $allowedDownloadFormats[] = $adf;
                }
            }
        }
        $normalized['allowedDownloadFormats'] = implode(',', $allowedDownloadFormats);
        $normalized['allowedDownloadFor'] = is_string($args['allowedDownloadFor'] ?? null) ? $args['allowedDownloadFor'] : '';
        if (!array_key_exists($normalized['allowedDownloadFor'], $this->getDownloadAccessLevels())) {
            $error->add(t('Please specify who can download the translations'));
        }
        if (!$error->has()) {
            if ($normalized['allowedDownloadFor'] !== self::ALLOWDOWNLOADFOR_NOBODY && $normalized['allowedDownloadFormats'] === '') {
                $error->add(t('If you specify that some user can download the translations, you should specify the allowed download formats'));
            }
        }

        return $error->has() ? $error : $normalized;
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::isControllerTaskInstanceSpecific($method)
     */
    protected function isControllerTaskInstanceSpecific(string $method): bool
    {
        switch (strtolower($method)) {
            case 'action_package':
            case 'action_search':
                return false;
            default:
                return true;
        }
    }

    private function getDownloadAccessLevels(): array
    {
        return [
            self::ALLOWDOWNLOADFOR_EVERYBODY => t('Everybody'),
            self::ALLOWDOWNLOADFOR_REGISTEREDUSERS => t('Registered Users'),
            self::ALLOWDOWNLOADFOR_TRANSLATORS_ALLLOCALES => t('Translators (all languages)'),
            self::ALLOWDOWNLOADFOR_TRANSLATORS_OWNLOCALES => t('Translators (own languages only)'),
            self::ALLOWDOWNLOADFOR_LOCALEADMINS_ALLLOCALES => t('Language coordinators (all languages)'),
            self::ALLOWDOWNLOADFOR_LOCALEADMINS_OWNLOCALES => t('Language coordinators (own languages only)'),
            self::ALLOWDOWNLOADFOR_GLOBALADMINS => t('Global language administrators'),
            self::ALLOWDOWNLOADFOR_NOBODY => t('Nobody'),
        ];
    }

    private function setCommonSets(): void
    {
        $allLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        $myLocales = [];
        $accessService = $this->getAccessService();
        if ($this->getUserService()->isLoggedIn()) {
            foreach ($allLocales as $locale) {
                if ($accessService->getLocaleAccess($locale) >= AccessService::TRANSLATE) {
                    $myLocales[] = $locale;
                }
            }
        }
        $numMyLocales = count($myLocales);
        if (0 < $numMyLocales && $numMyLocales < count($allLocales)) {
            $suggestedLocales = $myLocales;
        } else {
            $suggestedLocales = [];
            $browserLocales = [];
            foreach (array_keys(\Punic\Misc::getBrowserLocales()) as $bl) {
                $plChunks = explode('-', $bl);
                $browserLocales[] = $plChunks[0];
                if (isset($plChunks[1])) {
                    $browserLocales[] = $plChunks[0] . '_' . $plChunks[1];
                }
            }
            foreach ($allLocales as $locale) {
                $add = in_array($locale->getID(), $browserLocales);
                if ($add === false) {
                    $plChunks = explode('_', $locale->getID());
                    $add = isset($plChunks[1]) && in_array($plChunks[0], $browserLocales);
                }
                if ($add) {
                    $suggestedLocales[] = $locale;
                }
            }
            if (count($suggestedLocales) === count($allLocales)) {
                $suggestedLocales = [];
            }
        }
        $this->set('allLocales', $allLocales);
        $this->set('suggestedLocales', $suggestedLocales);
        $this->set('myLocales', $myLocales);
        $this->set('token', $this->app->make('token'));
    }

    /**
     * @param \Concrete\Core\User\User|'current'|null $user
     * @param \CommunityTranslation\Entity\Locale[]|null $approvedLocales
     *
     * @return \CommunityTranslation\TranslationsConverter\ConverterInterface[]
     */
    private function getAllowedDownloadFormats(LocaleEntity $locale, $user = UserService::CURRENT_USER_KEY, ?array $approvedLocales = null): array
    {
        if (!$this->allowedDownloadFormats) {
            return [];
        }
        $allowedFormats = [];
        $tcProvider = $this->app->make(TranslationsConverterProvider::class);
        foreach (explode(',', $this->allowedDownloadFormats) as $adf) {
            $converter = $tcProvider->getByHandle($adf);
            if ($converter !== null && $converter->canSerializeTranslations()) {
                $allowedFormats[$adf] = $converter;
            }
        }
        if ($allowedFormats === []) {
            return [];
        }
        switch ($this->allowedDownloadFor) {
            case self::ALLOWDOWNLOADFOR_EVERYBODY:
                return $allowedFormats;
            case self::ALLOWDOWNLOADFOR_REGISTEREDUSERS:
                if ($user === UserService::CURRENT_USER_KEY) {
                    $user = $this->getUserService()->getUserObject($user);
                }

                return $user === null ? [] : $allowedFormats;
            case self::ALLOWDOWNLOADFOR_TRANSLATORS_ALLLOCALES:
                if ($approvedLocales === null) {
                    $approvedLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
                }
                if ($user === UserService::CURRENT_USER_KEY) {
                    $user = $this->getUserService()->getUserObject($user);
                }
                foreach ($approvedLocales as $l) {
                    if ($this->getAccessService()->getLocaleAccess($l, $user) >= AccessService::TRANSLATE) {
                        return $allowedFormats;
                    }
                }

                return [];
            case self::ALLOWDOWNLOADFOR_TRANSLATORS_OWNLOCALES:
                if ($this->getAccessService()->getLocaleAccess($locale, $user) >= AccessService::TRANSLATE) {
                    return $allowedFormats;
                }

                return [];
            case self::ALLOWDOWNLOADFOR_LOCALEADMINS_ALLLOCALES:
                if ($approvedLocales === null) {
                    $approvedLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
                }
                if ($user === UserService::CURRENT_USER_KEY) {
                    $user = $this->getUserService()->getUserObject($user);
                }
                foreach ($approvedLocales as $l) {
                    if ($this->getAccessService()->getLocaleAccess($l, $user) >= AccessService::ADMIN) {
                        return $allowedFormats;
                    }
                }

                return [];
            case self::ALLOWDOWNLOADFOR_LOCALEADMINS_OWNLOCALES:
                if ($this->getAccessService()->getLocaleAccess($locale, $user) >= AccessService::TRANSLATE) {
                    return $allowedFormats;
                }

                return [];
            case self::ALLOWDOWNLOADFOR_GLOBALADMINS:
                if ($this->getAccessService()->getLocaleAccess($locale, $user) >= AccessService::GLOBAL_ADMIN) {
                    return $allowedFormats;
                }

                return [];
        }

        return [];
    }

    private function getTranslatedThreshold(): int
    {
        if ($this->translatedThreshold === null) {
            $config = $this->app->make(Repository::class);
            $this->translatedThreshold = (int) $config->get('community_translation::translate.translatedThreshold', 90);
        }

        return $this->translatedThreshold;
    }

    private function setPackageVersionSets(PackageVersionEntity $packageVersion): void
    {
        $dh = $this->app->make('date');
        $translatedThreshold = $this->getTranslatedThreshold();

        $approvedLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        $me = $this->getUserService()->getUserObject(UserService::CURRENT_USER_KEY);
        $stats = $this->app->make(StatsRepository::class)->get($packageVersion, $approvedLocales);
        $localeInfos = [];
        foreach ($approvedLocales as $locale) {
            $localeStats = null;
            foreach ($stats as $s) {
                if ($s->getLocale() === $locale) {
                    $localeStats = $s;
                    break;
                }
            }
            $udpatedOn = $localeStats === null ? null : $localeStats->getLastUpdated();
            $localeInfos[$locale->getID()] = [
                'translatedStrings' => $localeStats === null ? 0 : $localeStats->getTranslated(),
                'totalStrings' => $localeStats === null ? 0 : $localeStats->getTotal(),
                'perc' => $localeStats === null ? 0 : $localeStats->getRoundedPercentage(),
                'percSort' => $localeStats === null ? 0 : $localeStats->getPercentage(),
                'progressBarClass' => $this->percToProgressbarClass($localeStats === null ? 0 : $localeStats->getRoundedPercentage(), $translatedThreshold),
                'untranslated' => $localeStats === null ? null : $localeStats->getUntranslated(),
                'updatedOn' => $udpatedOn === null ? '' : $dh->formatDateTime($localeStats->getLastUpdated(), false),
                'updatedOn_sort' => $udpatedOn === null ? '' : $udpatedOn->format('c'),
                'downloadFormats' => $this->getAllowedDownloadFormats($locale, $me, $approvedLocales),
            ];
        }
        $this->set('localeInfos', $localeInfos);
        $this->set('userIsLoggedIn', $me !== null);
        $config = $this->app->make(Repository::class);
        $this->set('onlineTranslationPath', (string) $config->get('community_translation::paths.onlineTranslation'));
    }
}
