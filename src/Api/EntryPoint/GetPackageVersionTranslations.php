<?php

declare(strict_types=1);

namespace CommunityTranslation\Api\EntryPoint;

use CommunityTranslation\Api\AccessDeniedException;
use CommunityTranslation\Api\EntryPoint;
use CommunityTranslation\Repository\DownloadStats as DownloadStatsRepository;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Translation\FileExporter as TranslationFileExporter;
use CommunityTranslation\TranslationsConverter\Provider as TranslationsConverterProvider;
use Concrete\Core\Error\UserMessageException;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Get the translations of a package version.
 *
 * @example GET request to http://www.example.com/api/package/concrete5/8.2.0/translations/it_IT/mo
 * @example GET request to http://www.example.com/api/package/concrete5/best-match-version/translations/it_IT/mo?v=8.2.0rc1
 */
class GetPackageVersionTranslations extends EntryPoint
{
    public const ACCESS_KEY = 'getPackageVersionTranslations';

    public function __invoke(string $packageHandle, string $packageVersion, string $localeID, string $formatHandle): Response
    {
        return $this->handle(
            function () use ($packageHandle, $packageVersion, $localeID, $formatHandle): Response {
                $accessibleLocales = $this->userControl->checkLocaleAccess(static::ACCESS_KEY);
                $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
                if ($locale === null) {
                    throw new UserMessageException(t('Unable to find the specified locale'), Response::HTTP_NOT_FOUND);
                }
                if (!in_array($locale, $accessibleLocales, true)) {
                    throw new AccessDeniedException(t('Access denied to the specified locale'));
                }
                $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $packageHandle]);
                if ($package === null) {
                    throw new UserMessageException(t('Unable to find the specified package'), Response::HTTP_NOT_FOUND);
                }
                $version = $this->getPackageVersion($package, $packageVersion);
                if ($version === null) {
                    throw new UserMessageException(t('Unable to find the specified package version'), Response::HTTP_NOT_FOUND);
                }
                $format = $this->app->make(TranslationsConverterProvider::class)->getByHandle($formatHandle);
                if ($format === null) {
                    throw new UserMessageException(t('Unable to find the specified translations format'), Response::HTTP_NOT_FOUND);
                }
                $response = $this->app->make(TranslationFileExporter::class)->buildSerializedTranslationsFileResponse($version, $locale, $format);
                $this->app->make(DownloadStatsRepository::class)->logDownload($locale, $version);

                return $response;
            }
        );
    }
}
