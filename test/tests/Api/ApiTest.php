<?php
namespace CommunityTranslation\Tests\Api;

use CommunityTranslation\Tests\Helper\ApiClient;
use PHPUnit_Framework_TestCase;

abstract class ApiTest extends PHPUnit_Framework_TestCase
{
    private static $apiRootURL;

    /**
     * @var ApiClient|null
     */
    protected $apiClient;

    public static function setUpBeforeClass()
    {
        self::$apiRootURL = getenv('CT_TEST_API_ROOTURL') ?: null;
    }

    protected function setUp()
    {
        if (self::$apiRootURL === null) {
            $this->markTestSkipped('CT_TEST_API_ROOTURL environment variable is missing');
        }
        $this->apiClient = new ApiClient(self::$apiRootURL);
    }
}
