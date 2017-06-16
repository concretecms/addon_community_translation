<?php

return [
    'api' => [
        'access' => [
            'getRateLimit' => 'everybody',
            'getLocales' => 'everybody',
            'getPackages' => 'everybody',
            'getPackageVersions' => 'everybody',
            'getPackageVersionLocales' => 'everybody',
            'getPackageVersionTranslations' => 'everybody',
            'fillTranslations' => 'everybody',
            'importPackage' => 'globaladmins',
            'importPackageVersionTranslatables' => 'globaladmins',
            'importTranslations' => 'translators-own-locales',
            'importTranslations_approve' => 'localeadmins-own-locales',
        ],
        'accessControlAllowOrigin' => '*',
        'entryPoint' => '/api',
        'rateLimit' => [
            'maxRequests' => null,
            'timeWindow' => 3600,
        ],
    ],
    'nonInteractiveCLICommands' => [
        'notify' => false,
        'to' => [
            /* Example:
             [
             'handler' => 'slack',
             'apiToken' => 'xxx',
             'channel' => '#general',
             ],
             */
        ],
    ],
    'notificationsSenderAddress' => '',
    'notificationsSenderName' => '',
    'onlineTranslationPath' => '/translate/online',
    'parser' => 'CommunityTranslation\Parser\Concrete5Parser',
    'tempDir' => null,
    'translatedThreshold' => 90,
];
