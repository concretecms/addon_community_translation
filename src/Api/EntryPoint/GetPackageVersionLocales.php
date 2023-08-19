<?php

declare(strict_types=1);

namespace CommunityTranslation\Api\EntryPoint;

use CommunityTranslation\Api\EntryPoint;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Repository\Stats as StatsRepository;
use Concrete\Core\Error\UserMessageException;
use DateTimeZone;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Get some stats about the translations of a package version.
 *
 * @example GET request to http://www.example.com/api/package/concrete5/8.2.0/locales/80
 * @example GET request to http://www.example.com/api/package/concrete5/best-match-version/locales/80?v=8.2.0rc1
 */
class GetPackageVersionLocales extends EntryPoint
{
    public const ACCESS_KEY = 'getPackageVersionLocales';

    public function __invoke(string $packageHandle, string $packageVersion, string $minimumLevel = ''): Response
    {
        return $this->handle(
            function () use ($packageHandle, $packageVersion, $minimumLevel): Response {
                $accessibleLocales = $this->userControl->checkLocaleAccess(static::ACCESS_KEY);
                $minimumLevel = (int) $minimumLevel;
                if ($minimumLevel < 0 || $minimumLevel > 100) {
                    throw new UserMessageException(t('The minimum level must be between %1$s and %2$s', 0, 100));
                }
                $package = $this->app->make(PackageRepository::class)->getByHandle($packageHandle);
                if ($package === null) {
                    throw new UserMessageException(t('Unable to find the specified package'), Response::HTTP_NOT_FOUND);
                }
                $isBestMatch = null;
                $version = $this->getPackageVersion($package, $packageVersion, $isBestMatch);
                if ($version === null) {
                    throw new UserMessageException(t('Unable to find the specified package version'), Response::HTTP_NOT_FOUND);
                }
                $stats = $this->app->make(StatsRepository::class)->get($version, $accessibleLocales);
                $utc = new DateTimeZone('UTC');
                $resultData = $this->serializeLocales(
                    $accessibleLocales,
                    static function (LocaleEntity $locale, array $item) use ($stats, $minimumLevel, $utc): ?array {
                        foreach ($stats as $stat) {
                            if ($stat->getLocale() !== $locale) {
                                continue;
                            }
                            if ($stat->getRoundedPercentage() < $minimumLevel) {
                                return null;
                            }
                            $dt = $stat->getLastUpdated();
                            if ($dt === null) {
                                $updated = null;
                            } else {
                                $dt = clone $dt;
                                $dt->setTimezone($utc);
                                $updated = $dt->format('c');
                            }

                            return $item + [
                                'total' => $stat->getTotal(),
                                'translated' => $stat->getTranslated(),
                                'progress' => $stat->getRoundedPercentage(),
                                'updated' => $updated,
                            ];
                        }

                        return null;
                    }
                );
                if ($isBestMatch) {
                    $resultData = [
                        'versionHandle' => $version->getVersion(),
                        'versionName' => $version->getDisplayVersion(),
                        'locales' => $resultData,
                    ];
                }

                return $this->buildJsonResponse($resultData);
            }
        );
    }
}
