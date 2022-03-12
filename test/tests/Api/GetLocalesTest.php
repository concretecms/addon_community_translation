<?php

// declare(strict_types=1);

// namespace CommunityTranslation\Tests\Api;

// use CommunityTranslation\Api as CTApi;
// use CommunityTranslation\Tests\Helper;

// class GetLocalesTest extends Helper\ApiTest
// {
//     public function provideAccessCases(): array
//     {
//         return [
//             [CTApi\UserControl::ACCESSOPTION_EVERYBODY, true],
//             [CTApi\UserControl::ACCESSOPTION_NOBODY, false],
//         ];
//     }

//     /**
//      * @dataProvider provideAccessCases
//      */
//     public function testGetLocales(string $accessOption, bool $success): void
//     {
//         self::$config->save('community_translation::api.access.' . CTApi\EntryPoint\GetLocales::ACCESS_KEY, $accessOption);
//         $apiClient = $this->buildApiClient()->setEntryPoint('locales')->setQueryString('rl=en_US');
//         $error = null;
//         try {
//             $apiClient->exec();
//         } catch (Helper\ApiClientResponseException $x) {
//             $error = $x;
//         }
//         if ($success) {
//             $this->assertNull($error);
//         } else {
//             $this->assertNotNull($error);
//             $this->assertSame($error->getCode(), 401); // HTTP_UNAUTHORIZED
//         }
//     }
// }
