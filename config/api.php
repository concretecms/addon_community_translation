<?php

declare(strict_types=1);

use CommunityTranslation\Api;

defined('C5_EXECUTE') or die('Access Denied.');

return [
    'accessControlAllowOrigin' => '*',
    'access' => [
        Api\EntryPoint\GetRateLimit::ACCESS_KEY => Api\UserControl::ACCESSOPTION_EVERYBODY,
        Api\EntryPoint\GetLocales::ACCESS_KEY => Api\UserControl::ACCESSOPTION_EVERYBODY,
        Api\EntryPoint\GetPackages::ACCESS_KEY => Api\UserControl::ACCESSOPTION_EVERYBODY,
        Api\EntryPoint\GetPackageVersions::ACCESS_KEY => Api\UserControl::ACCESSOPTION_EVERYBODY,
        Api\EntryPoint\GetPackageVersionLocales::ACCESS_KEY => Api\UserControl::ACCESSOPTION_EVERYBODY,
        Api\EntryPoint\GetPackageVersionTranslations::ACCESS_KEY => Api\UserControl::ACCESSOPTION_EVERYBODY,
        Api\EntryPoint\FillTranslations::ACCESS_KEY => Api\UserControl::ACCESSOPTION_EVERYBODY,
        Api\EntryPoint\ImportPackage::ACCESS_KEY => Api\UserControl::ACCESSOPTION_GLOBALADMINS,
        Api\EntryPoint\ImportPackageVersionTranslatables::ACCESS_KEY => Api\UserControl::ACCESSOPTION_GLOBALADMINS,
        Api\EntryPoint\ImportTranslations::ACCESS_KEY_WITHOUTAPPROVE => Api\UserControl::ACCESSOPTION_TRANSLATORS_OWNLOCALES,
        Api\EntryPoint\ImportTranslations::ACCESS_KEY_WITHAPPROVE => Api\UserControl::ACCESSOPTION_LOCALEADMINS_OWNLOCALES,
    ],
];
