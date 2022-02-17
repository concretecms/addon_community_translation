<?php

declare(strict_types=1);

namespace CommunityTranslation\Api;

defined('C5_EXECUTE') or die('Access Denied.');

use Exception;
use Symfony\Component\HttpFoundation\Response;

class AccessDeniedException extends Exception
{
    public function __construct(string $message = '', int $code = Response::HTTP_UNAUTHORIZED)
    {
        if ($message === '') {
            $message = t('Access denied.');
        }
        parent::__construct($message, $code);
    }
}
