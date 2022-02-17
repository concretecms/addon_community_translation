<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

return [
    'notify' => false,
    'notifyTo' => [
        /*
         * Example:
         * [
         *     [
         *         'handler' => 'slack',
         *         'enabled' => true,
         *         'level' => Monolog\Logger::ERROR,
         *         'description' => 'Custom description for this handler',
         *         'apiToken' => '...',
         *         'channel' => '#general',
         *     ],
         *     [
         *         'handler' => 'telegram',
         *         'enabled' => true,
         *         'level' => Monolog\Logger::ERROR,
         *         'description' => 'Custom description for this handler',
         *         'botToken' => '...',
         *         'chatID' => '...',
         *     ],
         * ],
         */
    ],
];
