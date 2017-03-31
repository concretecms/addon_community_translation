<?php

spl_autoload_register(
    function ($class) {
        if (strpos($class, 'CommunityTranslation\\Tests\\') === 0) {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'tests' . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen('CommunityTranslation\\Tests'))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        } elseif (strpos($class, 'CommunityTranslation\\') === 0) {
            $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen('CommunityTranslation'))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    }
);
