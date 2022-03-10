<?php

declare(strict_types=1);

namespace CommunityTranslation\Api\EntryPoint;

use CommunityTranslation\Api\EntryPoint;
use CommunityTranslation\Repository\Package as PackageRepository;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Get the list of packages.
 *
 * @example GET request to http://www.example.com/api/packages
 */
class GetPackages extends EntryPoint
{
    public const ACCESS_KEY = 'getPackages';

    public function __invoke(): Response
    {
        return $this->handle(
            function (): Response {
                $this->userControl->checkGenericAccess(static::ACCESS_KEY);
                $repo = $this->app->make(PackageRepository::class);
                $packages = $repo->createQueryBuilder('p')
                    ->select('p.handle, p.name')
                    ->getQuery()->getArrayResult();

                return $this->buildJsonResponse($packages);
            }
        );
    }
}
