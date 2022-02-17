<?php

declare(strict_types=1);

namespace CommunityTranslation\Service;

use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;

defined('C5_EXECUTE') or die('Access Denied.');

class VersionComparer
{
    /**
     * Sort a list of package version entities.
     *
     * @param \CommunityTranslation\Entity\Package\Version[] $packageVersions
     *
     * @return \CommunityTranslation\Entity\Package\Version[]
     */
    public function sortPackageVersionEntities(array $packageVersions, bool $descending = false): array
    {
        $keys = [];
        foreach ($packageVersions as $pv) {
            $keys[$pv->getVersion()] = $pv;
        }
        $sortedKeys = $this->sortPackageVersionStrings(array_keys($keys), $descending);
        $result = [];
        foreach ($sortedKeys as $sortedKey) {
            $result[] = $keys[$sortedKey];
        }

        return $result;
    }

    /**
     * Sort a list of package version strings.
     *
     * @param string[] $packageVersions
     *
     * @return string[]
     */
    public function sortPackageVersionStrings(array $packageVersions, bool $descending = false): array
    {
        usort(
            $packageVersions,
            static function (string $a, string $b) use ($descending): int {
                $aIsDev = strpos($a, PackageVersionEntity::DEV_PREFIX) === 0;
                $aVer = $aIsDev ? substr($a, strlen(PackageVersionEntity::DEV_PREFIX)) : $a;
                $m = null;
                while (preg_match('/^(\.)\.0+$/', $aVer, $m)) {
                    $aVer = $m[1];
                }
                if ($aIsDev) {
                    $aVer .= str_repeat('.' . PHP_INT_MAX, 5);
                }
                $bIsDev = strpos($b, PackageVersionEntity::DEV_PREFIX) === 0;
                $bVer = $bIsDev ? substr($b, strlen(PackageVersionEntity::DEV_PREFIX)) : $b;
                while (preg_match('/^(\.)\.0+$/', $bVer, $m)) {
                    $bVer = $m[1];
                }
                if ($bIsDev) {
                    $bVer .= str_repeat('.' . PHP_INT_MAX, 5);
                }
                $cmp = version_compare($aVer, $bVer);

                return $descending ? -$cmp : $cmp;
            }
        );

        return $packageVersions;
    }

    /**
     * Guess the best package version entity corresponding to a list of entity instances.
     *
     * @param \CommunityTranslation\Entity\Package\Version[] $availableVersions
     *
     * @return \CommunityTranslation\Entity\Package\Version|null Returns null if $availableVersions is empty, an entity instance otherwise
     */
    public function matchPackageVersionEntities(array $availableVersions, string $wantedVersion): ?PackageVersionEntity
    {
        if ($availableVersions === []) {
            return null;
        }
        $keys = [];
        foreach ($availableVersions as $pv) {
            $keys[$pv->getVersion()] = $pv;
        }
        $bestKey = $this->matchPackageVersionStrings(array_keys($keys), $wantedVersion);

        return $keys[$bestKey];
    }

    /**
     * Guess the best package version string corresponding to a list of versions.
     *
     * @param string[] $availableVersions
     *
     * @return string|null Returns null if $availableVersions is empty, a version otherwise
     */
    public function matchPackageVersionStrings(array $availableVersions, string $wantedVersion): ?string
    {
        if ($availableVersions === []) {
            return null;
        }
        $m = null;
        if (preg_match('/^(\d+(?:\.\d+)*)(?:dev|alpha|a|beta|b|rc)/i', $wantedVersion, $m)) {
            $wantedVersionBase = $m[1];
        } else {
            $wantedVersionBase = $wantedVersion;
        }
        $wantedVersionComparable = preg_replace('/^(.*?\d)(\.0+)+$/', '\1', $wantedVersionBase);
        $result = null;
        $availableVersions = $this->sortPackageVersionStrings($availableVersions, false);
        foreach ($availableVersions as $availableVersion) {
            if ($result === null) {
                $result = $availableVersion;
            }
            if (strpos($availableVersion, PackageVersionEntity::DEV_PREFIX) === 0) {
                $availableVersionBase = substr($availableVersion, strlen(PackageVersionEntity::DEV_PREFIX));
                $availableVersionIsDev = true;
            } else {
                $availableVersionBase = $availableVersion;
                $availableVersionIsDev = false;
            }
            $availableVersionComparable = preg_replace('/^(.*?\d)(\.0+)+$/', '\1', $availableVersionBase);
            if ($availableVersionIsDev) {
                $availableVersionIsDev .= str_repeat('.' . PHP_INT_MAX, 6);
            }
            $cmp = version_compare($availableVersionComparable, $wantedVersionComparable);
            if ($cmp > 0) {
                if ($availableVersionIsDev && strpos($availableVersionBase . '.', $wantedVersionBase . '.') === 0) {
                    $result = $availableVersion;
                }
                break;
            }
            $result = $availableVersion;
            if ($cmp === 0) {
                break;
            }
        }

        return $result;
    }
}
