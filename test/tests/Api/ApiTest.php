<?php
namespace CommunityTranslation\Tests\Api;

use CommunityTranslation\Tests\Helper\ApiClient;
use PHPUnit_Framework_TestCase;

abstract class ApiTest extends PHPUnit_Framework_TestCase
{
    private static $apiRootURL;

    private static $config;

    /**
     * @var ApiClient|null
     */
    protected $apiClient;

    public static function setUpBeforeClass()
    {
        self::$apiRootURL = rtrim((string) getenv('CT_TEST_API_ROOTURL'), '/') ?: null;
        $packageDirectory = __DIR__ . '/../../..';
        $appDirectory = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', getenv('CT_TEST_APP_DIR') ?: ($packageDirectory . '/../../application')), '/');
        self::$config = null;
        if (is_dir($appDirectory)) {
            $configFiles = [
                "$packageDirectory/config/options.php",
                "$appDirectory/config/generated_overrides/community_translation/options.php",
                "$appDirectory/config/community_translation/options.php",
            ];
            foreach ($configFiles as $configFile) {
                if (is_file($configFile)) {
                    $cfg = require $configFile;
                    if (self::$config === null) {
                        self::$config = $cfg;
                    } else {
                        self::$config = array_replace_recursive(self::$config, $cfg);
                    }
                }
            }
        }
    }

    protected static function getConfigValue($key, $default = null)
    {
        if (isset(self::$config[$key])) {
            $result = self::$config[$key];
        } else {
            $result = self::$config;
            foreach (explode('.', $key) as $segment) {
                if (is_array($result) && array_key_exists($segment, $result)) {
                    $result = $result[$segment];
                } else {
                    $result = $default;
                    break;
                }
            }
        }

        return $result;
    }

    protected function setUp()
    {
        if (self::$apiRootURL === null) {
            $this->markTestSkipped('CT_TEST_API_ROOTURL environment variable is missing: set it to the URL of a running concrete5 instance with Community Translation installed');
        }
        if (self::$config === null) {
            $this->markTestSkipped('Unable to find the application directory. You can set it with the CT_TEST_APP_DIR environment variable');
        }
        $this->apiClient = new ApiClient(self::$apiRootURL . self::getConfigValue('api.entryPoint'));
    }
}
