<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\System\CommunityTranslation;

use Concrete\Core\Page\Controller\DashboardPageController;
use Illuminate\Filesystem\Filesystem;

class Options extends DashboardPageController
{
    public function view()
    {
        $config = \Package::getByHandle('community_translation')->getFileConfig();
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
        $this->set('tempDir', $config->get('options.tempDir'));
        $this->set('notificationsSenderAddress', $config->get('options.notificationsSenderAddress'));
        $this->set('notificationsSenderName', $config->get('options.notificationsSenderName'));
        foreach (array(
            'options.api.access.stats' => 'apiAccess_stats',
            'options.api.access.download' => 'apiAccess_download',
            'options.api.access.import_packages' => 'apiAccess_import_packages',
            'options.api.access.update_package_translations' => 'apiAccess_update_package_translations',
        ) as $key => $varName) {
            $gID = $config->get($key);
            $gID = @intval($gID);
            if ($gID !== 0 && $gID != GUEST_GROUP_ID) {
                $group = \Group::getByID($gID);
                if ($group === null) {
                    $gName = '<i>'.t('Removed group').'</i>';
                } else {
                    $gName = $group->getGroupDisplayName();
                }
            } else {
                $gID = GUEST_GROUP_ID;
                $gName = h(tc('GroupName', 'Guest'));
            }
            $this->set($varName, compact('gID', 'gName'));
        }
    }

    public function submit()
    {
        if (!$this->token->validate('ct-options-save')) {
            $this->error->add($this->token->getErrorMessage());
            $this->view();

            return;
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
        $apiAccess_update_package_translations = @intval($this->post('apiAccess_update_package_translations'));
        if ($apiAccess_update_package_translations <= 0) {
            $this->error->add(t('Please specify the user group to control the API access to update package translations'));
        }
        if (!$this->error->has()) {
            $config = \Package::getByHandle('community_translation')->getFileConfig();
            $config->save('options.translatedThreshold', $translatedThreshold);
            $config->save('options.downloadAccess', $downloadAccess);
            $config->save('options.tempDir', $tempDir);
            $config->save('options.notificationsSenderAddress', (string) $this->post('notificationsSenderAddress'));
            $config->save('options.notificationsSenderName', (string) $this->post('notificationsSenderName'));
            $config->save('options.api.access.stats', $apiAccess_stats);
            $config->save('options.api.access.download', $apiAccess_download);
            $config->save('options.api.access.import_packages', $apiAccess_import_packages);
            $config->save('options.api.access.update_package_translations', $apiAccess_update_package_translations);
            $this->flash('message', t('Comminity Translation options have been saved.'));
            $this->redirect('/dashboard/system/community_translation/options');
        }
        $this->view();
    }
}
