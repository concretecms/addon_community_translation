<?php
namespace CommunityTranslation\Api;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\RemotePackage as RemotePackageEntity;
use CommunityTranslation\RemotePackage\Importer as RemotePackageImporter;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Repository\RemotePackage as RemotePackageRepository;
use CommunityTranslation\Repository\Stats as StatsRepository;
use CommunityTranslation\Service\TranslationsFileExporter;
use CommunityTranslation\Service\VersionComparer;
use CommunityTranslation\Translatable\Importer as TranslatableImporter;
use CommunityTranslation\Translation\Exporter as TranslationExporter;
use CommunityTranslation\Translation\Importer as TranslationImporter;
use CommunityTranslation\TranslationsConverter\Provider as TranslationsConverterProvider;
use CommunityTranslation\UserException;
use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Http\ResponseFactory;
use Concrete\Core\Localization\Localization;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EntryPoint extends AbstractController
{
    /**
     * @var Localization|null
     */
    private $localization = null;

    /**
     * @return Localization
     */
    protected function getLocalization()
    {
        if ($this->localization === null) {
            $this->localization = $this->app->make(Localization::class);
        }

        return $this->localization;
    }

    /**
     * @var UserControl|null
     */
    private $userControl = null;

    /**
     * @return UserControl
     */
    protected function getUserControl()
    {
        if ($this->userControl === null) {
            $this->userControl = $this->app->make(UserControl::class, ['request' => $this->request]);
        }

        return $this->userControl;
    }

    /**
     * @var ResponseFactory|null
     */
    private $responseFactory = null;

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
     * @param string|Exception|Throwable $error
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
                    $code = Response::HTTP_UNAUTHORIZED;
                }
            } elseif ($error instanceof UserException) {
                if ($error->getCode() >= 400) {
                    $code = $error->getCode();
                }
                $error = $error->getMessage();
            } elseif ($error instanceof Exception || $error instanceof Throwable) {
                $error = t('An unexpected error occurred');
            } elseif (is_callable([$error, '__toString'])) {
                $error = (string) $error;
            } else {
                $error = get_class($error);
            }
        }
        if ($code === null) {
            $code = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        return $this->getResponseFactory()->create(
            $error,
            $code,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]
        );
    }

    /**
     * Check if the request is a JSON request and returns the posted parameters.
     *
     * @throws UserException
     *
     * @return array
     */
    protected function getRequestJson()
    {
        if ($this->request->getContentType() !== 'json') {
            throw new UserException(t('Invalid request Content-Type: %s', $this->request->headers->get('Content-Type', '')), Response::HTTP_NOT_ACCEPTABLE);
        }
        $contentBody = $this->request->getContent();
        $contentJson = @json_decode($contentBody, true);
        if (!is_array($contentJson)) {
            throw new UserException(t('Failed to parse the request body as JSON'), Response::HTTP_NOT_ACCEPTABLE);
        }

        return $contentJson;
    }

    /**
     * Build an JSON response.
     *
     * @param mixed $data
     *
     * @return Response
     */
    protected function buildJsonResponse($data)
    {
        return $this->getResponseFactory()->json($data);
    }

    /**
     * @param PackageEntity $package
     * @param string $packageVersionID
     * @param bool $isBastMatch [out]
     *
     * @return \CommunityTranslation\Entity\Package\Version|null
     */
    protected function getPackageVersion(PackageEntity $package, $packageVersion, &$isBastMatch = null)
    {
        if ($packageVersion === 'best-match-version') {
            $isBastMatch = true;
            $result = null;
            if ($this->request->query->has('v')) {
                $v = $this->request->query->get('v');
                if (is_string($v) && $v !== '') {
                    $versionComparer = new VersionComparer();
                    $result = $versionComparer->matchPackageVersionEntities($package->getVersions(), $v);
                }
            }
        } else {
            $isBastMatch = false;
            $result = $this->app->make(PackageVersionRepository::class)->findByHandleAndVersion($package->getHandle(), $packageVersion);
        }

        return $result;
    }

    /**
     * @var string
     */
    private $requestedResultsLocale;

    protected function start()
    {
        $this->requestedResultsLocale = (string) $this->request->query->get('rl', '');
        if ($this->requestedResultsLocale !== '') {
            $localization = $this->getLocalization();
            if ($this->requestedResultsLocale === $localization->getLocale()) {
                $this->requestedResultsLocale = '';
            } else {
                $localization->setContextLocale('communityTranslationAPI', $this->requestedResultsLocale);
                $localization->pushActiveContext('communityTranslationAPI');
            }
        }
    }

    /**
     * @param Response $response
     *
     * @return Response
     */
    protected function finish(Response $response)
    {
        if ($this->requestedResultsLocale !== '') {
            $this->getLocalization()->popActiveContext();
        }
        if (!$response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'DENY');
        }
        if (!$response->headers->has('Access-Control-Allow-Origin')) {
            $config = $this->app->make('community_translation/config');
            $response->headers->set('Access-Control-Allow-Origin', (string) $config->get('options.api.accessControlAllowOrigin'));
        }

        return $response;
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
                'nameLocalized' => $locale->getDisplayName(),
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
     * Get the current rate limit status.
     *
     * @return Response
     *
     * @example http://www.example.com/api/rate-limit/
     */
    public function getRateLimit()
    {
        $this->start();
        try {
            $this->getUserControl()->checkRateLimit();
            $this->getUserControl()->checkGenericAccess(__FUNCTION__);
            $rateLimit = $this->getUserControl()->getRateLimit();
            if ($rateLimit === null) {
                $result = $this->buildJsonResponse(null);
            } else {
                list($maxRequests, $timeWindow) = $rateLimit;
                $visits = $this->getUserControl()->getVisitsCountFromCurrentIP($timeWindow);
                $result = $this->buildJsonResponse([
                    'maxRequests' => $maxRequests,
                    'timeWindow' => $timeWindow,
                    'currentCounter' => $visits,
                ]);
            }
        } catch (Exception $x) {
            $result = $this->buildErrorResponse($x);
        } catch (Throwable $x) {
            $result = $this->buildErrorResponse($x);
        }

        return $this->finish($result);
    }

    /**
     * Get the list of approved locales.
     *
     * @return Response
     *
     * @example http://www.example.com/api/locales/
     */
    public function getLocales()
    {
        $this->start();
        try {
            $this->getUserControl()->checkRateLimit();
            $this->getUserControl()->checkGenericAccess(__FUNCTION__);
            $locales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
            $result = $this->buildJsonResponse($this->localesToArray($locales));
        } catch (Exception $x) {
            $result = $this->buildErrorResponse($x);
        } catch (Throwable $x) {
            $result = $this->buildErrorResponse($x);
        }

        return $this->finish($result);
    }

    /**
     * Get the list of packages.
     *
     * @return Response
     *
     * @example http://www.example.com/api/packages/
     */
    public function getPackages()
    {
        $this->start();
        try {
            $this->getUserControl()->checkRateLimit();
            $this->getUserControl()->checkGenericAccess(__FUNCTION__);
            $repo = $this->app->make(PackageRepository::class);
            $packages = $repo->createQueryBuilder('p')
                ->select('p.handle, p.name')
                ->getQuery()->getArrayResult();
            $result = $this->buildJsonResponse($packages);
        } catch (Exception $x) {
            $result = $this->buildErrorResponse($x);
        } catch (Throwable $x) {
            $result = $this->buildErrorResponse($x);
        }

        return $this->finish($result);
    }

    /**
     * Get the list of versions of a package.
     *
     * @return Response
     *
     * @example http://www.example.com/api/package/concrete5/versions/
     */
    public function getPackageVersions($packageHandle)
    {
        $this->start();
        try {
            $this->getUserControl()->checkRateLimit();
            $this->getUserControl()->checkGenericAccess(__FUNCTION__);
            $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $packageHandle]);
            if ($package === null) {
                throw new UserException(t('Unable to find the specified package'), Response::HTTP_NOT_FOUND);
            }
            $versions = [];
            foreach ($package->getSortedVersions(true, null) as $packageVersion) {
                $versions[] = $packageVersion->getVersion();
            }
            $result = $this->buildJsonResponse($versions);
        } catch (Exception $x) {
            $result = $this->buildErrorResponse($x);
        } catch (Throwable $x) {
            $result = $this->buildErrorResponse($x);
        }

        return $this->finish($result);
    }

    /**
     * Get some stats about the translations of a package version.
     *
     * @return Response
     *
     * @example http://www.example.com/api/package/concrete5/8.2/locales/80/
     * @example http://www.example.com/api/package/concrete5/best-match-version/locales/80/?v=8.2rc1
     */
    public function getPackageVersionLocales($packageHandle, $packageVersion, $minimumLevel = null)
    {
        $this->start();
        try {
            $this->getUserControl()->checkRateLimit();
            $accessibleLocales = $this->getUserControl()->checkLocaleAccess(__FUNCTION__);
            $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $packageHandle]);
            if ($package === null) {
                throw new UserException(t('Unable to find the specified package'), Response::HTTP_NOT_FOUND);
            }
            $version = $this->getPackageVersion($package, $packageVersion, $isBestMatch);
            if ($version === null) {
                throw new UserException(t('Unable to find the specified package version'), Response::HTTP_NOT_FOUND);
            }
            $minimumLevel = (int) $minimumLevel;
            $stats = $this->app->make(StatsRepository::class)->get($version, $accessibleLocales);
            $utc = new DateTimeZone('UTC');
            $resultData = $this->localesToArray(
                $accessibleLocales,
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
            if ($isBestMatch) {
                $resultData = [
                    'versionHandle' => $version->getVersion(),
                    'versionName' => $version->getDisplayVersion(),
                    'locales' => $resultData,
                ];
            }

            $result = $this->buildJsonResponse($resultData);
        } catch (Exception $x) {
            $result = $this->buildErrorResponse($x);
        } catch (Throwable $x) {
            $result = $this->buildErrorResponse($x);
        }

        return $this->finish($result);
    }

    /**
     * Get the translations of a package version.
     *
     * @return Response
     *
     * @example http://www.example.com/api/package/concrete5/8.2/translations/it_IT/mo/
     * @example http://www.example.com/api/package/concrete5/best-match-version/translations/it_IT/mo/?v=8.2rc1
     */
    public function getPackageVersionTranslations($packageHandle, $packageVersion, $localeID, $formatHandle)
    {
        $this->start();
        try {
            $this->getUserControl()->checkRateLimit();
            $accessibleLocales = $this->getUserControl()->checkLocaleAccess(__FUNCTION__);
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserException(t('Unable to find the specified locale'), Response::HTTP_NOT_FOUND);
            }
            if (!in_array($locale, $accessibleLocales, true)) {
                throw AccessDeniedException::create(t('Access denied to the specified locale'));
            }
            $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $packageHandle]);
            if ($package === null) {
                throw new UserException(t('Unable to find the specified package'), Response::HTTP_NOT_FOUND);
            }
            $version = $this->getPackageVersion($package, $packageVersion);
            if ($version === null) {
                throw new UserException(t('Unable to find the specified package version'), Response::HTTP_NOT_FOUND);
            }
            $format = $this->app->make(TranslationsConverterProvider::class)->getByHandle($formatHandle);
            if ($format === null) {
                throw new UserException(t('Unable to find the specified translations format'), Response::HTTP_NOT_FOUND);
            }
            $translationsFile = $this->app->make(TranslationsFileExporter::class)->getSerializedTranslationsFile($version, $locale, $format);
            $result = BinaryFileResponse::create(
                // $file
                $translationsFile,
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
        } catch (Exception $x) {
            $result = $this->buildErrorResponse($x);
        } catch (Throwable $x) {
            $result = $this->buildErrorResponse($x);
        }

        return $this->finish($result);
    }

    /**
     * Fill-in translations that we already know.
     *
     * @return Response
     *
     * @example POST a file (field name: file) to http://www.example.com/api/fill-translations/po/
     */
    public function fillTranslations($formatHandle)
    {
        $this->start();
        try {
            $this->getUserControl()->checkRateLimit();
            $this->getUserControl()->checkGenericAccess(__FUNCTION__);
            $format = $this->app->make(TranslationsConverterProvider::class)->getByHandle($formatHandle);
            if ($format === null) {
                throw new UserException(t('Unable to find the specified translations format'), Response::HTTP_NOT_FOUND);
            }
            /* @var \CommunityTranslation\TranslationsConverter\ConverterInterface $format */
            if (!$format->canUnserializeTranslations()) {
                throw new UserException(t('The specified translations format does not support unserialization'), Response::HTTP_NOT_ACCEPTABLE);
            }
            if (!$format->canSerializeTranslations()) {
                throw new UserException(t('The specified translations format does not support serialization'), Response::HTTP_NOT_ACCEPTABLE);
            }
            if (!$format->supportLanguageHeader()) {
                throw new UserException(t('The specified translations format does not support a language header'), Response::HTTP_NOT_ACCEPTABLE);
            }
            $file = $this->request->files->get('file');
            if ($file === null) {
                throw new UserException(t('The file with strings to be translated has not been specified'), Response::HTTP_NOT_ACCEPTABLE);
            }
            if (!$file->isValid()) {
                throw new UserException(t('The file with strings to be translated has not been received correctly: %s', $file->getErrorMessage()), Response::HTTP_NOT_ACCEPTABLE);
            }
            $translations = $format->loadTranslationsFromFile($file->getPathname());
            $localeID = (string) $translations->getLanguage();
            if ($localeID === '') {
                throw new UserException(t('The file with strings to be translated does not specify a language ID'));
            }
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserException(t('The file with strings to be translated specifies an unknown language ID (%s)', $localeID));
            }
            $translationExporter = $this->app->make(TranslationExporter::class);
            /* @var TranslationExporter $translationExporter */
            $translations = $translationExporter->fromPot($translations, $locale);
            $result = $this->getResponseFactory()->create(
                $format->convertTranslationsToString($translations),
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Transfer-Encoding' => 'binary',
                    'Content-Disposition' => 'attachment; filename="translations.' . $format->getFileExtension() . '"',
                ]
                );
        } catch (Exception $x) {
            $result = $this->buildErrorResponse($x);
        } catch (Throwable $x) {
            $result = $this->buildErrorResponse($x);
        }

        return $this->finish($result);
    }

    /**
     * Accept a package version to be imported and queue it for later processing.
     *
     * @return Response
     *
     * @example PUT Request to http://www.example.com/api/import/package/ with this JSON data:
     * {
     *   "package_handle": "...", // Required
     *   "package_version": "...",  // Required
     *   "archive_url": "...", // Required
     *   "package_name": "...", // Optional
     *   "package_url": "...", // Optional
     *   "approved": true/false // Optional
     *   "immediate": true/false // Optional
     * }
     */
    public function importPackage()
    {
        $this->start();
        try {
            $this->getUserControl()->checkRateLimit();
            $this->getUserControl()->checkGenericAccess(__FUNCTION__);
            $args = $this->getRequestJson();
            $package_handle = (isset($args['package_handle']) && is_string($args['package_handle'])) ? trim($args['package_handle']) : '';
            if ($package_handle === '') {
                throw new UserException(t('Missing argument: %s', 'package_handle'), Response::HTTP_NOT_ACCEPTABLE);
            }
            $package_version = (isset($args['package_version']) && is_string($args['package_version'])) ? trim($args['package_version']) : '';
            if ($package_version === '') {
                throw new UserException(t('Missing argument: %s', 'package_version'), Response::HTTP_NOT_ACCEPTABLE);
            }
            $archive_url = (isset($args['archive_url']) && is_string($args['archive_url'])) ? trim($args['archive_url']) : '';
            if ($archive_url === '') {
                throw new UserException(t('Missing argument: %s', 'archive_url'), Response::HTTP_NOT_ACCEPTABLE);
            }

            $entity = RemotePackageEntity::create($package_handle, $package_version, $archive_url);

            if (isset($args['package_name'])) {
                if (!is_string($args['package_name'])) {
                    throw new UserException(t('Invalid type of argument: %s', 'package_name'), Response::HTTP_NOT_ACCEPTABLE);
                }
                $entity->setName(trim($args['package_name']));
            }
            if (isset($args['package_url'])) {
                if (!is_string($args['package_url'])) {
                    throw new UserException(t('Invalid type of argument: %s', 'package_url'), Response::HTTP_NOT_ACCEPTABLE);
                }
                $entity->setUrl(trim($args['package_url']));
            }
            if (isset($args['approved'])) {
                if (!is_bool($args['approved'])) {
                    throw new UserException(t('Invalid type of argument: %s', 'approved'), Response::HTTP_NOT_ACCEPTABLE);
                }
                $entity->setIsApproved($args['approved']);
            }
            if (isset($args['immediate'])) {
                if (!is_bool($args['immediate'])) {
                    throw new UserException(t('Invalid type of argument: %s', 'immediate'), Response::HTTP_NOT_ACCEPTABLE);
                }
                $immediate = $args['immediate'];
            } else {
                $immediate = false;
            }
            $em = $this->app->make(EntityManager::class);
            /* @var EntityManager $em */
            $repo = $this->app->make(RemotePackageRepository::class);
            /* @var RemotePackageRepository $repo */
            $connection = $em->getConnection();
            $connection->beginTransaction();
            try {
                // Remove duplicated packages still to be processed
                $qb = $repo->createQueryBuilder('rp');
                $qb
                    ->delete()
                    ->where('rp.handle = :handle')->setParameter('handle', $entity->getHandle())
                    ->andWhere('rp.version = :version')->setParameter('version', $entity->getVersion())
                    ->andWhere($qb->expr()->isNull('rp.processedOn'))
                    ->getQuery()->execute();

                if ($entity->isApproved()) {
                    // Approve previously queued packages that were'nt approved
                    $qb = $repo->createQueryBuilder('rp');
                    $qb
                        ->update()
                        ->set('rp.approved', true)
                        ->where('rp.handle = :handle')->setParameter('handle', $entity->getHandle())
                        ->andWhere('rp.approved = :approved')->setParameter('approved', false)
                        ->andWhere($qb->expr()->isNull('rp.processedOn'))
                        ->getQuery()->execute();
                }
                if ($immediate === false) {
                    $em->persist($entity);
                    $em->flush($entity);
                }
                $connection->commit();
            } catch (Exception $x) {
                try {
                    $connection->rollBack();
                } catch (Exception $foo) {
                }
                throw $x;
            }
            if ($immediate) {
                if ($entity->isApproved()) {
                    $importer = $this->app->make(RemotePackageImporter::class);
                    /* @var RemotePackageImporter $importer */
                    $importer->import($entity);
                    $result = $this->buildJsonResponse('imported');
                } else {
                    $result = $this->buildJsonResponse('skipped');
                }
            } else {
                $result = $this->buildJsonResponse('queued');
            }
        } catch (Exception $x) {
            $result = $this->buildErrorResponse($x);
        } catch (Throwable $x) {
            $result = $this->buildErrorResponse($x);
        }

        return $this->finish($result);
    }

    /**
     * Set the translatable strings of a package version.
     *
     * @return Response
     *
     * @example http://www.example.com/api/package/concrete5/8.2/translatables/po/
     */
    public function importPackageVersionTranslatables($packageHandle, $packageVersion, $formatHandle)
    {
        $this->start();
        try {
            $this->getUserControl()->checkRateLimit();
            $this->getUserControl()->checkGenericAccess(__FUNCTION__);
            $em = $this->app->make(EntityManager::class);
            $post = $this->request->request;
            $packageName = $post->get('packageName', '');
            if (!is_string($packageName)) {
                $packageName = '';
            }
            $packageVersion = is_string($packageVersion) ? trim($packageVersion) : '';
            if ($packageVersion === '') {
                throw new UserException(t('Package version not specified'), Response::HTTP_NOT_ACCEPTABLE);
            }
            $format = $this->app->make(TranslationsConverterProvider::class)->getByHandle($formatHandle);
            if ($format === null) {
                throw new UserException(t('Unable to find the specified translations format'), 404);
            }
            $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $packageHandle]);
            if ($package === null) {
                $package = PackageEntity::create($packageHandle, $packageName);
                $em->persist($package);
                $em->flush($package);
                $version = null;
            } else {
                if ($packageName !== '' && $package->getName() !== $packageName) {
                    $package->setName($packageName);
                    $em->persist($package);
                    $em->flush($package);
                }
                $version = $this->app->make(PackageVersionRepository::class)->findByOneBy(['package' => $package, 'version' => $packageVersion]);
            }
            if ($version === null) {
                $version = PackageVersionEntity::create($package, $packageVersion);
                $em->persist($version);
                $em->flush($version);
            }
            $file = $this->request->files->get('file');
            if ($file === null) {
                throw new UserException(t('The file with translatable strings has not been specified'), Response::HTTP_NOT_ACCEPTABLE);
            }
            if (!$file->isValid()) {
                throw new UserException(t('The file with translatable strings has not been received correctly: %s', $file->getErrorMessage()), Response::HTTP_NOT_ACCEPTABLE);
            }
            $translations = $format->loadTranslationsFromFile($file->getPathname());
            if (count($t) < 1) {
                throw new UserException(t('No translatable strings found in uploaded file'));
            }
            $importer = $this->app->make(TranslatableImporter::class);
            $changed = $importer->importTranslations($translations, $package->getHandle(), $packageVersion->getVersion());
            $result = $this->buildJsonResponse(['changed' => $changed]);
        } catch (Exception $x) {
            $result = $this->buildErrorResponse($x);
        } catch (Throwable $x) {
            $result = $this->buildErrorResponse($x);
        }

        return $this->finish($result);
    }

    /**
     * Import the translations for a specific locale.
     *
     * @return Response
     *
     * @example http://www.example.com/api/translations/it_IT/po/0/
     */
    public function importTranslations($localeID, $formatHandle, $approve)
    {
        $this->start();
        try {
            $this->getUserControl()->checkRateLimit();
            $approve = $approve ? true : false;
            $accessibleLocales = $this->getUserControl()->checkLocaleAccess(__FUNCTION__ . ($approve ? '_approve' : ''));
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserException(t('Unable to find the specified locale'), Response::HTTP_NOT_FOUND);
            }
            if (!in_array($locale, $accessibleLocales, true)) {
                throw AccessDeniedException::create(t('Access denied to the specified locale'));
            }
            $format = $this->app->make(TranslationsConverterProvider::class)->getByHandle($formatHandle);
            if ($format === null) {
                throw new UserException(t('Unable to find the specified translations format'), 404);
            }
            $file = $this->request->files->get('file');
            if ($file === null) {
                throw new UserException(t('The file with translated strings has not been specified'), Response::HTTP_NOT_ACCEPTABLE);
            }
            if (!$file->isValid()) {
                throw new UserException(t('The file with translated strings has not been received correctly: %s', $file->getErrorMessage()), Response::HTTP_NOT_ACCEPTABLE);
            }
            $translations = $format->loadTranslationsFromFile($file->getPathname());
            if (count($translations) < 1) {
                throw new UserException(t('No translations found in uploaded file'));
            }
            if (!$translations->getLanguage()) {
                throw new UserException(t('The translation file does not contain a language header'));
            }
            if (strcasecmp($translations->getLanguage(), $locale->getID()) !== 0) {
                throw new UserException(t("The translation file is for the '%1\$s' language, not for '%2\$s'", $translations->getLanguage(), $locale->getID()));
            }
            $pf = $t->getPluralForms();
            if ($pf === null) {
                throw new UserException(t('The translation file does not define the plural rules'));
            }
            if ($pf[0] !== $locale->getPluralCount()) {
                throw new UserException(t('The translation file defines %1$s plural forms instead of %2$s', $pf[0], $locale->getPluralCount()));
            }
            $importer = $this->app->make(TranslationImporter::class);
            $me = $this->getUserControl()->getAssociatedUserEntity();
            $imported = $importer->import($translations, $locale, $me, $approve);
            if ($imported->newApprovalNeeded > 0) {
                $this->app->make(NotificationRepository::class)->translationsNeedApproval(
                    $locale,
                    $imported->newApprovalNeeded,
                    $me->getUserID(),
                    null
                );
            }
            $result = $this->buildJsonResponse($imported);
        } catch (Exception $x) {
            $result = $this->buildErrorResponse($x);
        } catch (Throwable $x) {
            $result = $this->buildErrorResponse($x);
        }

        return $this->finish($result);
    }

    /**
     * What to do if the entry point is not recognized.
     *
     * @param string $unrecognizedPath
     *
     * @return Response
     */
    public function unrecognizedCall($unrecognizedPath = '')
    {
        $this->start();

        if ($unrecognizedPath === '') {
            $message = t('Resource not specified');
        } else {
            $message = t(/*i18n: %1$s is a path, %2$s is an HTTP method*/'Unknown resource %1$s for %2$s method', $unrecognizedPath, $this->request->getMethod());
        }
        $result = $this->buildErrorResponse(
            $message,
            Response::HTTP_NOT_FOUND
        );

        return $this->finish($result);
    }
}
