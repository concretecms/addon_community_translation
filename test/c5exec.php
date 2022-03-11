<?php
/**
 * @var Symfony\Component\Console\Input\InputInterface $input
 * @var Symfony\Component\Console\Output\OutputInterface $input
 * @var array $args
 */
$_SERVER['argv'] = array_merge(
    [
        __DIR__ . '/../vendor/phpunit/phpunit/phpunit',
    ],
    $args
);

require $_SERVER['argv'][0];
