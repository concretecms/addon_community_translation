<?php
namespace Concrete\Package\CommunityTranslation\Block\SearchPackages;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Repository\Stats as StatsRepository;
use CommunityTranslation\Service\Access;
use CommunityTranslation\UserException;

class Controller extends BlockController
{
    const MIN_SEARCH_LENGTH = 3;
    const MAX_SEARCH_RESULTS = 10;
    public $helpers = [];

    protected $btTable = 'btCTSearchPackages';

    protected $btInterfaceWidth = 600;
    protected $btInterfaceHeight = 200;

    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = false;
    protected $btCacheBlockOutputOnPost = false;
    protected $btCacheBlockOutputForRegisteredUsers = false;

    protected $btCacheBlockOutputLifetime = 0; // 86400 = 1 day

    protected $btSupportsInlineEdit = false;
    protected $btSupportsInlineAdd = false;

    public $preloadPackageHandle;

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
        $this->set('preloadPackageHandle', (string) $this->preloadPackageHandle);
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
            'preloadPackageHandle' => '',
        ];
        if (isset($args['preloadPackageHandle']) && is_string($args['preloadPackageHandle'])) {
            $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $args['preloadPackageHandle']]);
            if ($package === null) {
                $error->add(t('No package with handle "%s"', h($args['preloadPackageHandle'])));
            } else {
                $normalized['preloadPackageHandle'] = $package->getHandle();
            }
        }

        return $error->has() ? $error : $normalized;
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::getInstanceSpecificTasks()
     */
    protected function getInstanceSpecificTasks()
    {
        return [
            '!action_package',
        ];
    }

    private function setCommonSets()
    {
        $this->set('token', $this->app->make('token'));
    }

    /**
     * @param int $perc
     *
     * @return string
     */
    private function percToColor($perc)
    {
        if ($perc < 50) {
            $r = 255;
            $g = (int) round(5.1 * $perc);
            $b = 0;
        } else {
            $g = 255;
            $r = (int) round(510 - 5.10 * $perc);
            $b = 0;
        }
        $h = ($r << 16) + ($g << 8) + ($b << 0);

        return '#' . str_pad(dechex($h), 6, '0', STR_PAD_LEFT);
    }

    private function setPackageVersionSets(PackageVersionEntity $packageVersion)
    {
        $allLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        $myLocales = [];
        $accessHelper = $this->app->make(Access::class);
        /* @var Access $accessHelper */
        if ($accessHelper->isLoggedIn()) {
            foreach ($allLocales as $locale) {
                if ($accessHelper->getLocaleAccess($locale, 'current') >= Access::TRANSLATE) {
                    $myLocales[] = $locale;
                }
            }
        }
        $stats = $this->app->make(StatsRepository::class)->get($packageVersion, $allLocales);
        $localeInfos = [];
        foreach ($allLocales as $locale) {
            $localeStats = null;
            foreach ($stats as $s) {
                if ($s->getLocale() === $locale) {
                    $localeStats = $s;
                    break;
                }
            }
            $localeInfos[$locale->getID()] = [
                'perc' => ($localeStats === null) ? 0 : $localeStats->getPercentage(true),
                'color' => $this->percToColor(($localeStats === null) ? 0 : $localeStats->getPercentage(true)),
                'untranslated' => ($localeStats === null) ? null : $localeStats->getUntranslated(),
            ];
        }
        $this->set('allLocales', $allLocales);
        $this->set('myLocales', $myLocales);
        $this->set('localeInfos', $localeInfos);
        $this->set('showLoginMessage', !$accessHelper->isLoggedIn());
        $config = $this->app->make('community_translation/config');
        $this->set('onlineTranslationPath', $config->get('options.onlineTranslationPath'));
    }

    public function view()
    {
        $package = null;
        if ($this->preloadPackageHandle === '') {
            $package = null;
        } else {
            $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $this->preloadPackageHandle]);
        }
        if ($package === null) {
            $packageVersions = null;
        } else {
            $packageVersions = $package->getSortedVersions(true);
        }
        if (empty($packageVersions)) {
            $packageVersion = null;
        } else {
            $packageVersion = current($packageVersions);
        }
        $this->setCommonSets();
        $this->set('package', $package);
        $this->set('packageVersions', $packageVersions);
        $this->set('packageVersion', $packageVersion);
        if ($packageVersion !== null) {
            $this->setPackageVersionSets($packageVersion);
        }
    }

    public function action_search()
    {
        try {
            $token = $this->app->make('token');
            if (!$token->validate('comtra_search_packages-search')) {
                new UserException($token->getErrorMessage());
            }
            $text = $this->request->query->get('text');
            $words = is_string($text) ? $this->getSearchWords($text) : [];
            if (count($words) > 0) {
                $this->set('searchText', $text);
                if (mb_strlen(implode('', $words)) < static::MIN_SEARCH_LENGTH) {
                    throw new UserException(t('Please be more specific with your search'));
                }
                $qb = $this->buildSearchQuery($words);
                $packages = $qb->getQuery()->execute();
                switch (count($packages)) {
                    case 0:
                        throw new UserException(t('No results found'));
                    case 1:
                        $this->action_package($packages[0]->getHandle());
                        break;
                    default:
                        $this->set('foundPackages', $packages);
                        break;
                }
            }
        } catch (UserException $x) {
            $this->set('searchError', h($x->getMessage()));
        }

        $this->setCommonSets();
    }

    public function action_package($handle = '', $version = '')
    {
        $package = null;
        $packageVersions = null;
        $packageVersion = null;
        if (is_string($handle) && $handle !== '') {
            $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $handle]);
            if ($package === null) {
                $this->set('showWarning', h(t('Unable to find a package with handle "%s"', $handle)));
            } else {
                $packageVersions = $package->getSortedVersions(true);
                if (!empty($packageVersions)) {
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
                    } else {
                        $packageVersion = current($packageVersions);
                    }
                }
            }
        } else {
            $package = null;
        }
        $this->setCommonSets();
        $this->set('package', $package);
        $this->set('packageVersions', $packageVersions);
        $this->set('packageVersion', $packageVersion);
        if ($packageVersion !== null) {
            $this->setPackageVersionSets($packageVersion);
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
        $qb->where($orFields)->setMaxResults(static::MAX_SEARCH_RESULTS);

        return $qb;
    }
}
