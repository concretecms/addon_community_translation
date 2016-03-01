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
        $this->set('tempDir', $config->get('options.tempDir'));
        $this->set('notificationsSenderAddress', $config->get('options.notificationsSenderAddress'));
        $this->set('notificationsSenderName', $config->get('options.notificationsSenderName'));
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
        if (!$this->error->has()) {
            $config = \Package::getByHandle('community_translation')->getFileConfig();
            $config->save('options.translatedThreshold', $translatedThreshold);
            $config->save('options.tempDir', $tempDir);
            $config->save('options.notificationsSenderAddress', (string) $this->post('notificationsSenderAddress'));
            $config->save('options.notificationsSenderName', (string) $this->post('notificationsSenderName'));
            $this->flash('message', t('Comminity Translation options have been saved.'));
            $this->redirect('/dashboard/system/community_translation/options');
        }
        $this->view();
    }
}
