<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

use Concrete\Package\CommunityTranslation\Src\Package\Package;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Concrete\Core\Application\Application;
use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Package\CommunityTranslation\Src\Exception;

class Stats implements ApplicationAwareInterface
{
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
     * Get the latest version of a translated package.
     *
     * @return string|null
     */
    public function getLatestPackageVersion($packageOrHandle)
    {
        if ($packageOrHandle instanceof Package) {
            $handle = $packageOrHandle->getHandle;
        } else {
            $handle = (string) $packageOrHandle;
        }
        $result = null;
        foreach ($this->app->make('community_translation/package')->findBy(array('pHandle' => $handle)) as $package) {
            $v = $package->getVersion();
            if (strpos($v, 'dev-') !== 0) {
                if ($result === null) {
                    $result = $v;
                } elseif (version_compare($v, $result) > 0) {
                    $result = $v;
                }
            }
        }

        return $result;
    }

    /**
     * Get the progress statistics about a package.
     * 
     * @param Package|array $packageOrHandleVersion The package for which you want the translations (a Package instance of an array with handle and version)
     * @param Locale|Locale[]|string|string[] $locales
     *
     * @throws Exception
     */
    public function getProgressStats($packageOrHandleVersion, $locales)
    {
        if ($packageOrHandleVersion instanceof Package) {
            $package = $packageOrHandleVersion;
        } elseif (is_array($packageOrHandleVersion) && isset($packageOrHandleVersion['handle']) && isset($packageOrHandleVersion['version'])) {
            $package = $this->app->make('community_translation/package')->findOneBy(array(
                'pHandle' => $packageOrHandleVersion['handle'],
                'pVersion' => $packageOrHandleVersion['version'],
            ));
        } elseif (is_array($packageOrHandleVersion) && isset($packageOrHandleVersion[0]) && isset($packageOrHandleVersion[1])) {
            $package = $this->app->make('community_translation/package')->findOneBy(array(
                'pHandle' => $packageOrHandleVersion[0],
                'pVersion' => $packageOrHandleVersion[1],
            ));
        } else {
            $package = null;
        }
        if ($package === null) {
            throw new Exception(t('Invalid translated package specified'));
        }
        $cache = $this->app->make('cache/expensive');
        $cachedLocales = array();
        $uncachedLocales = array();
        $cacheItems = array();
        foreach (((array) $locales) as $l) {
            if ($l) {
                if ($l instanceof Locale) {
                    $locale = $l;
                } else {
                    if ($localeRepo === null) {
                        $localeRepo = $this->app->make('community_translation/locale');
                    }
                    $locale = $l ? $localeRepo->find($l) : null;
                }
            } else {
                $locale = null;
            }
            if ($locale === null) {
                throw new Exception(t('Invalid locale specified'));
            }
            $cacheKey = 'community_translation/'.$locale->getID().'/'.(($package->getHandle() === '') ? '_' : $package->getHandle());
            $cacheItems[$locale->getID()] = $cache->getItem($cacheKey);
            if ($cacheItems[$locale->getID()]->isMiss()) {
                $uncachedLocales[] = $locale;
            } else {
                $cachedLocales[] = $locale;
            }
        }
        if (empty($cachedLocales) && empty($uncachedLocales)) {
            throw new Exception(t('No locale has been specified'));
        }
        $result = array();
        foreach ($cachedLocales as $locale) {
            $result[$locale->getID()] = $cacheItems[$locale->getID()]->get();
        }
        if (!empty($uncachedLocales)) {
            $total = $this->app->make('community_translation/translatable/place')
                ->createQueryBuilder('p')
                    ->select('count(p.tpTranslatable)')
                    ->where('p.tpPackage = :package')
                    ->setParameter('package', $package)
                    ->getQuery()
                        ->getSingleScalarResult()
            ;
            $total = empty($total) ? 0 : (int) $total;
            foreach ($uncachedLocales as $locale) {
                $result[$locale->getID()] = array(
                    'total' => $total,
                    'translated' => 0,
                    'untranslated' => 0,
                    'realPercentage' => 0,
                    'shownPercentage' => 0,
                );
            }
            if ($total > 0) {
                $q = array($package->getID());
                $w = array();
                foreach ($uncachedLocales as $locale) {
                    $w[] = 't.tLocale = ?';
                    $q[] = $locale->getID();
                }
                $rs = $this->app->make('community_translation/em')->getConnection()->executeQuery(
                    '
                        select
                            t.tLocale,
                            count(t.tTranslatable) as translated
                        from
                            TranslatablePlaces as p
                            inner join Translations as t on p.tpTranslatable = t.tTranslatable and 1 = t.tCurrent
                        where
                            p.tpPackage = ?
                            and ('.implode(' or ', $w).')
                        group by
                            t.tLocale
                    ',
                    $q
                );
                while (($row = $rs->fetch()) !== false) {
                    if (!empty($row['translated'])) {
                        $translated = (int) $row['translated'];
                        $result[$row['tLocale']]['translated'] = $translated;
                        $result[$row['tLocale']]['untranslated'] = $total - $translated;
                        if ($translated === $total) {
                            $realPerc = 100.0;
                            $shownPercentage = 100;
                        } else {
                            $realPerc = $translated * 100.0 / $total;
                            $shownPercentage = max(1, min(99, (int) round($realPerc)));
                        }
                        $result[$row['tLocale']]['realPercentage'] = $realPerc;
                        $result[$row['tLocale']]['shownPercentage'] = $shownPercentage;
                    }
                }
                $rs->closeCursor();
            }
            foreach ($uncachedLocales as $locale) {
                $cacheItems[$locale->getID()]->set($result[$locale->getID()]);
            }
        }

        return $result;
    }
}
