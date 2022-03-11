<?php

declare(strict_types=1);

namespace CommunityTranslation\Tests\Helper;

use PHPUnit\Framework\TestCase;

abstract class ApiTest extends TestCase
{
    protected ApiClient $apiClient;

    private static string $apiRootURL;

    private static ?array $config;

    /**
     * {@inheritdoc}
     *
     * @see \PHPUnit\Framework\TestCase::setUpBeforeClass()
     */
    public static function setUpBeforeClass(): void
    {
        self::$apiRootURL = rtrim((string) ($_ENV['CT_TEST_API_ROOTURL'] ?? ''), '/');
        if (self::$apiRootURL === '') {
            self::$apiRootURL = rtrim((string) getenv('CT_TEST_API_ROOTURL'), '/');
            if (self::$apiRootURL === '') {
                self::markTestSkipped('CT_TEST_API_ROOTURL environment variable is missing: set it to the URL of a running concrete5 instance with Community Translation installed.');
            }
        }
        $appDirectory = rtrim(
            str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                ($_ENV['CT_TEST_APP_DIR'] ?? false) ?: getenv('CT_TEST_APP_DIR') ?: (CT_ROOT_DIR . '/../../application')
            ),
            '/'
        );
        if (!is_dir($appDirectory)) {
            self::markTestSkipped('Unable to detect the application directory. You can set it via the CT_TEST_APP_DIR environment variable.');
        }
        self::$config = null;
        if (is_dir($appDirectory)) {
            $configFiles = array_filter(
                scandir(CT_ROOT_DIR . '/config'),
                static function (string $path): bool {
                    return (bool) preg_match('/^[^.].*\.php$/', $path);
                }
            );
            $configDirs = [
                CT_ROOT_DIR . '/config',
                "{$appDirectory}/config/generated_overrides/community_translation",
                "{$appDirectory}/config/community_translation",
            ];
            foreach ($configFiles as $configFile) {
            	$baseName = basename($configFile, '.php');
                foreach ($configDirs as $configDir) {
                    $configFullPath = "{$configDir}/{$configFile}";
                    if (is_file($configFullPath)) {
                        self::mergeConfig($baseName, require $configFullPath);
                    }
                }
            }
        }
        if (self::$config === null) {
            self::markTestSkipped('Unable to find any of the configuration files');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp(): void
    {
        $this->apiClient = new ApiClient(self::$apiRootURL . self::getConfigValue('paths.api'));
    }

    protected static function getConfigValue(string $key, $default = null)
    {
        $result = self::$config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($result) || !array_key_exists($segment, $result)) {
                return $default;
            }
            $result = $result[$segment];
        }

        return $result;
    }

    private static function mergeConfig(string $baseName, array $newConfig): void
    {
        $config = [$baseName => []];
        foreach ($newConfig as $newConfigKey => $newConfigValue) {
            $config[$baseName][$newConfigKey] = $newConfigValue;
        }
        if (self::$config === null) {
            self::$config = $config;
        } else {
            self::$config = array_replace_recursive(self::$config, $config);
        }
    }
}
