<?php
namespace Concrete\Package\CommunityTranslation\Src\Rest;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;

class Api extends \Concrete\Core\Controller\AbstractController
{
    /**
     * @param Locale[]|Locale $locale
     */
    protected function localesToArray($locales, $cb = null)
    {
        if (is_array($locales)) {
            $single = false;
        } else {
            $single = true;
            $locales = array($locales);
        }
        $list = array();
        foreach ($locales as $locale) {
            $item = array(
                'id' => $locale->getID(),
                'name' => $locale->getName(),
            );
            if ($cb !== null) {
                $item = call_user_func($cb, $locale, $item);
            }
            if ($item !== null) {
                $list[] = $item;
            }
        }

        return $single ? $list[0] : $list;
    }

    public function getApprovedLocales()
    {
        $locales = $this->app->make('community_translation/locale')->getApprovedLocales();

        return JsonResponse::create($this->localesToArray($locales));
    }

    public function getLocalesForPackage($packageHandle, $packageVersion, $minimumLevel)
    {
        $package = $this->app->make('community_translation/package')->findOneBy(array(
            'pHandle' => (string) $packageHandle,
            'pVersion' => (string) $packageVersion,
        ));
        if ($package === null) {
            return JsonResponse::create(
                array('error' => 'Unable to find the specified package'),
                404
            );
        }
        $minimumLevel = (int) $minimumLevel;
        $result = array();
        $allLocales = $this->app->make('community_translation/locale')->getApprovedLocales();
        $stats = $this->app->make('community_translation/stats')->get($package, $allLocales);
        $utc = new \DateTimeZone('UTC');
        $result = $this->localesToArray(
            $allLocales,
            function (Locale $locale, array $item) use ($stats, $minimumLevel, $utc) {
                $result = null;
                foreach ($stats as $stat) {
                    if ($stat->getLocale() === $locale) {
                        if ($stat->getPercentage() >= $minimumLevel) {
                            $item['progress'] = $stat->getPercentage(false);
                            $item['progressShown'] = $stat->getPercentage(true);
                            $dt = $stat->getLastUpdated();
                            if ($dt === null) {
                                $item['updated'] = null;
                            } else {
                                $dt = clone $dt;
                                $dt->setTimezone($utc);
                                $item['updated'] = $dt->format('c');
                            }
                            $result = $item;
                        }
                        break;
                    }
                }

                return $result;
            }
        );

        return JsonResponse::create($result);
    }

    public function getAvailablePackageHandles()
    {
        $em = $this->app->make('community_translation/em');
        $handles = $em->getConnection()->executeQuery('select distinct pHandle from TranslatedPackages')->fetchAll(\PDO::FETCH_COLUMN);

        return JsonResponse::create($handles);
    }

    public function getAvailablePackageVersions($packageHandle)
    {
        $em = $this->app->make('community_translation/em');
        $handles = $em->getConnection()->executeQuery('select distinct pVersion from TranslatedPackages where pHandle = ?', array((string) $packageHandle))->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($handles)) {
            return JsonResponse::create(
                array('error' => 'Unable to find the specified package'),
                404
            );
        }

        return JsonResponse::create($handles);
    }

    public function getPackagePo($packageHandle, $packageVersion, $localeID)
    {
        return $this->getPackageTranslations($packageHandle, $packageVersion, $localeID, false);
    }

    public function getPackageMo($packageHandle, $packageVersion, $localeID)
    {
        return $this->getPackageTranslations($packageHandle, $packageVersion, $localeID, true);
    }

    protected function getPackageTranslations($packageHandle, $packageVersion, $localeID, $compiled)
    {
        $package = $this->app->make('community_translation/package')->findOneBy(array('pHandle' => $packageHandle, 'pVersion' => $packageVersion));
        if ($package === null) {
            return Response::create(
                'Unable to find the specified package',
                404
            );
        }
        $locale = $this->app->make('community_translation/locale')->findApproved($localeID);
        if ($locale === null) {
            return Response::create(
                'Unable to find the specified locale',
                404
            );
        }
        $translations = $this->app->make('community_translation/translation/exporter')->forPackage($package, $locale);
        if ($compiled) {
            \Gettext\Generators\Mo::$includeEmptyTranslations = true;
            $data = $translations->toMoString();
            $filename = $locale->getID().'.mo';
        } else {
            $data = $translations->toPoString();
            $filename = $locale->getID().'.po';
        }

        return Response::create(
            $data,
            200,
            array(
                'Content-Disposition' => 'attachment; filename='.$filename,
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => strlen($data),
            )
        );
    }
}
