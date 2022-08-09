<?php

declare(strict_types=1);

namespace CommunityTranslation\RemotePackage;

use Concrete\Core\Error\UserMessageException;

defined('C5_EXECUTE') or die('Access Denied.');

class DownloadException extends UserMessageException
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
