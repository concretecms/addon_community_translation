<?php

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation;

use CommunityTranslation\Api\UserControl as ApiUserControl;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Parser\Provider as ParserProvider;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Service\RateLimit;
use CommunityTranslation\UserException;
use Concrete\Core\Page\Controller\DashboardPageController;
use Exception;
use Illuminate\Filesystem\Filesystem;

class Options extends DashboardPageController
{
    private function getApiAccessOptions($localeDepentent)
    {
        return $localeDepentent ?
            [
                // 'localeadmins-all-locales', 'localeadmins-own-locales', 'globaladmins', 'nobody'
                ApiUserControl::ACCESSOPTION_EVERYBODY => t('Everybody (no authentication required)'),
                ApiUserControl::ACCESSOPTION_REGISTEREDUSERS => t('Registered users'),
                ApiUserControl::ACCESSOPTION_TRANSLATORS_ALLLOCALES => t('Translators (access to all locales)'),
                ApiUserControl::ACCESSOPTION_TRANSLATORS_OWNLOCALES => t('Translators (access to own locales only)'),
                ApiUserControl::ACCESSOPTION_LOCALEADMINS_ALLLOCALES => t('Language team coordinators (access to all locales)'),
                ApiUserControl::ACCESSOPTION_LOCALEADMINS_OWNLOCALES => t('Language team coordinators (access to own locales only)'),
                ApiUserControl::ACCESSOPTION_GLOBALADMINS => t('Global localization administrators'),
                ApiUserControl::ACCESSOPTION_SITEADMINS => t('Site administrators'),
                ApiUserControl::ACCESSOPTION_ROOT => t('Site administrator'),
                ApiUserControl::ACCESSOPTION_NOBODY => t('Nobody'),
            ]
            :
            [
                ApiUserControl::ACCESSOPTION_EVERYBODY => t('Everybody (no authentication required)'),
                ApiUserControl::ACCESSOPTION_REGISTEREDUSERS => t('Registered users'),
                ApiUserControl::ACCESSOPTION_TRANSLATORS => t('Translators (of any locale)'),
                ApiUserControl::ACCESSOPTION_LOCALEADMINS => t('Language team coordinators (of any locale)'),
                ApiUserControl::ACCESSOPTION_GLOBALADMINS => t('Global localization administrators'),
                ApiUserControl::ACCESSOPTION_SITEADMINS => t('Site administrators'),
                ApiUserControl::ACCESSOPTION_ROOT => t('Site administrator'),
                ApiUserControl::ACCESSOPTION_NOBODY => t('Nobody'),
            ]
        ;
    }

    private function getApiAccessChecks()
    {
        return [
            'getRateLimit' => [
                'name' => t('Get the current status of the rate limit'),
                'localeDepentent' => false,
            ],
            'getLocales' => [
                'name' => t('Get the list of approved locales'),
                'localeDepentent' => false,
            ],
            'getPackages' => [
                'name' => t('Get the list of available packages'),
                'localeDepentent' => false,
            ],
            'getPackageVersions' => [
                'name' => t('Get the version list of packages'),
                'localeDepentent' => false,
            ],
            'getPackageVersionLocales' => [
                'name' => t('Get the translation progress of package versions'),
                'localeDepentent' => true,
            ],
            'getPackageVersionTranslations' => [
                'name' => t('Get the translations of a specific package version'),
                'localeDepentent' => true,
            ],
            'fillTranslations' => [
                'name' => t('Fill-in known translations for users usage'),
                'localeDepentent' => true,
            ],
            'importPackage' => [
                'name' => t('Import translatable strings from a a remote package'),
                'localeDepentent' => false,
            ],
            'importPackageVersionTranslatables' => [
                'name' => t('Set the source strings of a specific package version'),
                'localeDepentent' => false,
            ],
            'importTranslations' => [
                'name' => t('Add translations (without approval)'),
                'localeDepentent' => true,
            ],
            'importTranslations_approve' => [
                'name' => t('Add translations (with approval)'),
                'localeDepentent' => true,
            ],
        ];
    }

