<?php

namespace Concrete\Package\CommunityTranslation\Block\SearchPackages;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Repository\DownloadStats as DownloadStatsRepository;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Repository\Stats as StatsRepository;
use CommunityTranslation\Service\Access;
use CommunityTranslation\Service\TranslationsFileExporter;
use CommunityTranslation\TranslationsConverter\Provider as TranslationsConverterProvider;
use Concrete\Core\Error\UserMessageException;
use Concrete\Package\CommunityTranslation\Controller\Search\Packages as SearchController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class Controller extends BlockController
{
    const ALLOWDOWNLOADFOR_EVERYBODY = 'everybody';
    const ALLOWDOWNLOADFOR_REGISTEREDUSERS = 'registered-users';
    const ALLOWDOWNLOADFOR_TRANSLATORS_ALLLOCALES = 'translators-all-locales';
    const ALLOWDOWNLOADFOR_TRANSLATORS_OWNLOCALES = 'translators-own-locales';
    const ALLOWDOWNLOADFOR_LOCALEADMINS_ALLLOCALES = 'localeadmins-all-locales';
    const ALLOWDOWNLOADFOR_LOCALEADMINS_OWNLOCALES = 'localeadmins-own-locales';
    const ALLOWDOWNLOADFOR_GLOBALADMINS = 'globaladmins';
    const ALLOWDOWNLOADFOR_NOBODY = 'nobody';

    public $helpers = [];

    protected $btTable = 'btCTSearchPackages';

    protected $btInterfaceWidth = 600;
    protected $btInterfaceHeight = 600;

    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = false;
    protected $btCacheBlockOutputOnPost = false;
    protected $btCacheBlockOutputForRegisteredUsers = false;

    protected $btCacheBlockOutputLifetime = 0; // 86400 = 1 day

    protected $btSupportsInlineEdit = false;
    protected $btSupportsInlineAdd = false;

    public $resultsPerPage;
    public $allowedDownloadFormats;
    public $allowedDownloadFor;

    public function getBlockTypeName()
    {
        return t('Search Packages');
    }

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
        $this->set('resultsPerPage', $this->resultsPerPage ? (int) $this->resultsPerPage : 10);
        $this->set('allowedDownloadFor', $this->allowedDownloadFor ?: static::ALLOWDOWNLOADFOR_NOBODY);
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
     * @see BlockController::normalizeArgs()
     */
    protected function normalizeArgs(array $args)
    {
        $args += [
            'resultsPerPage' => null,
            'allowedDownloadFormats' => null,
        ];
        $normalized = [];
        $error = $this->app->make('helper/validation/error');
        /* @var \Concrete\Core\Error\ErrorList\ErrorList $error */
        $valn = $this->app->make('helper/validation/numbers');
        /* @var \Concrete\Core\Utility\Service\Validation\Numbers $valn */
        if ($valn->integer($args['resultsPerPage'], 1)) {
            $normalized['resultsPerPage'] = (int) $args['resultsPerPage'];
        } else {
            $error->add(t('Please specify the number of search results per page.'));
        }
        $allowedDownloadFormats = [];
        if (is_array($args['allowedDownloadFormats'])) {
            $tcProvider = $this->app->make(TranslationsConverterProvider::class);
            foreach (array_unique($args['allowedDownloadFormats']) as $adf) {
                if ($tcProvider->isRegistered($adf) === false) {
                    $error->add(t('Invalid format identifier received'));
                } else {
                    $allowedDownloadFormats[] = $adf;
                }
            }
        }
        $normalized['allowedDownloadFormats'] = implode(',', $allowedDownloadFormats);
        $normalized['allowedDownloadFor'] = (isset($args['allowedDownloadFor']) && is_string($args['allowedDownloadFor'])) ? $args['allowedDownloadFor'] : '';
        if (!array_key_exists($normalized['allowedDownloadFor'], $this->getDownloadAccessLevels())) {
            $error->add(t('Please specify who can download the translations'));
        }
        if (!$error->has()) {
            if ($normalized['allowedDownloadFor'] !== static::ALLOWDOWNLOADFOR_NOBODY && $normalized['allowedDownloadFormats'] === '') {
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
    protected function isControllerTaskInstanceSpecific($method)
    {
        switch (strtolower($method)) {
            case 'action_package':
            case 'action_search':
                return false;
            default:
                return true;
        }
    }

    /**
     * @return array
     */
    private function getDownloadAccessLevels()
    {
        return [
            static::ALLOWDOWNLOADFOR_EVERYBODY => t('Everybody'),
            static::ALLOWDOWNLOADFOR_REGISTEREDUSERS => t('Registered Users'),
            static::ALLOWDOWNLOADFOR_TRANSLATORS_ALLLOCALES => t('Translators (all languages)'),
            static::ALLOWDOWNLOADFOR_TRANSLATORS_OWNLOCALES => t('Translators (own languages only)'),
            static::ALLOWDOWNLOADFOR_LOCALEADMINS_ALLLOCALES => t('Language coordinators (all languages)'),
            static::ALLOWDOWNLOADFOR_LOCALEADMINS_OWNLOCALES => t('Language coordinators (own languages only)'),
            static::ALLOWDOWNLOADFOR_GLOBALADMINS => t('Global language administrators'),
            static::ALLOWDOWNLOADFOR_NOBODY => t('Nobody'),
        ];
    }

    private function setCommonSets()
    {
        $allLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        $myLocales = [];
        $accessHelper = $this->getAccess();
        if ($accessHelper->isLoggedIn()) {
            foreach ($allLocales as $locale) {
                if ($accessHelper->getLocaleAccess($locale, 'current') >= Access::TRANSLATE) {
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
     * @param LocaleEntity $locale
     * @param mixed $user
     * @param LocaleEntity[]|null $approvedLocales
     *
     * @return \CommunityTranslation\TranslationsConverter\ConverterInterface[]
     */
    private function getAllowedDownloadFormats(LocaleEntity $locale, $user = 'current', array $approvedLocales = null)
    {
        $result = [];
        $allowedFormats = [];
        if ($this->allowedDownloadFormats) {
            $tcProvider = $this->app->make(TranslationsConverterProvider::class);
            foreach (explode(',', $this->allowedDownloadFormats) as $adf) {
                $converter = $tcProvider->getByHandle($adf);
                if ($converter !== null && $converter->canSerializeTranslations()) {
                    $allowedFormats[$adf] = $converter;
                }
            }
        }
        if (!empty($allowedFormats)) {
            $userOk = false;
            switch ($this->allowedDownloadFor) {
                case static::ALLOWDOWNLOADFOR_EVERYBODY:
                    $userOk = true;
                    break;
                case static::ALLOWDOWNLOADFOR_REGISTEREDUSERS:
                    if ($user === 'current') {
                        $user = $this->getAccess()->getUser('current');
                    }
                    if ($user !== null) {
                        $userOk = true;
                    }
                    break;
                case static::ALLOWDOWNLOADFOR_TRANSLATORS_ALLLOCALES:
                    if ($approvedLocales === null) {
                        $approvedLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
                    }
                    if ($user === 'current') {
                        $user = $this->getAccess()->getUser('current');
                    }
                    foreach ($approvedLocales as $l) {
                        if ($this->getAccess()->getLocaleAccess($l, $user) >= Access::TRANSLATE) {
                            $userOk = true;
                            break;
                        }
                    }
                    break;
                case static::ALLOWDOWNLOADFOR_TRANSLATORS_OWNLOCALES:
                    if ($this->getAccess()->getLocaleAccess($locale, $user) >= Access::TRANSLATE) {
                        $userOk = true;
                    }
                    break;
                case static::ALLOWDOWNLOADFOR_LOCALEADMINS_ALLLOCALES:
                    if ($approvedLocales === null) {
                        $approvedLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
                    }
                    if ($user === 'current') {
                        $user = $this->getAccess()->getUser('current');
                    }
                    foreach ($approvedLocales as $l) {
                        if ($this->getAccess()->getLocaleAccess($l, $user) >= Access::ADMIN) {
                            $userOk = true;
                            break;
                        }
                    }
                    break;
                case static::ALLOWDOWNLOADFOR_LOCALEADMINS_OWNLOCALES:
                    if ($this->getAccess()->getLocaleAccess($locale, $user) >= Access::TRANSLATE) {
                        $userOk = true;
                    }
                    break;
                case static::ALLOWDOWNLOADFOR_GLOBALADMINS:
                    if ($this->getAccess()->getLocaleAccess($locale, $user) >= Access::GLOBAL_ADMIN) {
                        $userOk = true;
                    }
                    break;
            }
            if ($userOk === true) {
                $result = $allowedFormats;
            }
        }

        return $result;
    }

    /**
     * @var int|null
     */
    private $translatedThreshold;

    /**
     * @return int
     */
    private function getTranslatedThreshold()
    {
        if (!isset($this->translatedThreshold)) {
            $config = $this->app->make('community_translation/config');
            $this->translatedThreshold = (int) $config->get('options.translatedThreshold', 90);
        }

        return $this->translatedThreshold;
    }

    /**
     * @param int $perc
     * @param int|null $translatedThreshold
     *
     * @return string
     */
    public function percToProgressbarClass($perc, $translatedThreshold = null)
    {
        if ($translatedThreshold === null) {
            $translatedThreshold = $this->getTranslatedThreshold();
        }
        if ($perc >= 100) {
            $percClass = 'progress-bar-success';
        } elseif ($perc >= $translatedThreshold) {
            $percClass = 'progress-bar-info';
        } elseif ($perc > 0) {
            $percClass = 'progress-bar-warning';
        } else {
            $percClass = 'progress-bar-danger';
        }
        if ($perc > 0 && $perc < 10) {
            $percClass .= ' progress-bar-minwidth1';
        } elseif ($perc >= 10 && $perc < 100) {
            $percClass .= ' progress-bar-minwidth2';
        }

        return $percClass;
    }

    private function setPackageVersionSets(PackageVersionEntity $packageVersion)
    {
        $dh = $this->app->make('date');
        $translatedThreshold = $this->getTranslatedThreshold();

        $approvedLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        $accessHelper = $this->getAccess();
        $me = $accessHelper->getUser('current');
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
            $udpatedOn = ($localeStats === null) ? null : $localeStats->getLastUpdated();
            $localeInfos[$locale->getID()] = [
                'perc' => ($localeStats === null) ? 0 : $localeStats->getPercentage(true),
                'percSort' => ($localeStats === null) ? 0 : $localeStats->getPercentage(false),
                'progressBarClass' => $this->percToProgressbarClass(($localeStats === null) ? 0 : $localeStats->getPercentage(true), $translatedThreshold),
                'untranslated' => ($localeStats === null) ? null : $localeStats->getUntranslated(),
                'updatedOn' => ($udpatedOn === null) ? '' : $dh->formatDateTime($localeStats->getLastUpdated(), false),
                'updatedOn_sort' => ($udpatedOn === null) ? '' : $udpatedOn->format('c'),
                'downloadFormats' => $this->getAllowedDownloadFormats($locale, $me, $approvedLocales),
            ];
        }
        $this->requireAsset('jquery/comtraSortable');
        $this->set('localeInfos', $localeInfos);
        $this->set('showLoginMessage', !$accessHelper->isLoggedIn());
        $config = $this->app->make('community_translation/config');
        $this->set('onlineTranslationPath', $config->get('options.onlineTranslationPath'));
    }

    public function view()
    {
        $this->action_search();
    }

    public function action_search()
    {
        $resetSearch = false;
        if ($this->request->isPost()) {
            $token = $this->app->make('token');
            if (!$token->validate('communitytranslations-search_packages-' . $this->bID)) {
                $this->set('showWarning', $token->getErrorMessage());
            } else {
                $resetSearch = true;
            }
        }
        $post = $this->request->request;
        $searchController = $this->app->make(SearchController::class);
        /* @var SearchController $searchController */
        $searchController->getSearchList()->setItemsPerPage((int) $this->resultsPerPage ?: 10);
        $searchController->search($resetSearch);
        $result = $searchController->getSearchResultObject();
        $this->set('sticky', $searchController->getStickyRequest()->getSearchRequest());
        $this->set('result', $result);
        $this->setCommonSets();
        $this->addHeaderItem(<<<EOT
<style>
.community_translation-only-package {
    display: none;
}
</style>
EOT
        );
    }

    public function action_package($handle = '', $version = '')
    {
        if (!is_string($handle) || $handle === '') {
            return $this->redirect($this->action(''));
        }
        $package = null;
        $packageVersions = null;
        $packageVersion = null;
        $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $handle]);
        if ($package === null) {
            $this->set('showWarning', h(t('Unable to find a package with handle "%s"', $handle)));
        } else {
            /* @var PackageEntity $package */
            $packageVersions = $package->getSortedVersions(true);
            if (empty($packageVersions)) {
                $this->set('showWarning', h(t('The package "%s" does not have any versions.', $package->getDisplayName())));
            } else {
                if (is_string($version) && $version !== '') {
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
            }
        }
        if ($packageVersion === null) {
            $this->action_search();
        } else {
            $this->set('package', $package);
            $this->set('packageVersions', $packageVersions);
            $this->set('packageVersion', $packageVersion);
            $this->setCommonSets();
            $this->setPackageVersionSets($packageVersion);
            $this->addHeaderItem(<<<EOT
<style>
.community_translation-only-search {
    display: none;
}
</style>
EOT
            );
        }
    }

    public function action_download_translations_file($packageVersionID = '', $localeID = '', $formatHandle = '')
    {
        try {
            $token = $this->app->make('token');
            if (!$token->validate('comtra-download-translations-' . $packageVersionID . '@' . $localeID . '.' . $formatHandle)) {
                throw new UserMessageException($token->getErrorMessage());
            }
            $packageVersion = $this->app->make(PackageVersionRepository::class)->find((int) $packageVersionID);
            if ($packageVersion === null) {
                throw new UserMessageException(t('Unable to find the specified package'));
            }
            $locale = $this->app->make(LocaleRepository::class)->findApproved((string) $localeID);
            if ($locale === null) {
                throw new UserMessageException(t('Unable to find the specified language'));
            }
            $formats = $this->getAllowedDownloadFormats($locale);
            if (!array_key_exists($formatHandle, $formats)) {
                throw new UserMessageException(t('Unable to find the specified translations file format'));
            }
            $format = $formats[$formatHandle];
            $serializedTranslationsFile = $this->app->make(TranslationsFileExporter::class)->getSerializedTranslationsFile($packageVersion, $locale, $format);
            $this->app->make(DownloadStatsRepository::class)->logDownload($locale, $packageVersion);

            return BinaryFileResponse::create(
                // $file
                $serializedTranslationsFile,
                // $status
                Response::HTTP_OK,
                // $headers
                [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Transfer-Encoding' => 'binary',
                ]
            )->setContentDisposition(
                'attachment',
                'translations-' . $locale->getID() . '.' . $format->getFileExtension()
            );
        } catch (UserMessageException $x) {
            return $this->app->make('helper/concrete/ui')->buildErrorResponse(
                t('Error'),
                h($x->getMessage())
            );
        }
    }

    private function resultToJSON(PackageEntity $package)
    {
        $result = null;
        $versions = $package->getSortedVersions(true);
        if (!empty($versions)) {
            $result = [
                'handle' => $package->getHandle(),
                'name' => $package->getDisplayName(),
                'versions' => [],
            ];
            foreach ($versions as $version) {
                $result['versions'][] = [
                    'id' => $version->getID(),
                    'name' => $version->getDisplayVersion(),
                ];
            }
        }

        return $result;
    }

    /**
     * @param string $text
     *
     * @return string[]
     */
    private function getSearchWords($text)
    {
        $result = [];
        if (is_string($text)) {
            $text = trim(preg_replace('/[\W_]+/u', ' ', $text));
            if ($text !== '') {
                $words = explode(' ', mb_strtolower($text));
                $words = array_values(array_unique($words));
                $duplicates = [];
                for ($i = 0; $i < count($words); ++$i) {
                    for ($j = 0; $j < count($words); ++$j) {
                        if ($i !== $j && @mb_stripos($words[$i], $words[$j]) === 0) {
                            $duplicates[] = $words[$j];
                        }
                    }
                }
                if (!empty($duplicates)) {
                    $words = array_values(array_diff($words, $duplicates));
                }
                $result = $words;
            }
        }

        return $result;
    }

    /**
     * @param string[] $words
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function buildSearchQuery(array $words)
    {
        $qb = $this->app->make(PackageRepository::class)->createQueryBuilder('p');
        $expr = $qb->expr();
        $orFields = $expr->orX();
        $counter = 0;
        foreach (['p.handle', 'p.name'] as $fieldIndex => $fieldName) {
            $and = $expr->andX();
            foreach ($words as $word) {
                $and->add($expr->like($fieldName, $expr->literal('%' . $word . '%')));
            }
            $orFields->add($and);
        }
        $qb->where($orFields);
        if ($this->maximumSearchResults) {
            $qb->setMaxResults($this->maximumSearchResults);
        }

        return $qb;
    }
}
