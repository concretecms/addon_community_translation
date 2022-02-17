<?php

declare(strict_types=1);

namespace CommunityTranslation\RemotePackage;

use Exception;

defined('C5_EXECUTE') or die('Access Denied.');

class DownloadException extends Exception
{
    public function __construct(string $message, int $httpCode)
    {
        parent::__construct($message, $httpCode);
    }

    public function getHttpCode(): int
    {
        return $this->getCode();
    }
}