    public function view()
    {
        $config = $this->app->make('community_translation/config');
        $this->set('sourceLocale', $this->app->make('community_translation/sourceLocale'));
        $this->set('translatedThreshold', $config->get('options.translatedThreshold', 90));
        $this->set('tempDir', str_replace('/', DIRECTORY_SEPARATOR, (string) $config->get('options.tempDir')));
        $this->set('notificationsSenderAddress', $config->get('options.notificationsSenderAddress'));
        $this->set('notificationsSenderName', $config->get('options.notificationsSenderName'));
        $this->set('onlineTranslationPath', $config->get('options.onlineTranslationPath'));
        $this->set('apiEntryPoint', $config->get('options.api.entryPoint'));
        $this->set('rateLimitHelper', $this->app->make(RateLimit::class));
        $this->set('apiRateLimitTimeWindow', $config->get('options.api.rateLimit.timeWindow'));
        $this->set('apiRateLimitMaxRequests', $config->get('options.api.rateLimit.maxRequests'));
        $this->set('apiAccessControlAllowOrigin', (string) $config->get('options.api.accessControlAllowOrigin'));
        $apiAccessChecks = [];
        foreach ($this->getApiAccessChecks() as $aacKey => $aacInfo) {
            $apiAccessChecks[$aacKey] = [
                'label' => $aacInfo['name'],
                'value' => $config->get('options.api.access.' . $aacKey),
                'values' => $this->getApiAccessOptions($aacInfo['localeDepentent']),
            ];
        }
        $this->set('apiAccessChecks', $apiAccessChecks);
        $parsers = [];
        foreach ($this->app->make(ParserProvider::class)->getRegisteredParsers() as $parser) {
            $parsers[get_class($parser)] = $parser->getDisplayName();
        }
        $this->set('parsers', $parsers);
        $this->set('defaultParser', $config->get('options.parser'));
    }

