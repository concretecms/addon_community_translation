<?php

declare(strict_types=1);

namespace CommunityTranslation\Api\EntryPoint;

use CommunityTranslation\Api\EntryPoint;
use CommunityTranslation\Api\NullResponseData;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Get the current rate limit status.
 *
 * @example GET request to http://www.example.com/api/rate-limit
 */
class GetRateLimit extends EntryPoint
{
    public const ACCESS_KEY = 'getRateLimit';

    public function __invoke(): Response
    {
        return $this->handle(
            function (): Response {
                $this->userControl->checkGenericAccess(static::ACCESS_KEY);
                $rateLimitService = $this->userControl->getIpAccessControlRateLimit();
                $rateLimitCategory = $rateLimitService->getCategory();
                if (!$rateLimitCategory->isEnabled() || $rateLimitService->isAllowlisted()) {
                    $responseData = NullResponseData::getInstance();
                } else {
                    $responseData = [
                        'maxRequests' => $rateLimitCategory->getMaxEvents(),
                        'timeWindow' => $rateLimitCategory->getTimeWindow(),
                        'currentCounter' => $rateLimitService->getEventsCount(),
                    ];
                }

                return $this->buildJsonResponse($responseData);
            }
        );
    }
}
