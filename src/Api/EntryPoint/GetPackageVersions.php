<?php

declare(strict_types=1);

namespace CommunityTranslation\Api\EntryPoint;

use CommunityTranslation\Api\EntryPoint;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Repository\Package as PackageRepository;
use Concrete\Core\Error\UserMessageException;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Get the list of versions of a package.
 *
 * @example GET request to http://www.example.com/api/package/concrete5/versions
 */
class GetPackageVersions extends EntryPoint
{
    public const ACCESS_KEY = 'getPackageVersions';

    public function __invoke(string $packageHandle): Response
    {
        return $this->handle(
            function () use ($packageHandle): Response {
                $this->userControl->checkGenericAccess(static::ACCESS_KEY);
                $package = $this->app->make(PackageRepository::class)->findOneBy(['handle' => $packageHandle]);
                if ($package === null) {
                    throw new UserMessageException(t('Unable to find the specified package'), Response::HTTP_NOT_FOUND);
                }
                $versions = array_map(
                    static function (PackageVersionEntity $packageVersion): string {
                        return $packageVersion->getVersion();
                    },
                    $package->getSortedVersions(true)
                );

                return $this->buildJsonResponse($versions);
            }
        );
    }
}
