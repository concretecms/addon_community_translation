<?php

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Service\IPControlLog;
use DateTime;
use Exception;
use Symfony\Component\Console\Input\InputArgument;

class RemoveLoggedIPAddressesCommand extends Command
{
    protected function configure()
    {
        $errExitCode = static::RETURN_CODE_ON_FAILURE;
        $this
            ->setName('ct:remove-logged-ips')
            ->addArgument('days', InputArgument::REQUIRED, 'Remove the IP addresses older that this number of days')
            ->setDescription('Remove from the database the IP addresses that are logged for controlling the rate limits')
            ->setHelp(<<<EOT
Returns codes:
  0 operation completed successfully
  $errExitCode errors occurred
EOT
            )
        ;
    }

    protected function executeWithLogger()
    {
        $valn = $this->app->make('helper/validation/numbers');
        $days = null;
        if ($valn->integer($this->input->getArgument('days'))) {
            $days = (int) $this->input->getArgument('days');
        }
        if ($days === null || $days < 0) {
            throw new Exception('Please specify a non negative integer for the days argument');
        }
        $ipControlLog = $this->app->make(IPControlLog::class);
        $numDeleted = $ipControlLog->clearVisits(new DateTime("-$days days"));
        $this->logger->info("Number of deleted records: $numDeleted");
    }
}
