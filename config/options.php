<?php
return [
    'api' => [
        'access' => [
            'download' => (int) GUEST_GROUP_ID,
            'importPackages' => (int) ADMIN_GROUP_ID,
            'stats' => (int) GUEST_GROUP_ID,
            'updatePackageTranslations' => (int) REGISTERED_GROUP_ID,
        ],
        'entryPoint' => '/api',
    ],
    'downloadAccess' => 'members',
    'notificationsSenderAddress' => '',
    'notificationsSenderName' => '',
    'onlineTranslationPath' => '/translate/online',
    'parser' => 'CommunityTranslation\Parser\Concrete5Parser',
    'tempDir' => null,
    'translatedThreshold' => 90,
];
