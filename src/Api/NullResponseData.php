<?php

declare(strict_types=1);

namespace CommunityTranslation\Api;

use JsonSerializable;

defined('C5_EXECUTE') or die('Access Denied.');

final class NullResponseData implements JsonSerializable
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * {@inheritdoc}
     *
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return null;
    }
}
