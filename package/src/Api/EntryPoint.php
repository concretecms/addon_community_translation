<?php
namespace Concrete\Package\CommunityTranslation\Src\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Concrete\Package\CommunityTranslation\Src\UserException;
use Concrete\Package\CommunityTranslation\Src\Service\Parser\Parser;
use Exception;
use DateTime;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EntryPoint extends \Concrete\Core\Controller\AbstractController
{
    /**
     * @var UserControl|null
     */
    protected $userControl = null;

    /**
     * @param UserControl $userControl
     */
    public function setUserControl(UserControl $userControl)
    {
        $this->userControl = $userControl;
    }

    /**
     * @return UserControl
     */
    public function getUserControl()
    {
        if ($this->userControl === null) {
            $this->userControl = $this->app->make('Concrete\Package\CommunityTranslation\Src\Api\UserControl');
            $this->userControl->setRequest($this->request);
        }

        return $this->userControl;
    }

    /**
     * Build an error response.
     *
     * @param string|Exception $error
     * @param int $code
     *
     * @return Response
     */
    protected function buildErrorResponse($error, $code = null)
    {
        if ($code !== null && (!is_int($code) || $code < 400)) {
            $code = null;
        }
        if (is_object($error)) {
            if ($error instanceof AccessDeniedException) {
                $error = $error->getMessage();
                if ($code === null) {
                    $code = 401;
                }
            } elseif ($error instanceof UserException) {
                $error = $error->getMessage();
            } elseif ($error instanceof Exception) {
                $error = 'Unspecified error';
            }
        }
        if ($code === null) {
            $code = 500;
        }

        return Response::create($error, $code, array('Content-Type' => 'text/plain; charset=UTF-8'));
    }

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

    /**
     * Check the access to a specific group of API functions.
     *
     * @param string $configKey 'stats', 'download', 'import_packages', ...
     */
    protected function checkAccess($configKey)
    {
        $config = \Package::getByHandle('community_translation')->getFileConfig();
        $this->getUserControl()->checkRequest($config->get('options.api.access.'.$configKey));
    }

    /**
     * @example http://www.example.com/api/locales/
     */
    public function getApprovedLocales()
    {
        try {
            $this->checkAccess('stats');
            $locales = $this->app->make('community_translation/locale')->getApprovedLocales();

            return JsonResponse::create($this->localesToArray($locales));
        } catch (Exception $x) {
            return $this->buildErrorResponse($x);
        }
    }

    /**
     * @example http://www.example.com/api/locales//dev-5.7/90/
     */
    public function getLocalesForPackage($packageHandle, $packageVersion, $minimumLevel)
    {
        try {
            $this->checkAccess('stats');
            $package = $this->app->make('community_translation/package')->findOneBy(array(
                'pHandle' => (string) $packageHandle,
                'pVersion' => (string) $packageVersion,
            ));
            if ($package === null) {
                return $this->buildErrorResponse('Unable to find the specified package', 404);
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
                                $item['total'] = $stat->getTotal();
                                $item['translated'] = $stat->getTranslated();
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
        } catch (Exception $x) {
            return $this->buildErrorResponse($x);
        }
    }

    /**
     * @example http://www.example.com/api/packages/
     */
    public function getAvailablePackageHandles()
    {
        try {
            $this->checkAccess('stats');
            $em = $this->app->make('community_translation/em');
            $handles = $em->getConnection()->executeQuery('select distinct pHandle from TranslatedPackages')->fetchAll(\PDO::FETCH_COLUMN);

            return JsonResponse::create($handles);
        } catch (Exception $x) {
            return $this->buildErrorResponse($x);
        }
    }

    /**
     * @example http://www.example.com/api/package//versions/
     */
    public function getAvailablePackageVersions($packageHandle)
    {
        try {
            $this->checkAccess('stats');
            $em = $this->app->make('community_translation/em');
            $handles = $em->getConnection()->executeQuery('select distinct pVersion from TranslatedPackages where pHandle = ?', array((string) $packageHandle))->fetchAll(\PDO::FETCH_COLUMN);
            if (empty($handles)) {
                return $this->buildErrorResponse('Unable to find the specified package', 404);
            }

            return JsonResponse::create($handles);
        } catch (Exception $x) {
            return $this->buildErrorResponse($x);
        }
    }

    /**
     * @example http://www.example.com/api/po//dev-5.7/it_IT/
     */
    public function getPackagePo($packageHandle, $packageVersion, $localeID)
    {
        try {
            $this->checkAccess('download');

            return $this->getPackageTranslations($packageHandle, $packageVersion, $localeID, false);
        } catch (Exception $x) {
            return $this->buildErrorResponse($x);
        }
    }

    /**
     * @example http://www.example.com/api/mo//dev-5.7/it_IT/
     */
    public function getPackageMo($packageHandle, $packageVersion, $localeID)
    {
        try {
            $this->checkAccess('download');

            return $this->getPackageTranslations($packageHandle, $packageVersion, $localeID, true);
        } catch (Exception $x) {
            return $this->buildErrorResponse($x);
        }
    }

    protected function getPackageTranslations($packageHandle, $packageVersion, $localeID, $compiled)
    {
        $package = $this->app->make('community_translation/package')->findOneBy(array('pHandle' => $packageHandle, 'pVersion' => $packageVersion));
        if ($package === null) {
            return $this->buildErrorResponse('Unable to find the specified package', 404);
        }
        $locale = $this->app->make('community_translation/locale')->findApproved($localeID);
        if ($locale === null) {
            return $this->buildErrorResponse('Unable to find the specified locale', 404);
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

    public function importPackageTranslatable()
    {
        try {
            $this->checkAccess('import_packages');
            $packageHandle = $this->post('handle');
            $packageHandle = is_string($packageHandle) ? trim($packageHandle) : '';
            if ($packageHandle === '') {
                return $this->buildErrorResponse('Package handle not specified', 400);
            }
            $packageVersion = $this->post('version');
            $packageVersion = is_string($packageVersion) ? trim($packageVersion) : '';
            if ($packageVersion === '') {
                return $this->buildErrorResponse('Package version not specified', 400);
            }
            $archive = $this->request->files->get('archive');
            if ($archive === null) {
                return $this->buildErrorResponse('Package archive not received', 400);
            }
            if (!$archive->isValid()) {
                return $this->buildErrorResponse(sprintf('Package archive not correctly received: %s', $archive->getErrorMessage()), 400);
            }
            $parsed = $this->app->make('community_translation/parser')->parseZip($archive->getPathname(), 'packages/'.$packageHandle, Parser::GETTEXT_NONE);
            $pot = ($parsed === null) ? null : $parsed->getPot(false);
            if ($pot === null || count($pot) === 0) {
                return $this->buildErrorResponse('No translatable strings found', 406);
            }
            $changed = $this->app->make('community_translation/translatable/importer')->importTranslations($pot, $packageHandle, $packageVersion);

            return JsonResponse::create(
                array('changed' => $changed),
                200
            );
        } catch (Exception $x) {
            return $this->buildErrorResponse($x);
        }
    }

    public function updatePackageTranslations()
    {
        try {
            $this->checkAccess('update_package_translations');
            $packageHandle = $this->post('handle');
            $packageHandle = is_string($packageHandle) ? trim($packageHandle) : '';
            if ($packageHandle === '') {
                return $this->buildErrorResponse('Package handle not specified', 400);
            }
            $packageVersion = $this->post('version');
            $packageVersion = is_string($packageVersion) ? trim($packageVersion) : '';
            if ($packageVersion === '') {
                return $this->buildErrorResponse('Package version not specified', 400);
            }
            $archive = $this->request->files->get('archive');
            if ($archive === null) {
                return $this->buildErrorResponse('Package archive not received', 400);
            }
            if (!$archive->isValid()) {
                return $this->buildErrorResponse(sprintf('Package archive not correctly received: %s', $archive->getErrorMessage()), 400);
            }
            $localesToInclude = array();
            $package = $this->app->make('community_translation/package')->findOneBy(array('pHandle' => $packageHandle, 'pVersion' => $packageVersion));
            if ($package === null) {
                return $this->buildErrorResponse('Unable to find the specified package version', 404);
            }
            $allLocales = $this->app->make('community_translation/locale')->getApprovedLocales();
            $threshold = (int) \Package::getByHandle('community_translation')->getFileConfig()->get('options.translatedThreshold', 90);
            $statsList = $this->app->make('community_translation/stats')->get(array($packageHandle, $packageVersion), $allLocales);
            $usedLocaleStats = array();
            foreach ($statsList as $stats) {
                if ($stats->getLastUpdated() !== null && $stats->getPercentage() >= $threshold) {
                    $usedLocaleStats[] = $stats;
                }
            }
            $updated = false;
            if (!empty($usedLocaleStats)) {
                $unzipped = $this->app->make('community_translation/decompressed_package', array($archive->getPathname()));
                $unzipped->extract();
                $parsed = $this->app->make('community_translation/parser')->parseDirectory(
                    $unzipped->getExtractedWorkDir(),
                    'packages/'.$packageHandle,
                    Parser::GETTEXT_MO | Parser::GETTEXT_PO
                );
                $exporter = $this->app->make('community_translation/translation/exporter');
                \Gettext\Generators\Mo::$includeEmptyTranslations = true;
                foreach ($usedLocaleStats as $stats) {
                    $localTranslationsDate = max($stats->getLastUpdated(), $package->getUpdatedOn());
                    $packageTranslationsDate = null;
                    $packageTranslations = $parsed->getPo($stats->getLocale());
                    if ($packageTranslations !== null) {
                        $dt = $packageTranslations->getHeader('PO-Revision-Date');
                        if ($dt !== null) {
                            try {
                                $packageTranslationsDate = new DateTime($dt);
                            } catch (Exception $foo) {
                            }
                        }
                    }
                    if ($packageTranslationsDate !== null && $packageTranslationsDate >= $localTranslationsDate) {
                        continue;
                    }
                    $localTranslations = $exporter->forPackage($package, $stats->getLocale());
                    $dir = $unzipped->getExtractedWorkDir().'/languages/'.$stats->getLocale()->getID().'/LC_MESSAGES';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0777, true);
                        if (!is_dir($dir)) {
                            return $this->buildErrorResponse(sprintf('Failed to create directory: %s', $dir));
                        }
                    }
                    $localTranslations->toMoFile($dir.'/messages.mo');
                    $updated = true;
                }
            }
            if (!$updated) {
                unset($unzipped);

                return Response::create(
                    '',
                    304,
                    array(
                        'Content-Length' => '0',
                    )
                );
            }
            $unzipped->repack();
            unset($unzipped);
            $fileSize = @filesize($archive->getPathname());
            if ($fileSize === false || $fileSize <= 0) {
                throw new UserException('Failed to retrieve size of re-packed archive');
            }
            $response = BinaryFileResponse::create(
                $archive->getPathname(),
                201,
                array(
                    'Content-Type' => 'application/zip',
                    'Content-Length' => $fileSize,
                )
            );
            $response->prepare(\Request::getInstance());

            return $response;
        } catch (Exception $x) {
            try {
                if (isset($unzipped)) {
                    unset($unzipped);
                }
            } catch (Exception $foo) {
            }

            return $this->buildErrorResponse($x);
        }
    }

    public function recentPackagesUpdated()
    {
        try {
            $this->checkAccess('stats');
            $sinceStr = $this->request->get('since');
            $sinceStr = is_string($sinceStr) ? trim($sinceStr) : '';
            if ($sinceStr === '') {
                throw new UserException("Missing 'since' parameter in query string");
            }
            $since = null;
            $ts = preg_match('/^[0-9]{9,11}$/', $sinceStr) ? @intval($sinceStr, 10) : 0;
            if ($ts > 0) {
                $since = @DateTime::createFromFormat('U', $ts);
                if ($since && ((int) $since->format('Y')) < 1990) {
                    $since = null;
                }
            }
            if ($since === null) {
                $sample = new DateTime();
                $sample->modify('-2 days');
                throw new UserException("The 'since' parameter should be a reasonable Unix timestamp (example: ".$sample->format('U').")");
            }
            $em = $this->app->make('community_translation/em');
            $rs = $em->getConnection()->executeQuery(
                "
                    select distinct
                        TranslatedPackages.pHandle as handle,
                        TranslatedPackages.pVersion as version
                    from
                        Translations
                        inner join TranslatablePlaces on Translations.tTranslatable = TranslatablePlaces.tpTranslatable
                        inner join TranslatedPackages on TranslatablePlaces.tpPackage = TranslatedPackages.pID
                    where
                        TranslatedPackages.pHandle != ''
                        and
                        (
                            TranslatedPackages.pUpdatedOn >= :date
                            or
                            (
                                Translations.tCurrent = 1
                                and Translations.tCurrentSince >= :date
                            )
                        )
                ",
                array(
                    'date' => $this->app->make('date')->toDB($since),
                )
            );
            $result = $rs->fetchAll(\PDO::FETCH_ASSOC);
            $rs->closeCursor();

            return JsonResponse::create(
                $result
            );
        } catch (Exception $x) {
            return $this->buildErrorResponse($x);
        }
    }
}
