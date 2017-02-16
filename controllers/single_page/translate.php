<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage;

use CommunityTranslation\Locale\Locale;
use CommunityTranslation\Package\Package;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Service\Access;
use Concrete\Core\Page\Controller\PageController;

class Translate extends PageController
{
    const MAX_LOCALES = 3;

    public function view()
    {
        $this->redirect('/translate', 'core');
    }

    /**
     * @return Locale[]
     */
    protected function getPostedLocales()
    {
        $result = [];
        if ($this->isPost()) {
            $rx = $this->post('locales');
            if (is_array($rx)) {
                $repo = $this->app->make(LocaleRepository::class);
                foreach ($rx as $id) {
                    if ($id && is_string($id)) {
                        $locale = $repo->findApproved($id);
                        if ($locale !== null) {
                            $result[] = $locale;
                        }
                    }
                }
            }
        }

        return $result;
    }

    protected function deliverLocales()
    {
        $locales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        $officialLocales = [];
        $coreVersion = $this->app->make('community_translation/package')->getLatestVersion('');
        if ($coreVersion !== null) {
            $threshold = (int) $this->app->make('community_translation/config')->get('options.translatedThreshold', 90);
            $statsList = $this->app->make('community_translation/stats')->get(['', $coreVersion], $locales);
            foreach ($statsList as $stats) {
                if ($stats->getPercentage() >= $threshold) {
                    $officialLocales[] = $stats->getLocale();
                }
            }
        }
        if (empty($officialLocales)) {
            $officialLocales = $locales;
        }
        $otherLocales = array_values(array_diff($locales, $officialLocales));
        $this->set('officialLocales', $officialLocales);
        $this->set('otherLocales', $otherLocales);
        $checkedLocales = $this->getPostedLocales();
        if (empty($checkedLocales)) {
            $access = $this->app->make(Access::class);
            foreach ($locales as $locale) {
                $a = $access->getLocaleAccess($locale);
                if ($a >= Access::ASPRIRING && $a <= Access::ADMIN) {
                    $checkedLocales[] = $locale;
                }
            }
        }
        $this->set('checkedLocales', $checkedLocales);
    }

    /**
     * @param Locale[] $packages
     * @param Package[] $packages
     */
    protected function deliverPackages($locales, $packages)
    {
        usort($packages, function (Package $a, Package $b) {
            if ($a->isDevVersion() === $b->isDevVersion()) {
                return version_compare($b->getVersion(), $a->getVersion());
            } elseif ($a->isDevVersion()) {
                return -1;
            } else {
                return 1;
            }
        });
        $this->set('translatedThreshold', $this->app->make('community_translation/config')->get('options.translatedThreshold', 90));
        $stats = $this->app->make('community_translation/stats')->get($packages, $locales);
        $this->set('packages', $packages);
        $this->set('stats', $stats);
    }

    public function core()
    {
        $hh = $this->app->make('helper/html');
        $this->addHeaderItem($hh->css('translate.css', 'community_translation'));
        $this->set('section', 'core');
        $this->requireAsset('select2');
        $token = $this->app->make('helper/validation/token');
        $this->set('token', $token);
        $this->deliverLocales();
        if ($this->isPost()) {
            if (!$token->validate('comtra_core')) {
                $this->set('error', $token->getErrorMessage());
            } else {
                $locales = $this->getPostedLocales();
                if (empty($locales)) {
                    $this->set('error', t('Please specify at least one language'));
                } else {
                    if (count($locales) > static::MAX_LOCALES) {
                        $this->set('error', t2('Please select up to %d language', 'Please select up to %d languages', static::MAX_LOCALES));
                    } else {
                        $this->deliverPackages($locales, $this->app->make('community_translation/package')->findBy(['packageHandle' => '']));
                    }
                }
            }
        }
    }

    public function packages()
    {
        $hh = $this->app->make('helper/html');
        $this->addHeaderItem($hh->css('translate.css', 'community_translation'));
        $this->set('section', 'packages');
        $this->requireAsset('select2');
        $token = $this->app->make('helper/validation/token');
        $this->set('token', $token);
        $this->deliverLocales();
        if ($this->isPost()) {
            if (!$token->validate('comtra_packages')) {
                $this->set('error', $token->getErrorMessage());
            } else {
                $locales = $this->getPostedLocales();
                if (empty($locales)) {
                    $this->set('error', t('Please specify at least one language'));
                } else {
                    $handle = $this->post('package');
                    $handle = is_string($handle) ? trim($handle) : '';
                    if ($handle === '') {
                        $this->set('error', t('Please specify the package handle'));
                    } else {
                        $this->set('searchHandle', $handle);
                        if (count($locales) > static::MAX_LOCALES) {
                            $this->set('error', t2('Please select up to %d language', 'Please select up to %d languages', static::MAX_LOCALES));
                        } else {
                            $repo = $this->app->make('community_translation/package');
                            $pickedPackage = $this->post('pickedpackage');
                            if (is_string($pickedPackage) && $pickedPackage !== '') {
                                $packages = $repo->findBy(['packageHandle' => $pickedPackage]);
                            } else {
                                $packages = [];
                            }
                            if (!empty($packages)) {
                                $this->deliverPackages($locales, $packages);
                            } else {
                                $foundPackageHandles = [];
                                foreach ($repo->createQueryBuilder('p')
                                    ->where('p.packageHandle LIKE :handle')
                                    ->setParameter('handle', '%' . $handle . '%')
                                    ->getQuery()
                                    ->getResult() as $package) {
                                    $foundPackageHandles[$package->getHandle()] = true;
                                }
                                $foundPackageHandles = array_keys($foundPackageHandles);
                                sort($foundPackageHandles);
                                if (count($foundPackageHandles) === 1) {
                                    $this->deliverPackages($locales, $repo->findBy(['packageHandle' => $foundPackageHandles[0]]));
                                } else {
                                    $this->set('foundPackages', array_values($foundPackageHandles));
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
