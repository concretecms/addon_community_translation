<?php

declare(strict_types=1);

namespace CommunityTranslation\Api\EntryPoint;

use CommunityTranslation\Api\EntryPoint;
use Concrete\Core\Error\UserMessageException;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * What to do if the entry point is not recognized.
 */
class Unrecognized extends EntryPoint
{
    public function __invoke(string $unrecognizedPath = ''): Response
    {
        return $this->handle(
            function () use ($unrecognizedPath): Response {
                if ($unrecognizedPath === '') {
                    $message = t('Resource not specified');
                } else {
                    $message = t(/*i18n: %1$s is a path, %2$s is an HTTP method*/'Unknown resource %1$s for %2$s method', $unrecognizedPath, $this->request->getMethod());
                }

                return $this->buildErrorResponse(new UserMessageException($message), Response::HTTP_NOT_FOUND);
            }
        );
    }
}
