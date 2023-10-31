<?php

declare(strict_types=1);

namespace CommunityTranslation\Console;

use Closure;
use CommunityTranslation\Logging\Handler\ConsoleHandler;
use CommunityTranslation\Monolog\Handler\TelegramHandler;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Console\Command as ConcreteCommand;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\System\Mutex\MutexInterface;
use Generator;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SlackHandler;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Symfony\Component\Console\Input\InputInterface;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;

defined('C5_EXECUTE') or die('Access Denied.');

abstract class Command extends ConcreteCommand
{
    protected LoggerInterface $logger;

    /**
     * Override this method returning a string if this command can be execute more than once at a time.
     */
    protected function getMutexKey(): string
    {
        $chunks = explode('\\', get_class($this));

        return 'CommunityTranslation.' . array_pop($chunks);
    }

    final protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger = $this->createLogger($input, $output);
        $mutexReleaser = null;
        try {
            $mutexReleaser = $this->acquireMutex();
            $rc = parent::execute($input, $output);
        } catch (Throwable $error) {
            $this->logger->error($this->formatThrowable($error));
            $rc = self::FAILURE;
        } finally {
            if ($mutexReleaser !== null) {
                $mutexReleaser();
            }
        }

        return $rc;
    }

    protected function formatThrowable(Throwable $error): string
    {
        $message = trim($error->getMessage()) . "\n";
        if ($error instanceof UserMessageException || $this->output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
            return $message;
        }
        $file = (string) $error->getFile();
        if ($file !== '') {
            $message .= "\nFile:\n{$file}";
            $line = $error->getLine();
            if ($line) {
                $message .= ":{$line}";
            }
            $message .= "\n";
        }
        if ($this->output->getVerbosity() < OutputInterface::VERBOSITY_VERY_VERBOSE) {
            return $message;
        }
        $trace = (string) $error->getTraceAsString();
        if ($trace) {
            $message .= "\nTrace:\n{$trace}\n";
        }

        return $message;
    }

    private function createLogger(InputInterface $input, OutputInterface $output): LoggerInterface
    {
        $logger = new MonologLogger('CommunityTranslation');
        if (!$input->isInteractive()) {
            foreach ($this->buildNonInteractiveLogHandlers() as $handler) {
                $logger->pushHandler($handler);
            }
        }
        $logger->pushHandler(new ConsoleHandler($output));

        return $logger;
    }

    /**
     * @return \Generator<\Monolog\Handler\HandlerInterface>
     */
    private function buildNonInteractiveLogHandlers(): Generator
    {
        $app = app();
        yield new PsrHandler($app->make(LoggerInterface::class), Logger::NOTICE);
        $config = $app->make(Repository::class);
        if (!$config->get('community_translation::cli.notify')) {
            return;
        }
        $to = $config->get('community_translation::cli.notifyTo');
        if (!is_array($to) || $to === []) {
            return;
        }
        $site = $app->make('site')->getSite()->getSiteName();
        foreach ($to as $toConfig) {
            if (!is_array($toConfig) || empty($toConfig['enabled']) || !is_string($toConfig['handler'] ?? null)) {
                continue;
            }
            $level = is_int($toConfig['level'] ?? null) ? $toConfig['level'] : MonologLogger::ERROR;
            switch ($toConfig['handler']) {
                case 'slack':
                    if (is_string($toConfig['apiToken'] ?? null) && $toConfig['apiToken'] !== '' && is_string($toConfig['channel'] ?? null) && $toConfig['channel'] !== '') {
                        $handler = new SlackHandler(
                            // $token
                            $toConfig['apiToken'],
                            // $channel
                            $toConfig['channel'],
                            // $username
                            'CommunityTranslation@' . $site,
                            // $useAttachment
                            true,
                            // $iconEmoji
                            null,
                            // $level
                            $level
                        );
                        $lineFormatter = new LineFormatter('%message%');
                        if (method_exists($lineFormatter, 'allowInlineLineBreaks')) {
                            $lineFormatter->allowInlineLineBreaks(true);
                        }
                        $handler->setFormatter($lineFormatter);
                        yield $handler;
                    }
                    break;
                case 'telegram':
                    if (is_string($toConfig['botToken'] ?? null) && $toConfig['botToken'] !== '' && is_scalar($toConfig['chatID'] ?? []) && (string) $toConfig['chatID'] !== '') {
                        yield new TelegramHandler($app, $toConfig['botToken'], $toConfig['chatID'], $level);
                    }
                    break;
            }
        }
    }

    private function acquireMutex(): ?Closure
    {
        $key = $this->getMutexKey();
        if ($key === '') {
            return null;
        }
        $mutex = app(MutexInterface::class);
        $mutex->acquire($key);

        return static function () use ($mutex, $key): void {
            $mutex->release($key);
        };
    }
}