    public function submit()
    {
        if (!$this->token->validate('ct-options-save')) {
            $this->error->add($this->token->getErrorMessage());
            $this->view();

            return;
        }

        $config = $this->app->make('community_translation/config');

        try {
            $newSourceLocale = LocaleEntity::create((string) $this->post('sourceLocale'));
        } catch (Exception $x) {
            $newSourceLocale = null;
        }
        if ($newSourceLocale === null) {
            $this->error->add(t('Please specify a valid source locale'));
        } elseif ($this->app->make('community_translation/sourceLocale') === $newSourceLocale->getID()) {
            $newSourceLocale = null;
        } elseif ($newSourceLocale->getPluralCount() !== 2) {
            $this->error->add(t('Because of the gettext specifications, the source locale must have exactly 2 plural forms'));
        } else {
            $repo = $this->app->make(LocaleRepository::class);
            $existingLocale = $repo->find($newSourceLocale->getID());
            if ($existingLocale !== null) {
                $this->error->add(t("There's already an existing locale with code %s that's not the current source locale", $newSourceLocale->getID()));
            }
        }

        $translatedThreshold = null;
        $s = $this->post('translatedThreshold');
        if (is_string($s) && is_numeric($s)) {
            $s = (int) $s;
            if ($s >= 0 && $s <= 100) {
                $translatedThreshold = $s;
            }
        }
        if ($translatedThreshold === null) {
            $this->error->add(t('Please specify the translation thresold used to consider a language as translated'));
        }
        $tempDir = $this->post('tempDir', '');
        if (!is_string($tempDir)) {
            $tempDir = '';
        } else {
            $tempDir = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $tempDir), '/');
        }
        if ($tempDir !== '') {
            $fs = new Filesystem();
            if (!$fs->isDirectory($tempDir)) {
                $this->error->add(t('The specified temporary directory does not exist'));
            } elseif (!$fs->isWritable($tempDir)) {
                $this->error->add(t('The specified temporary directory is not writable'));
            }
        }
        $onlineTranslationPath = (string) $this->post('onlineTranslationPath');
        $onlineTranslationPath = preg_replace('/\s+/', '', $onlineTranslationPath);
        $onlineTranslationPath = preg_replace('/[\/\\\\]+/', '/', $onlineTranslationPath);
        $onlineTranslationPath = trim($onlineTranslationPath, '/');
        if ($onlineTranslationPath === '') {
            $this->error->add(t('Please specify the Online Translation URI'));
        } else {
            $onlineTranslationPath = '/' . $onlineTranslationPath;
        }
        $apiEntryPoint = (string) $this->post('apiEntryPoint');
        $apiEntryPoint = preg_replace('/\s+/', '', $apiEntryPoint);
        $apiEntryPoint = preg_replace('/[\/\\\\]+/', '/', $apiEntryPoint);
        $apiEntryPoint = trim($apiEntryPoint, '/');
        if ($apiEntryPoint === '') {
            $this->error->add(t('Please specify the API entry point'));
        } else {
            $apiEntryPoint = '/' . $apiEntryPoint;
        }
        try {
            list($apiRateLimitMaxRequests, $apiRateLimitTimeWindow) = $this->app->make(RateLimit::class)->fromWidgetHtml('apiRateLimit', (int) $config->get('options.api.rateLimit.timeWindow') ?: 3600);
        } catch (UserException $x) {
            $this->error->add($x->getMessage());
        }
        $apiAccessControlAllowOrigin = $this->post('apiAccessControlAllowOrigin');
        if (!is_string($apiAccessControlAllowOrigin) || $apiAccessControlAllowOrigin === '') {
            $this->error->add(t(/*i18n: %s is an HTTP header name*/'Please specify the value of the %s header', 'Access-Control-Allow-Origin'));
        }
        $apiAccess = [];
        foreach ($this->getApiAccessChecks() as $aacKey => $aacInfo) {
            $validValues = $this->getApiAccessOptions($aacInfo['localeDepentent']);
            $value = (string) $this->post('apiAccess-' . $aacKey);
            if ($value === '') {
                $this->error->add(t('Please specify the API access for: %s', $aacInfo['name']));
            } elseif (!isset($validValues[$value])) {
                $this->error->add(t('Unrecognized value for the API access for: %s', $aacInfo['name']));
            } else {
                $apiAccess[$aacKey] = $value;
            }
        }
        if (!$this->error->has()) {
            if ($newSourceLocale !== null) {
                $this->entityManager->beginTransaction();
                if ($oldSourceLocale !== null) {
                    $this->entityManager->remove($oldSourceLocale);
                    $this->entityManager->flush($oldSourceLocale);
                }
                $newSourceLocale->setIsSource(true)->setIsApproved(true);
                $this->entityManager->persist($newSourceLocale);
                $this->entityManager->flush($newSourceLocale);
                $this->entityManager->commit();
            }
            $config->save('options.translatedThreshold', $translatedThreshold);
            $config->save('options.tempDir', $tempDir);
            $config->save('options.notificationsSenderAddress', (string) $this->post('notificationsSenderAddress'));
            $config->save('options.notificationsSenderName', (string) $this->post('notificationsSenderName'));
            $config->save('options.onlineTranslationPath', $onlineTranslationPath);
            $config->save('options.api.entryPoint', $apiEntryPoint);
            $config->save('options.api.rateLimit.maxRequests', $apiRateLimitMaxRequests);
            $config->save('options.api.rateLimit.timeWindow', $apiRateLimitTimeWindow);
            $config->save('options.api.accessControlAllowOrigin', $apiAccessControlAllowOrigin);
            foreach ($apiAccess as $aacKey => $aacValue) {
                $config->save('options.api.access.' . $aacKey, $aacValue);
            }
            $config->save('options.parser', $this->post('parser'));
            $this->flash('message', t('Comminity Translation options have been saved.'));
            $this->redirect('/dashboard/community_translation/options');
        }
        $this->view();
    }
}
