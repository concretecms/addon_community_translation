<?php

declare(strict_types=1);

namespace CommunityTranslation\Console;

use Closure;
use CommunityTranslation\Logging\Handler\ConsoleHandler;
use Concrete\Core\Console\Command as ConcreteCommand;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\System\Mutex\MutexInterface;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

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

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::execute()
     */
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
        if ($error instanceof UserMessageException) {
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
            $logger->pushHandler(new PsrHandler(app(LoggerInterface::class), Logger::NOTICE));
        }
        $logger->pushHandler(new ConsoleHandler($output));

        return $logger;
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
