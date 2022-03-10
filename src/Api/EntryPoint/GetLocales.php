<?php

declare(strict_types=1);

namespace CommunityTranslation\Api\EntryPoint;

use CommunityTranslation\Api\EntryPoint;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Get the list of approved locales.
 *
 * @example GET request to http://www.example.com/api/locales
 */
class GetLocales extends EntryPoint
{
    public const ACCESS_KEY = 'getLocales';

    public function __invoke(): Response
    {
        return $this->handle(
            function (): Response {
                $this->userControl->checkGenericAccess(static::ACCESS_KEY);
                $locales = $this->app->make(LocaleRepository::class)->getApprovedLocales();

                return $this->buildJsonResponse($this->serializeLocales($locales));
            }
        );
    }
}
