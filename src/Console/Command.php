<?php
namespace CommunityTranslation\Console;

use CommunityTranslation\Logging\Handler\ConsoleHandler;
use Concrete\Core\Console\Command as ConcreteCommand;
use Concrete\Core\Support\Facade\Application;
use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SlackHandler;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

abstract class Command extends ConcreteCommand
{
    /**
     * @var \Concrete\Core\Application\Application|null
     */
    protected $app;

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
        $this->app = Application::getFacadeApplication();
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
                                        $lineFormatter->allowInlineLineBreaks(true);
                                        $handler->setFormatter($lineFormatter);
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
}
