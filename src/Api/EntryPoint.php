<?php

declare(strict_types=1);

namespace CommunityTranslation\Api;

use Closure;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Service\VersionComparer;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactory;
use Concrete\Core\Localization\Localization;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

abstract class EntryPoint extends AbstractController
{
    protected Localization $localization;

    protected UserControl $userControl;

    protected ResponseFactory $responseFactory;

    protected string $requestedResultsLocale;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Controller\AbstractController::on_start()
     */
    public function on_start()
    {
        parent::on_start();
        $this->localization = $this->app->make(Localization::class);
        $this->userControl = $this->app->make(UserControl::class, ['request' => $this->request]);
        $this->responseFactory = $this->app->make(ResponseFactory::class);
        $rl = $this->request->query->get('rl', '');
        $this->requestedResultsLocale = is_string($rl) ? trim($rl) : '';
        if ($this->requestedResultsLocale !== '') {
            if ($this->requestedResultsLocale === $this->localization->getLocale()) {
                $this->requestedResultsLocale = '';
            } else {
                $this->localization->setContextLocale('communityTranslationAPI', $this->requestedResultsLocale);
                $this->localization->pushActiveContext('communityTranslationAPI');
            }
        }
    }

    protected function handle(Closure $callback): Response
    {
        try {
            $this->userControl->checkRateLimit();
            $result = $callback();
        } catch (Throwable $x) {
            $result = $this->buildErrorResponse($x);
        }

        return $this->finalizeResponse($result);
    }

    /**
     * @param mixed $data
     */
    protected function buildJsonResponse($data): Response
    {
        return $this->responseFactory->json($data);
    }

    /**
     * Build an error response.
     */
    protected function buildErrorResponse(Throwable $error, ?int $code = null): Response
    {
        if ($code !== null) {
            $code = $this->filterErrorResponseCode($code);
        }
        if ($error instanceof AccessDeniedException) {
            $message = $error->getMessage();
            if ($code === null) {
                $code = $this->filterErrorResponseCode($error->getCode(), Response::HTTP_UNAUTHORIZED);
            }
        } elseif ($error instanceof UserMessageException) {
            $message = $error->getMessage();
            if ($code === null) {
                $code = $this->filterErrorResponseCode($error->getCode(), Response::HTTP_BAD_REQUEST);
            }
        } else {
            $message = t('An unexpected error occurred');
            if ($code === null) {
                $code = Response::HTTP_INTERNAL_SERVER_ERROR;
            }
        }

        return $this->responseFactory->create(
            $message,
            $code,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]
        );
    }

    /**
     * @param \CommunityTranslation\Entity\Locale[] $locales
     */
    protected function serializeLocales(array $locales, ?Closure $itemCustomizer = null): array
    {
        $result = [];
        foreach ($locales as $locale) {
            $item = [
                'id' => $locale->getID(),
                'name' => $locale->getName(),
                'nameLocalized' => $locale->getDisplayName(),
            ];
            if ($itemCustomizer !== null) {
                $item = $itemCustomizer($locale, $item);
            }
            if ($item !== null) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param bool|null $isBastMatch [out]
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    protected function getPackageVersion(PackageEntity $package, string $packageVersion, ?bool & $isBastMatch = null): ?PackageVersionEntity
    {
        if ($packageVersion === 'best-match-version') {
            $isBastMatch = true;
            $v = $this->request->query->get('v');
            if (!is_string($v) || $v === '') {
                throw new UserMessageException(t('Missing required querystring parameter: %s', 'v'));
            }
            $versionComparer = new VersionComparer();

            return $versionComparer->matchPackageVersionEntities($package->getVersions()->toArray(), $v);
        }
        $isBastMatch = false;

        return $this->app->make(PackageVersionRepository::class)->findByHandleAndVersion($package->getHandle(), $packageVersion);
    }

    private function finalizeResponse(Response $response): Response
    {
        if ($this->requestedResultsLocale !== '') {
            $this->localization->popActiveContext();
        }
        if (!$response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'DENY');
        }
        if (!$response->headers->has('Access-Control-Allow-Origin')) {
            $config = $this->app->make(Repository::class);
            $response->headers->set('Access-Control-Allow-Origin', (string) $config->get('community_translation::api.accessControlAllowOrigin'));
        }

        return $response;
    }

    private function filterErrorResponseCode(int $errorResponseCode, ?int $onInvalid = null): ?int
    {
        return $errorResponseCode < 400 || $errorResponseCode > 599 ? $onInvalid : $errorResponseCode;
    }
}
