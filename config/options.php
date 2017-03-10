<?php
return [
    'api' => [
        'access' => [
            'getLocales' => 'everybody',
            'getPackages' => 'everybody',
            'getPackageVersions' => 'everybody',
            'getPackageVersionLocales' => 'everybody',
            'getPackageVersionTranslations' => 'everybody',
            'importPackageVersionTranslatables' => 'globaladmins',
            'importTranslations' => 'translators-own-locales',
            'importTranslations_approve' => 'localeadmins-own-locales',
        ],
        'accessControlAllowOrigin' => '*',
        'entryPoint' => '/api',
    ],
    'notificationsSenderAddress' => '',
    'notificationsSenderName' => '',
    'onlineTranslationPath' => '/translate/online',
    'parser' => 'CommunityTranslation\Parser\Concrete5Parser',
    'tempDir' => null,
    'translatedThreshold' => 90,
    'nonInteractiveCLICommands' => [
        'notify' => true,
        'to' => [
        ],
    ],
];
