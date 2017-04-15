<?php
namespace CommunityTranslation\Console;

use CommunityTranslation\Logging\Handler\ConsoleHandler;
use CommunityTranslation\Monolog\Handler\TelegramHandler;
use Concrete\Core\Console\Command as ConcreteCommand;
use Concrete\Core\Support\Facade\Application;
use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SlackHandler;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

abstract class Command extends ConcreteCommand
{
    /**
     * @var \Concrete\Core\Application\Application
     */
    protected $app;

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::__construct()
     */
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->app = Application::getFacadeApplication();
    }

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @var OutputInterface|null
     */
    protected $output;

    /**
     * @var InputInterface|null
     */
    protected $input;

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->logger = $this->createLogger($input, $output);
        $error = null;
        try {
            $result = $this->executeWithLogger($input);
        } catch (Exception $x) {
            $error = $x;
        } catch (Throwable $x) {
            $error = $x;
        }
        if ($error !== null) {
            $this->logger->error($this->formatThrowable($error));

            return static::RETURN_CODE_ON_FAILURE;
        }

        return $result;
    }

    /**
     * @param OutputInterface $output
     *
     * @return MonologLogger
     */
    private function createLogger(InputInterface $input, OutputInterface $output)
    {
        $logger = new MonologLogger('CommunityTranslation');
        if (!$input->isInteractive()) {
            $config = $this->app->make('community_translation/config');
            if ($config->get('options.nonInteractiveCLICommands.notify')) {
                $to = $config->get('options.nonInteractiveCLICommands.to');
                if ($to && is_array($to)) {
                    $site = $this->app->make('site')->getSite()->getSiteName();
                    foreach ($to as $toConfig) {
                        $handler = null;
                        if (is_array($toConfig) && isset($toConfig['handler'])) {
                            switch ($toConfig['handler']) {
                                case 'slack':
                                    if (isset($toConfig['apiToken']) && $toConfig['apiToken'] && isset($toConfig['channel']) && $toConfig['channel']) {
                                        $handler = new SlackHandler($toConfig['apiToken'], $toConfig['channel'], 'CommunityTranslation@' . $site);
                                        $lineFormatter = new LineFormatter('%message%');
                                        if (method_exists($lineFormatter, 'allowInlineLineBreaks')) {
                                            $lineFormatter->allowInlineLineBreaks(true);
                                        }
                                        $handler->setFormatter($lineFormatter);
                                    }
                                    break;
                                case 'telegram':
                                    if (isset($toConfig['botToken']) && $toConfig['botToken'] && isset($toConfig['chatID']) && $toConfig['chatID']) {
                                        $handler = new TelegramHandler($toConfig['botToken'], $toConfig['chatID']);
                                    }
                                    break;
                            }
                        }
                        if ($handler !== null) {
                            $handler->setLevel(MonologLogger::ERROR);
                            $logger->pushHandler($handler);
                        }
                    }
                }
            }
        }
        $logger->pushHandler(new ConsoleHandler($output));

        return $logger;
    }

    protected function executeWithLogger()
    {
        throw new Exception('Implement the execute() or the executeWithLogger() method');
    }

    /**
     * @param Exception|Throwable $error
     *
     * @return string
     */
    protected function formatThrowable($error)
    {
        $message = trim($error->getMessage()) . "\n";
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $file = $error->getFile();
            if ($file) {
                $message .= "\nFile:\n$file";
                $line = $error->getLine();
                if ($line) {
                    $message .= ':' . $line;
                }
                $message .= "\n";
            }
            if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
                $trace = $error->getTraceAsString();
                if ($trace) {
                    $message .= "\nTrace:\n$trace\n";
                }
            }
        }

        return $message;
    }

    /**
     * @param string|null $lockHandle
     *
     * @throws Exception
     *
     * @return string
     */
    private function getLockFilename($lockHandle = null)
    {
        $lockHandle = (string) $lockHandle;
        if ($lockHandle === '') {
            $myClass = new ReflectionClass($this);
            $myFilename = $myClass->getFileName();
            $lockHandle = $myClass->getShortName() . '-' . sha1($myClass->getFileName() . DIR_APPLICATION);
        }
        $config = $this->app->make('community_translation/config');
        $tempDir = $config->get('options.tempDir');
        $tempDir = is_string($tempDir) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $tempDir), '/') : '';
        if ($tempDir === '') {
            $fh = $this->app->make('helper/file');
            $tempDir = $fh->getTemporaryDirectory();
            $tempDir = is_string($tempDir) ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $tempDir), '/') : '';
            if ($tempDir === '') {
                throw new Exception(t('Unable to retrieve the temporary directory.'));
            }
        }
        $locksDir = $tempDir . '/command-locks';
        if (!@is_dir($locksDir)) {
            @mkdir($locksDir, DIRECTORY_PERMISSIONS_MODE_COMPUTED, true);
            if (!@is_dir($locksDir)) {
                throw new Exception(t('Unable to create a temporary directory.'));
            }
        }

        return $locksDir . "/$lockHandle.lock";
    }

    private $lockHandles = [];

    /**
     * @param int $maxWaitSeconds
     * @param string|null $lockHandle
     *
     * @return bool
     */
    protected function acquireLock($maxWaitSeconds = 5, $lockHandle = null)
    {
        $lockFile = $this->getLockFilename($lockHandle);
        if (isset($this->lockHandles[$lockFile])) {
            $result = true;
        } else {
            $startTime = time();
            $result = false;
            $fd = null;
            while ($result === false) {
                $fd = @fopen($lockFile, 'w');
                if ($fd !== false) {
                    if (@flock($fd, LOCK_EX | LOCK_NB) === true) {
                        $result = true;
                        break;
                    } else {
                        @fclose($fd);
                    }
                }
                $elapsedTime = time() - $startTime;
                if ($elapsedTime >= $maxWaitSeconds) {
                    break;
                }
                sleep(1);
            }
            if ($result === true) {
                $this->lockHandles[$lockFile] = $fd;
            }
        }

        return $result;
    }

    /**
     * @param string|null $lockHandle
     */
    protected function releaseLock($lockHandle = null)
    {
        $lockFile = $this->getLockFilename($lockHandle);
        if (isset($this->lockHandles[$lockFile])) {
            $fd = $this->lockHandles[$lockFile];
            unset($this->lockHandles[$lockFile]);
            @flock($fd, LOCK_UN);
            @fclose($fd);
            @unlink($lockFile);
        }
    }
}
