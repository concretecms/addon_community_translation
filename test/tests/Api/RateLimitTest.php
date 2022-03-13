<?php

declare(strict_types=1);

namespace CommunityTranslation\Tests\Api;

use CommunityTranslation\Api as CTApi;
use CommunityTranslation\Tests\Helper;
use Concrete\Core\Http\Response;

class RateLimitTest extends Helper\ApiTest
{
    public function testNoAccess(): void
    {
        $apiClient = $this->buildApiClient()->setEntryPoint('rate-limit')->setQueryString('rl=en_US');
        self::$config->save('community_translation::api.access.' . CTApi\EntryPoint\GetRateLimit::ACCESS_KEY, CTApi\UserControl::ACCESSOPTION_NOBODY);
        $error = null;
        try {
            $apiClient->exec();
        } catch (Helper\ApiClientResponseException $x) {
            $error = $x;
        }
        $this->assertNotNull($error);
        $this->assertSame($error->getCode(), Response::HTTP_UNAUTHORIZED);
    }

    public function testEverybodyAccessWithoutRateLimit(): void
    {
        $apiClient = $this->buildApiClient()->setEntryPoint('rate-limit')->setQueryString('rl=en_US');
        self::$config->save('community_translation::api.access.' . CTApi\EntryPoint\GetRateLimit::ACCESS_KEY, CTApi\UserControl::ACCESSOPTION_EVERYBODY);
        $error = null;
        try {
            $apiClient->exec();
        } catch (Helper\ApiClientResponseException $x) {
            $error = $x;
        }
        $this->assertNull($error);
        $this->assertNull($apiClient->getLastResponseData());
    }

    public function testEverybodyAccessWithRateLimit(): void
    {
        $apiClient = $this->buildApiClient()->setEntryPoint('rate-limit')->setQueryString('rl=en_US');
        self::$config->save('community_translation::api.access.' . CTApi\EntryPoint\GetRateLimit::ACCESS_KEY, CTApi\UserControl::ACCESSOPTION_EVERYBODY);
        $maxEvents = 3;
        self::$ipAccessControlRateLimit
            ->setEnabled(true)
            ->setMaxEvents($maxEvents)
            ->setTimeWindow(30)
            ->setBanDuration(600)
        ;
        self::$em->flush();
        for ($cycle = 1; $cycle <= $maxEvents + 2; $cycle++) {
            $error = null;
            try {
                $apiClient->exec();
            } catch (Helper\ApiClientResponseException $x) {
                $error = $x;
            }
            if ($cycle > $maxEvents) {
                $this->assertNotNull($error);
                $this->assertSame($error->getCode(), Response::HTTP_TOO_MANY_REQUESTS);
                $this->assertRegExp('/You reached the API rate limit/i', $error->getMessage());
                continue;
            }
            $this->assertNull($error);
            $actual = $apiClient->getLastResponseData();
            $this->assertIsArray($actual);
            ksort($actual);
            $expected = [
                'currentCounter' => $cycle,
                'maxRequests' => 3,
                'timeWindow' => 30,
            ];
            ksort($expected);
            $this->assertSame($actual, $expected);
        }
    }
}
