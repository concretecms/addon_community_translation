<?php

namespace Concrete\Package\CommunityTranslation\Src\Api;

class AccessDeniedException extends \Exception
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
