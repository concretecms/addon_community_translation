<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation\Options;

use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class Paths extends DashboardPageController
{
    public function view(): ?Response
    {
        $this->set('urlResolver', $this->app->make(ResolverManagerInterface::class));
        $config = $this->app->make(Repository::class);
        $this->set('onlineTranslationPath', (string) $config->get('community_translation::paths.onlineTranslation'));
        $this->set('apiBasePath', (string) $config->get('community_translation::paths.api'));
        $this->set('tempDir', str_replace('/', DIRECTORY_SEPARATOR, (string) $config->get('community_translation::paths.tempDir')));
        $this->set('defaultTempDir', str_replace('/', DIRECTORY_SEPARATOR, (string) $this->app->make('helper/file')->getTemporaryDirectory()));

        return null;
    }

    public function submit(): ?Response
    {
        if (!$this->token->validate('ct-options-save-paths')) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view();
        }
        $onlineTranslationPath = $this->parseBaseUrl('onlineTranslationPath', t('Please specify the Online Translation URI'));
        $apiBasePath = $this->parseBaseUrl('apiBasePath', t('Please specify the API entry point'));
        $tempDir = $this->parseTempDir();
        if ($this->error->has()) {
            return $this->view();
        }
        $config = $this->app->make(Repository::class);
        $config->save('community_translation::paths.onlineTranslation', $onlineTranslationPath);
        $config->save('community_translation::paths.api', $apiBasePath);
        $config->save('community_translation::paths.tempDir', $tempDir);
        $this->flash('message', t('Comminity Translation options have been saved.'));

        return $this->buildRedirect([$this->request->getCurrentPage()]);
    }

    private function parseBaseUrl(string $fieldName, string $errorMessage): string
    {
        $result = $this->request->request->get($fieldName);
        $result = preg_replace('/\s+/', '', is_string($result) ? $result : '');
        $result = preg_replace('/[\/\\\\]+/', '/', $result);
        $result = trim($result, '/');
        if ($result === '') {
            $this->error->add($errorMessage);

            return '';
        }

        return '/' . $result;
    }

    private function parseTempDir(): string
    {
        $result = $this->request->request->get('tempDir');
        $result = is_string($result) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $result), '/') : '';
        if ($result === '') {
            return '';
        }
        $fs = new Filesystem();
        if (!$fs->isDirectory($result)) {
            $this->error->add(t('The specified temporary directory does not exist'));
        } elseif (!$fs->isWritable($result)) {
            $this->error->add(t('The specified temporary directory is not writable'));
        }

        return $result;
    }
}
