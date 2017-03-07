<?php

spl_autoload_register(
    function ($class) {
        if (strpos($class, 'CommunityTranslation\\Tests\\') !== 0) {
            return;
        }
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'tests' . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen('CommunityTranslation\\Tests'))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
);