<?php

declare(strict_types=1);

use Concrete\Core\Package\PackageService;
use PHPUnit\TextUI\Command;

/**
 * @var Symfony\Component\Console\Input\InputInterface $input
 * @var Symfony\Component\Console\Output\OutputInterface $output
 * @var array $args
 */

if (!class_exists(Command::class)) {
    $output->write('<error>Unable to find PHPUnit: you need to install the dev dependencies of Concrete.</error>');

    return 1;
}

if (!app()->isInstalled()) {
    $output->write('<error>Concrete must be installed in order to perform tests.</error>');

    return 1;
}

if (!in_array('community_translation', app(PackageService::class)->getInstalledHandles(), true)) {
    $output->write('<error>The CommunityTranslation package must be installed in order to perform tests.</error>');

    return 1;
}

define('CT_ROOT_DIR', rtrim(str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__)), '/'));

chdir(CT_ROOT_DIR);

spl_autoload_register(
    static function (string $className): void {
        if (strpos($className, 'CommunityTranslation\\Tests\\') !== 0) {
            return;
        }
        $relPath = str_replace('\\', '/', substr($className, strlen('CommunityTranslation\\Tests\\'))) . '.php';
        $absPath = CT_ROOT_DIR . '/test/src/' . $relPath;
        if (is_file($absPath)) {
            require_once $absPath;
        } else {
            $absPath = CT_ROOT_DIR . '/test/tests/' . $relPath;
            if (is_file($absPath)) {
                require_once $absPath;
            }
        }
    },
    true
);

return (new Command())->run($args, false);
