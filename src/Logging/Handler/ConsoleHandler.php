<?php

declare(strict_types=1);

namespace CommunityTranslation\Logging\Handler;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Component\Console\Output\OutputInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class ConsoleHandler extends AbstractProcessingHandler
{
    private OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
        $verbosity = $this->output->getVerbosity();
        if ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
            $level = Logger::DEBUG;
        } elseif ($verbosity >= OutputInterface::VERBOSITY_NORMAL) {
            $level = Logger::INFO;
        } else {
            $level = Logger::ERROR;
        }
        parent::__construct($level);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Monolog\Handler\AbstractHandler::getDefaultFormatter()
     */
    protected function getDefaultFormatter()
    {
        $formatter = new LineFormatter("[%datetime%] %level_name%: %message%\n");
        if (method_exists($formatter, 'allowInlineLineBreaks')) {
            $formatter->allowInlineLineBreaks(true);
        }

        return $formatter;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Monolog\Handler\AbstractProcessingHandler::write()
     */
    protected function write(array $record)
    {
        $this->output->write((string) $record['formatted'], false, $this->output->getVerbosity());
    }
}
