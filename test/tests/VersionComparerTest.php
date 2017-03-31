<?php
namespace CommunityTranslation\Tests;

use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Service\VersionComparer;
use PHPUnit_Framework_TestCase;

class VersionComparerTest extends PHPUnit_Framework_TestCase
{
    protected function getAllVersions()
    {
        $devPrefix = PackageVersionEntity::DEV_PREFIX;

        return [
            '5.5.0',
            '5.5.1',
            '5.5.2',
            '5.5.2.1',
            '5.6.0',
            '5.6.0.1',
            '5.6.0.2',
            '5.6.1',
            '5.6.1.1',
            '5.6.1.2',
            '5.6.3',
            '5.6.3.1',
            '5.6.3.2',
            '5.6.3.3',
            '5.6.3.4',
            "{$devPrefix}5.6",
            '5.7.0',
            '5.7.0.1',
            '5.7.0.3',
            '5.7.1',
            '5.7.2',
            '5.7.2.1',
            '5.7.3',
            '5.7.3.1',
            '5.7.4',
            '5.7.4.1',
            '5.7.4.2',
            '5.7.5.8',
            '5.7.5.9',
            '5.7.5.10',
            '5.7.5.11',
            '5.7.5.12',
            "{$devPrefix}5.7",
            '8.0',
            '8.0.1',
            '8.0.2',
            '8.0.3',
            '8.1.0',
            "{$devPrefix}8",
        ];
    }

    public function versionBestMatchProvider()
    {
        $devPrefix = PackageVersionEntity::DEV_PREFIX;

        return [
            ['1', '5.5.0'],
            ['5.6.99.99', "{$devPrefix}5.6"],
            ['5.7', '5.7.0'],
            ['5.7.0.3', '5.7.0.3'],
            ['5.7.0.99', '5.7.0.3'],
            ['5.7.2.1.2', '5.7.2.1'],
            ['5.7.2.2', '5.7.2.1'],
            ['5.9', "{$devPrefix}5.7"],
            ['8', '8.0'],
            ['8.0.0.1', '8.0'],
            ['8.0.1.0', '8.0.1'],
            ['8.1rc1', '8.1.0'],
            ['8.2', "{$devPrefix}8"],
            ['10', "{$devPrefix}8"],
        ];
    }

    /**
     * @dataProvider versionBestMatchProvider
     */
    public function testVersionBestMatch($wanted, $expected)
    {
        $vm = new VersionComparer();
        $allVersions = $this->getAllVersions();
        $matchedVersion = $vm->matchPackageVersions($allVersions, $wanted);
        $this->assertSame($expected, $matchedVersion);
    }
}
