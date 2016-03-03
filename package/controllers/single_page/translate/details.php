<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Translate;

use Concrete\Core\Page\Controller\PageController;
use Concrete\Package\CommunityTranslation\Src\Stats\Stats;
use Concrete\Package\CommunityTranslation\Src\Package\Package;

class Details extends PageController
{
    public function view($prefixedPackageHandle = '', $packageVersion = '', $localeID = '')
    {
        $package = null;
        $locale = null;
        if (is_string($prefixedPackageHandle) && strpos($prefixedPackageHandle, 'pkg_') === 0) {
            $packageHandle = trim(substr($prefixedPackageHandle, strlen('pkg_')));
            if (is_string($packageVersion) && ($packageVersion = trim($packageVersion)) !== '') {
                $package = $this->app->make('community_translation/package')->findOneBy(array('pHandle' => $packageHandle, 'pVersion' => $packageVersion));
                if ($package !== null) {
                    if (is_string($localeID) && ($localeID = trim($localeID)) !== '') {
                        $locale = $this->app->make('community_translation/locale')->findOneBy(array('lID' => $localeID, 'lIsSource' => false, 'lIsApproved' => true));
                    }
                }
            }
        }
        if ($package === null || $locale === null) {
            $this->redirect('/translate');
        }
        $allVersions = $this->app->make('community_translation/package')->findBy(array('pHandle' => $package->getHandle()));
        usort($allVersions, function(Package $a, Package $b) {
            $devA = strpos($a->getVersion(), Package::DEV_PREFIX) === 0;
            $devB = strpos($b->getVersion(), Package::DEV_PREFIX) === 0;
            if ($devA === $devB) {
                return version_compare($b->getVersion(), $a->getVersion());
            } elseif ($devA) {
                return -1;
            } else {
                return 1;
            }
        });
        $this->set('allVersions', $allVersions);
        $hh = $this->app->make('helper/html');
        $this->addHeaderItem($hh->css('translate.css', 'community_translation'));        
        $locales = $this->app->make('community_translation/locale')->getApprovedLocales();
        $this->set('translatedThreshold', \Package::getByHandle('community_translation')->getFileConfig()->get('options.translatedThreshold', 90));
        $this->set('dh', $this->app->make('helper/date'));
        $this->set('locales', $locales);
        $this->set('package', $package);
        $this->set('locale', $locale);
        $stats = $this->app->make('community_translation/stats')->get($package, $locales);
        usort($stats, function (Stats $a, Stats $b) {
           $delta = $b->getTranslated() - $a->getTranslated();
           if ($delta == 0) {
               $delta = strcasecmp($a->getLocale()->getDisplayName(), $b->getLocale()->getDisplayName());
           }
           return $delta;
        });
        $this->set('stats', $stats);
    }
}
