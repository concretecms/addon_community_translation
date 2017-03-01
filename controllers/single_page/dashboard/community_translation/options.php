<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Parser\Provider as ParserProvider;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\Page\Controller\DashboardPageController;
use Exception;
use Illuminate\Filesystem\Filesystem;

class Options extends DashboardPageController
{
    public function view()
    {
        $config = $this->app->make('community_translation/config');
        $this->set('sourceLocale', $this->app->make('community_translation/sourceLocale'));
        $this->set('translatedThreshold', $config->get('options.translatedThreshold', 90));
        $downloadAccess = $config->get('options.downloadAccess');
        switch ($downloadAccess) {
            case 'anyone':
            case 'members':
            case 'translators':
                break;
            default:
                $downloadAccess = 'members';
                break;
        }
        $this->set('downloadAccess', $downloadAccess);
        $this->set('tempDir', str_replace('/', DIRECTORY_SEPARATOR, (string) $config->get('options.tempDir')));
        $this->set('notificationsSenderAddress', $config->get('options.notificationsSenderAddress'));
        $this->set('notificationsSenderName', $config->get('options.notificationsSenderName'));
        $this->set('onlineTranslationPath', $config->get('options.onlineTranslationPath'));
        $this->set('apiEntryPoint', $config->get('options.api.entryPoint'));
        foreach ([
            'options.api.access.stats' => 'apiAccess_stats',
            'options.api.access.download' => 'apiAccess_download',
            'options.api.access.importPackages' => 'apiAccess_import_packages',
            'options.api.access.updatePackageTranslations' => 'apiAccess_updatePackageTranslations',
        ] as $key => $varName) {
            $gID = $config->get($key);
            $gID = @intval($gID);
            if ($gID !== 0 && $gID != GUEST_GROUP_ID) {
                $group = \Group::getByID($gID);
                if ($group === null) {
                    $gName = '<i>' . t('Removed group') . '</i>';
                } else {
                    $gName = $group->getGroupDisplayName();
                }
            } else {
                $gID = GUEST_GROUP_ID;
                $gName = h(tc('GroupName', 'Guest'));
            }
            $this->set($varName, compact('gID', 'gName'));
        }
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
        $downloadAccess = $this->post('downloadAccess');
        switch ($downloadAccess) {
            case 'anyone':
            case 'members':
            case 'translators':
                break;
            default:
                $this->error->add(t('Please specify who can download translations'));
                break;
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
        $apiAccess_stats = @intval($this->post('apiAccess_stats'));
        if ($apiAccess_stats <= 0) {
            $this->error->add(t('Please specify the user group to control the API access to statistical data'));
        }
        $apiAccess_download = @intval($this->post('apiAccess_download'));
        if ($apiAccess_download <= 0) {
            $this->error->add(t('Please specify the user group to control the API access to downloads'));
        }
        $apiAccess_import_packages = @intval($this->post('apiAccess_import_packages'));
        if ($apiAccess_import_packages <= 0) {
            $this->error->add(t('Please specify the user group to control the API access to import packages'));
        }
        $apiAccess_updatePackageTranslations = @intval($this->post('apiAccess_updatePackageTranslations'));
        if ($apiAccess_updatePackageTranslations <= 0) {
            $this->error->add(t('Please specify the user group to control the API access to update package translations'));
        }
        if (!$this->error->has()) {
            $config = $this->app->make('community_translation/config');
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
            $config->save('options.downloadAccess', $downloadAccess);
            $config->save('options.tempDir', $tempDir);
            $config->save('options.notificationsSenderAddress', (string) $this->post('notificationsSenderAddress'));
            $config->save('options.notificationsSenderName', (string) $this->post('notificationsSenderName'));
            $config->save('options.onlineTranslationPath', $onlineTranslationPath);
            $config->save('options.api.entryPoint', $apiEntryPoint);
            $config->save('options.api.access.stats', $apiAccess_stats);
            $config->save('options.api.access.download', $apiAccess_download);
            $config->save('options.api.access.importPackages', $apiAccess_import_packages);
            $config->save('options.api.access.updatePackageTranslations', $apiAccess_updatePackageTranslations);
            $config->save('options.parser', $this->post('parser'));
            $this->flash('message', t('Comminity Translation options have been saved.'));
            $this->redirect('/dashboard/community_translation/options');
        }
        $this->view();
    }
}
