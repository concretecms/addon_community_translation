<?php
namespace CommunityTranslation\Api;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Parser\Parser;
use CommunityTranslation\Parser\ParserInterface;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package\Version as VersionRepository;
use CommunityTranslation\Repository\Stats as StatsRepository;
use CommunityTranslation\Service\DecompressedPackage;
use CommunityTranslation\UserException;
use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Http\ResponseFactory;
use DateTime;
use DateTimeZone;
use Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EntryPoint extends AbstractController
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
            $this->userControl = $this->app->make(UserControl::class, ['request' => $this->request]);
        }

        return $this->userControl;
    }

    /**
     * @var ResponseFactory|null
     */
    protected $responseFactory = null;

    /**
     * @param ResponseFactory $userControl
     */
    public function setResponseFactory(ResponseFactory $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    /**
     * @return ResponseFactory
     */
    public function getResponseFactory()
    {
        if ($this->responseFactory === null) {
            $this->responseFactory = $this->app->make(ResponseFactory::class);
        }

        return $this->responseFactory;
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

        return $this->getResponseFactory()->create(
            $error,
            $code,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    /**
     * @param LocaleEntity[]|LocaleEntity $locale
     */
    protected function localesToArray($locales, $cb = null)
    {
        if (is_array($locales)) {
            $single = false;
        } else {
            $single = true;
            $locales = [$locales];
        }
        $list = [];
        foreach ($locales as $locale) {
            $item = [
                'id' => $locale->getID(),
                'name' => $locale->getName(),
            ];
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
     * @param string $configKey 'stats', 'download', 'importPackages', ...
     */
    protected function checkAccess($configKey)
    {
        $config = $this->app->make('community_translation/config');
        $this->getUserControl()->checkRequest($config->get('options.api.access.' . $configKey));
    }

    /**
     * @example http://www.example.com/api/locales/
     */
    public function getApprovedLocales()
    {
        try {
            $this->checkAccess('stats');
            $locales = $this->app->make(LocaleRepository::class)->getApprovedLocales();

            return $this->getResponseFactory()->json($this->localesToArray($locales));
        } catch (Exception $x) {
            return $this->buildErrorResponse($x);
        }
    }

    /**
     * @example http://www.example.com/api/locales/concrete5/dev-8/90/
     */
    public function getLocalesForPackage($packageHandle, $packageVersion, $minimumLevel)
    {
        try {
            $this->checkAccess('stats');
            $version = $this->app->make(VersionRepository::class)->findByHandleAndVersion($packageHandle, $packageVersion);
            if ($version === null) {
                return $this->buildErrorResponse('Unable to find the specified package', 404);
            }
            $minimumLevel = (int) $minimumLevel;
            $result = [];
            $allLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
            $stats = $this->app->make(StatsRepository::class)->get($version, $allLocales);
            $utc = new DateTimeZone('UTC');
            $result = $this->localesToArray(
                $allLocales,
                function (LocaleEntity $locale, array $item) use ($stats, $minimumLevel, $utc) {
                    $result = null;
                    foreach ($stats as $stat) {
                        if ($stat->getLocale() === $locale) {
                            if ($stat->getPercentage() >= $minimumLevel) {
                                $item['total'] = $stat->getTotal();
                                $item['translated'] = $stat->getTranslated();
                                $item['progress'] = $stat->getPercentage(true);
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
            $handles = $em->getConnection()->executeQuery('select distinct pVersion from TranslatedPackages where pHandle = ?', [(string) $packageHandle])->fetchAll(\PDO::FETCH_COLUMN);
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
        $package = $this->app->make('community_translation/package')->findOneBy(['pHandle' => $packageHandle, 'pVersion' => $packageVersion]);
        if ($package === null) {
            return $this->buildErrorResponse('Unable to find the specified package', 404);
        }
        $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
        if ($locale === null) {
            return $this->buildErrorResponse('Unable to find the specified locale', 404);
        }
        $translations = $this->app->make('community_translation/translation/exporter')->forPackage($package, $locale);
        if ($compiled) {
            \Gettext\Generators\Mo::$includeEmptyTranslations = true;
            $data = $translations->toMoString();
            $filename = $locale->getID() . '.mo';
        } else {
            $data = $translations->toPoString();
            $filename = $locale->getID() . '.po';
        }

        return Response::create(
            $data,
            200,
            [
                'Content-Disposition' => 'attachment; filename=' . $filename,
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => strlen($data),
            ]
        );
    }

    public function importPackageTranslatable()
    {
        try {
            $this->checkAccess('importPackages');
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
            $parsed = $this->app->make('community_translation/parser')->parseZip($archive->getPathname(), 'packages/' . $packageHandle, ParserInterface::DICTIONARY_NONE);
            $pot = ($parsed === null) ? null : $parsed->getSourceStrings(false);
            if ($pot === null || count($pot) === 0) {
                return $this->buildErrorResponse('No translatable strings found', 406);
            }
            $changed = $this->app->make('community_translation/translatable/importer')->importTranslations($pot, $packageHandle, $packageVersion);

            return JsonResponse::create(
                ['changed' => $changed],
                200
            );
        } catch (Exception $x) {
            return $this->buildErrorResponse($x);
        }
    }

    public function updatePackageTranslations()
    {
        try {
            $this->checkAccess('updatePackageTranslations');
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
            $keepOldTranslations = $this->post('keepOldTranslations');
            $keepOldTranslations = empty($keepOldTranslations) ? false : true;
            $localesToInclude = [];
            $package = $this->app->make('community_translation/package')->findOneBy(['pHandle' => $packageHandle, 'pVersion' => $packageVersion]);
            if ($package === null) {
                return $this->buildErrorResponse('Unable to find the specified package version', 404);
            }
            $allLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
            $threshold = (int) $this->app->make('community_translation/config')->get('options.translatedThreshold', 90);
            $statsList = $this->app->make('community_translation/stats')->get([$packageHandle, $packageVersion], $allLocales);
            $usedLocaleStats = [];
            foreach ($statsList as $stats) {
                if ($stats->getLastUpdated() !== null && $stats->getPercentage() >= $threshold) {
                    $usedLocaleStats[] = $stats;
                }
            }
            $updated = false;
            if (!empty($usedLocaleStats)) {
                $unzipped = $this->app->make(DecompressedPackage::class, ['packageArchive' => $archive->getPathname(), 'volatileDirectory' => null]);
                $unzipped->extract();
                $parsed = $this->app->make('community_translation/parser')->parseDirectory(
                    $unzipped->getExtractedWorkDir(),
                    'packages/' . $packageHandle,
                    ParserInterface::DICTIONARY_LOCALIZED_COMPILED || ParserInterface::DICTIONARY_LOCALIZED_SOURCE
                );
                $exporter = $this->app->make('community_translation/translation/exporter');
                \Gettext\Generators\Mo::$includeEmptyTranslations = true;
                foreach ($usedLocaleStats as $stats) {
                    $localTranslationsDate = max($stats->getLastUpdated(), $package->getUpdatedOn());
                    $packageTranslationsDate = null;
                    $packageTranslations = $parsed->getTranslations($stats->getLocale());
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
                    $dir = $unzipped->getExtractedWorkDir() . '/languages/' . $stats->getLocale()->getID() . '/LC_MESSAGES';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0777, true);
                        if (!is_dir($dir)) {
                            return $this->buildErrorResponse(sprintf('Failed to create directory: %s', $dir));
                        }
                    } elseif ($keepOldTranslations) {
                        if (is_file($dir . '/messages.po')) {
                            try {
                                $existing = \Gettext\Translations::fromPoFile($dir . '/messages.po');
                                if (count($existing) > 0) {
                                    $localTranslations->mergeWith($existing, \Gettext\Translations::MERGE_ADD);
                                }
                            } catch (\Exception $foo) {
                            }
                        }
                        if (is_file($dir . '/messages.mo')) {
                            try {
                                $existing = \Gettext\Translations::fromMoFile($dir . '/messages.mo');
                                if (count($existing) > 0) {
                                    $localTranslations->mergeWith($existing, \Gettext\Translations::MERGE_ADD);
                                }
                            } catch (\Exception $foo) {
                            }
                        }
                    }
                    $localTranslations->toMoFile($dir . '/messages.mo');
                    $updated = true;
                }
            }
            if (!$updated) {
                unset($unzipped);

                return Response::create(
                    '',
                    304,
                    [
                        'Content-Length' => '0',
                    ]
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
                [
                    'Content-Type' => 'application/zip',
                    'Content-Length' => $fileSize,
                ]
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
                throw new UserException("The 'since' parameter should be a reasonable Unix timestamp (example: " . $sample->format('U') . ')');
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
                [
                    'date' => $this->app->make('date')->toDB($since),
                ]
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
