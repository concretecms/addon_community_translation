<?php

namespace CommunityTranslation\RemotePackage;

use Exception;

class DownloadException extends Exception
{
    public function getHttpCode()
    {
        return $this->getCode();
    }

    public function __construct($message, $httpCode)
    {
        parent::__construct($message, $httpCode);
    }
}
