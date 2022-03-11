<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation\Options;

use Closure;
use CommunityTranslation\Monolog\Handler\TelegramHandler;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\Request;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use DateTime;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SlackHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class Cli extends DashboardPageController
{
    public function view(): ?Response
    {
        $this->set('urlResolver', $this->app->make(ResolverManagerInterface::class));
        $config = $this->app->make(Repository::class);
        $this->set('notify', (bool) $config->get('community_translation::cli.notify'));
        $this->set('notifyTo', $this->getEditNotifyTo());
        $handlers = [];
        foreach ($this->getHandlers() as $handler) {
            $handlers[] = array_filter(
                $handler,
                static function (string $key): bool {
                    return in_array($key, ['handle', 'name'], true);
                },
                ARRAY_FILTER_USE_KEY
            );
        }
        $this->set('handlers', $handlers);
        $this->set('handlerLevels', $this->getHandlerLevels());

        return null;
    }

    public function submit(): ?Response
    {
        if (!$this->token->validate('ct-options-save-cli')) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view();
        }
        $notify = $this->parseNotify();
        $notifyTo = $this->parseNotifyTo(true);
        if ($this->error->has()) {
            return $this->view();
        }
        $config = $this->app->make(Repository::class);
        $config->save('community_translation::cli.notify', $notify);
        $config->save('community_translation::cli.notifyTo', $notifyTo);
        $this->flash('message', t('Comminity Translation options have been saved.'));

        return $this->buildRedirect([$this->request->getCurrentPage()]);
    }

    public function test_handler(): Response
    {
        if (!$this->token->validate('ct-options-cli-testhandler')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $tester = null;
        $notifyTo = $this->parseOneNotifyTo('', true, $tester);
        if ($this->error->has()) {
            throw new UserMessageException($this->error->toText());
        }
        $level = null;
        foreach ($this->getHandlerLevels() as $hl) {
            if ($level === null || $hl['level'] > $level) {
                $level = $hl['level'];
            }
        }
        $record = [
            'message' => t('Sample Message'),
            'context' => [],
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'Test',
            'datetime' => new DateTime(),
            'extra' => [],
        ];
        try {
            $tester($notifyTo, $record);
        } catch (Throwable $x) {
            throw new UserMessageException($x->getMessage());
        }

        return $this->app->make(ResponseFactoryInterface::class)->json(true);
    }

    private function getHandlers(): array
    {
        return [
            [
                'handle' => 'slack',
                'name' => 'Slack',
                'parser' => function (string $prefix, bool $setError): array {
                    $result = [];
                    $v = $this->request->request->get("{$prefix}apiToken");
                    $result['apiToken'] = is_string($v) ? trim($v) : '';
                    if ($result['apiToken'] === '') {
                        if ($setError) {
                            $this->error->add(t('Please specify the Slack API Token'));
                        }
                    }
                    $v = $this->request->request->get("{$prefix}channel");
                    $result['channel'] = is_string($v) ? ltrim(trim($v), '#') : '';
                    if ($result['channel'] === '') {
                        if ($setError) {
                            $this->error->add(t('Please specify the Slack Channel'));
                        }
                    } else {
                        $result['channel'] = '#' . $result['channel'];
                    }

                    return $result;
                },
                'test' => function (array $config, array $record): void {
                    $handler = new SlackHandler(
                        // $token
                        $config['apiToken'],
                        // $channel
                        $config['channel'],
                        // $username
                        'CommunityTranslation@This Website',
                        // $useAttachment
                        true,
                        // $iconEmoji
                        null,
                        // $level
                        $config['level']
                    );
                    $lineFormatter = new LineFormatter('%message%');
                    if (method_exists($lineFormatter, 'allowInlineLineBreaks')) {
                        $lineFormatter->allowInlineLineBreaks(true);
                    }
                    $handler->setFormatter($lineFormatter);
                    $handler->handle($record);
                },
            ],
            [
                'handle' => 'telegram',
                'name' => 'Telegram',
                'parser' => function (string $prefix, bool $setError): array {
                    $result = [];
                    $v = $this->request->request->get("{$prefix}botToken");
                    $result['botToken'] = is_string($v) ? trim($v) : '';
                    if ($result['botToken'] === '') {
                        if ($setError) {
                            $this->error->add(t('Please specify the Telegram Bot Token'));
                        }
                    }
                    $v = $this->request->request->get("{$prefix}chatID");
                    $result['chatID'] = is_string($v) ? trim($v) : '';
                    if ($result['chatID'] === '') {
                        if ($setError) {
                            $this->error->add(t('Please specify the Slack Chat ID'));
                        }
                    }

                    return $result;
                },
                'test' => function (array $config, array $record): void {
                    $handler = new TelegramHandler($this->app, $config['botToken'], $config['chatID'], $config['level']);
                    $handler->handle($record);
                },
            ],
        ];
    }

    private function getHandlerLevels(): array
    {
        return [
            ['level' => Logger::DEBUG, 'name' => t('Debug')],
            ['level' => Logger::INFO, 'name' => t('Info')],
            ['level' => Logger::NOTICE, 'name' => t('Notice')],
            ['level' => Logger::WARNING, 'name' => t('Warning')],
            ['level' => Logger::ERROR, 'name' => t('Error')],
            ['level' => Logger::CRITICAL, 'name' => t('Critical')],
            ['level' => Logger::ALERT, 'name' => t('Alert')],
            ['level' => Logger::EMERGENCY, 'name' => t('Emergency')],
        ];
    }

    private function getEditNotifyTo(): array
    {
        if ($this->request->isMethod(Request::METHOD_POST)) {
            return $this->parseNotifyTo(false);
        }
        $config = $this->app->make(Repository::class);
        $result = $config->get('community_translation::cli.notifyTo');

        return is_array($result) ? $result : [];
    }

    private function parseNotify(): bool
    {
        return $this->request->request->get('notify') ? true : false;
    }

    private function parseNotifyTo(bool $setError): array
    {
        $numNotifyTo = $this->request->request->get('numNotifyTo');
        $numNotifyTo = is_numeric($numNotifyTo) ? (int) $numNotifyTo : -1;
        if ($numNotifyTo < 0) {
            if ($setError) {
                $this->error->add(t('Invalid parameter received: %s', 'numNotifyTo'));
            }

            return [];
        }
        $result = [];
        for ($index = 0; $index < $numNotifyTo; $index++) {
            $result[] = $this->parseOneNotifyTo("notifyTo{$index}_", $setError);
        }

        return $result;
    }

    private function parseOneNotifyTo(string $prefix, bool $setError, ?Closure & $testCallback = null): array
    {
        $testCallback = null;
        $result = [];
        $post = $this->request->request;
        $v = $post->get("{$prefix}handler");
        $result['handler'] = is_string($v) ? trim($v) : '';
        $handlerInfo = null;
        if ($result['handler'] !== '') {
            foreach ($this->getHandlers() as $h) {
                if ($h['handle'] === $result['handler']) {
                    $handlerInfo = $h;
                    break;
                }
            }
        }
        if ($handlerInfo === null) {
            if ($setError) {
                $this->error->add(t('Unrecognized notification handler.'));
            }
        } else {
            $testCallback = $handlerInfo['test'];
        }
        $v = $post->get("{$prefix}enabled");
        $result['enabled'] = $v && $v !== 'false' ? true : false;
        $v = $post->get("{$prefix}level");
        $result['level'] = is_numeric($v) ? (int) $v : null;
        if ($result['level'] !== null) {
            $ok = false;
            foreach ($this->getHandlerLevels() as $hl) {
                if ($hl['level'] === $result['level']) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                if ($setError) {
                    $this->error->add('Please specify the handler minimum level');
                }
            }
        }
        $v = $post->get("{$prefix}description");
        $result['description'] = is_string($v) ? trim($v) : '';
        if ($handlerInfo !== null) {
            $setter = $handlerInfo['parser'];
            $result += $setter($prefix, $setError);
        }

        return $result;
    }
}
