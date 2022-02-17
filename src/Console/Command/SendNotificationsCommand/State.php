<?php

declare(strict_types=1);

namespace CommunityTranslation\Console\Command\SendNotificationsCommand;

use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Error\UserMessageException;

defined('C5_EXECUTE') or die('Access Denied.');

class State
{
    public array $categories = [];

    public array $from;

    public bool $someNotificationSent = false;

    public bool $someNotificationFailed = false;

    public function __construct(Repository $config)
    {
        $from = $this->buildFrom($config, 'community_translation::notifications.sender.address', 'community_translation::notifications.sender.name');
        if ($from === null) {
            $from = $this->buildFrom($config, 'concrete.email.default.address', 'concrete.email.default.name');
            if ($from === null) {
                throw new UserMessageException('Neither the CommunityTranslation sender address nor the system default sender address are configured');
            }
        }
        $this->from = $from;
    }

    private function buildFrom(Repository $config, string $addressKey, string $nameKey = ''): ?array
    {
        $fromEmail = $config->get($addressKey);
        if (!is_string($fromEmail) || $fromEmail === '') {
            return null;
        }
        $fromName = $nameKey === '' ? null : $config->get($nameKey);
        if (!is_string($fromName) || $fromName === '') {
            $fromName = null;
        }

        return [$fromEmail, $fromName];
    }
}
