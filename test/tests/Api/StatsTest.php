<?php
namespace CommunityTranslation\Tests\Api;

use CommunityTranslation\Tests\Helper\ApiClientResponseException;
use Symfony\Component\HttpFoundation\Request;

class StatsTest extends ApiTest
{
    public function testGetLocales()
    {
        $this->apiClient->setEntryPoint('locales')->setQueryString('rl=en_US');
        $error = null;
        try {
            $this->apiClient->exec();
        } catch (ApiClientResponseException $x) {
            $error = $x;
        }
        if ($this->getConfigValue('api.access.getLocales') === 'everybody') {
            $this->assertNull($error);
        } else {
            $this->assertNotNull($error);
            $this->assertSame($error->getCode(), 401); // HTTP_UNAUTHORIZED
            $token = getenv('CT_TEST_TOKEN_GETLOCALES');
            if (!$token) {
                $this->markTestSkipped('Specify a CT_TEST_TOKEN_GETLOCALES environment variable with a token valid for /locales API request');
            }
            $this->apiClient->setToken($token)->exec();
        }
        $this->assertSame(200, $this->apiClient->getLastResponseCode());
        $this->assertSame('application/json', $this->apiClient->getLastResponseType());
        $data = $this->apiClient->getLastResponseData();
        $this->assertInternalType('array', $data);
        if (empty($data)) {
            $this->markTestIncomplete('No locales defined in CommunityTranslation');
        }
        foreach ($data as $key => $value) {
            $this->assertInternalType('int', $key);
            $this->assertInternalType('array', $value);
            $this->assertArrayHasKey('id', $value);
            $this->assertArrayHasKey('name', $value);
            $this->assertArrayHasKey('nameLocalized', $value);
        }
    }

    public function testGetAvailablePackages()
    {
        $this->apiClient->setEntryPoint('packages')->setQueryString('rl=en_US');
        $error = null;
        try {
            $this->apiClient->exec();
        } catch (ApiClientResponseException $x) {
            $error = $x;
        }
        if ($this->getConfigValue('api.access.getPackages') === 'everybody') {
            $this->assertNull($error);
        } else {
            $this->assertNotNull($error);
            $this->assertSame($error->getCode(), 401); // HTTP_UNAUTHORIZED
            $token = getenv('CT_TEST_TOKEN_GETPACKAGES');
            if (!$token) {
                $this->markTestSkipped('Specify a CT_TEST_TOKEN_GETPACKAGES environment variable with a token valid for /packages API request');
            }
            $this->apiClient->setToken($token)->exec();
        }
        $this->assertSame(200, $this->apiClient->getLastResponseCode());
        $this->assertSame('application/json', $this->apiClient->getLastResponseType());
        $data = $this->apiClient->getLastResponseData();
        $this->assertInternalType('array', $data);
        if (empty($data)) {
            $this->markTestIncomplete('No packages defined in CommunityTranslation');
        }
        foreach ($data as $key => $value) {
            $this->assertInternalType('int', $key);
            $this->assertInternalType('array', $value);
            $this->assertArrayHasKey('handle', $value);
            $this->assertArrayHasKey('name', $value);
        }
    }
}
