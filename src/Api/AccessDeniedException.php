<?php

namespace CommunityTranslation\Api;

use Exception;

class AccessDeniedException extends Exception
{
    /**
     * @param string $message
     *
     * @return static
     */
    public static function create($message = '')
    {
        $result = new static($message ?: t('Access denied.'));

        return $result;
    }
}
