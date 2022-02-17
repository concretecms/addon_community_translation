<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation\Options;

use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Concrete\Core\Validator\String\EmailValidator;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class Notifications extends DashboardPageController
{
    public function view(): ?Response
    {
        $this->set('urlResolver', $this->app->make(ResolverManagerInterface::class));
        $config = $this->app->make(Repository::class);
        $this->set('defaultSenderAddress', (string) $config->get('concrete.email.default.address'));
        $this->set('senderAddress', (string) $config->get('community_translation::notifications.sender.address'));
        $this->set('defaultSenderName', (string) $config->get('concrete.email.default.name'));
        $this->set('senderName', (string) $config->get('community_translation::notifications.sender.name'));

        return null;
    }

    public function submit(): ?Response
    {
        if (!$this->token->validate('ct-options-save-notifications')) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view();
        }
        $senderAddress = $this->parseSenderAddress();
        $senderName = $this->parseSenderName();
        if ($this->error->has()) {
            return $this->view();
        }
        $config = $this->app->make(Repository::class);
        $config->save('community_translation::notifications.sender.address', $senderAddress);
        $config->save('community_translation::notifications.sender.name', $senderName);
        $this->flash('message', t('Comminity Translation options have been saved.'));

        return $this->buildRedirect([$this->request->getCurrentPage()]);
    }

    private function parseSenderAddress(): string
    {
        $result = $this->request->request->get('senderAddress');
        $result = is_string($result) ? trim($result) : '';
        if ($result === '') {
            return '';
        }
        $this->app->make(EmailValidator::class)->isValid($result, $this->error);

        return $result;
    }

    private function parseSenderName(): string
    {
        $result = $this->request->request->get('senderName');

        return is_string($result) ? trim($result) : '';
    }
}
