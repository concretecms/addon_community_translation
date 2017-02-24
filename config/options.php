<?php
return [
    'translatedThreshold' => 90,
    'downloadAccess' => 'members',
    'api' => [
        'entryPoint' => '/api',
        'access' => [
            'stats' => (int) GUEST_GROUP_ID,
            'download' => (int) GUEST_GROUP_ID,
            'importPackages' => (int) ADMIN_GROUP_ID,
            'updatePackageTranslations' => (int) REGISTERED_GROUP_ID,
        ],
    ],
    'parser' => 'CommunityTranslation\Parser\Concrete5Parser',
    'tempDir' => null,
    'notificationsSenderAddress' => '',
    'notificationsSenderName' => '',
    'translatorPath' => '/translate/online2',
];
