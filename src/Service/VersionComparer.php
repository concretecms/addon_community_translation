<?php
namespace CommunityTranslation\Service;

use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;

class VersionComparer
{
    /**
     * Sort a list of package version entities.
     *
     * @param \CommunityTranslation\Entity\Package\Version[] $packageVersions
     * @param bool $descending
     *
     * @return \CommunityTranslation\Entity\Package\Version[]
     */
    public function sortPackageVersionEntities(array $packageVersions, $descending = false)
    {
        $keys = [];
        foreach ($packageVersions as $pv) {
            $keys[$pv->getVersion()] = $pv;
        }
        $sortedKeys = $this->sortPackageVersions(array_keys($keys), $descending);
        $result = [];
        foreach ($sortedKeys as $sortedKey) {
            $result[] = $keys[$sortedKey];
        }

        return $result;
    }

    /**
     * Sort a list of package version entities.
     *
     * @param string[] $packageVersions
     * @param bool $descending
     *
     * @return string[]
     */
    public function sortPackageVersions(array $packageVersions, $descending = false)
    {
        usort($packageVersions, function ($a, $b) use ($descending) {
            $aIsDev = (strpos($a, PackageVersionEntity::DEV_PREFIX) === 0);
            $aVer = $aIsDev ? substr($a, strlen(PackageVersionEntity::DEV_PREFIX)) : $a;
            while (preg_match('/^(\.)\.0+$/', $aVer, $m)) {
                $aVer = $m[1];
            }
            if ($aIsDev) {
                $aVer .= str_repeat('.' . PHP_INT_MAX, 5);
            }
            $bIsDev = (strpos($b, PackageVersionEntity::DEV_PREFIX) === 0);
            $bVer = $bIsDev ? substr($b, strlen(PackageVersionEntity::DEV_PREFIX)) : $b;
            while (preg_match('/^(\.)\.0+$/', $bVer, $m)) {
                $bVer = $m[1];
            }
            if ($bIsDev) {
                $bVer .= str_repeat('.' . PHP_INT_MAX, 5);
            }

            return version_compare($aVer, $bVer) * ($descending ? -1 : 1);
        });

        return $packageVersions;
    }

    /**
     * Guess the best package version entity corresponding to a list of entity instances.
     *
     * @param \CommunityTranslation\Entity\Package\Version[] $availableVersions
     * @param string $wantedVersion
     *
     * @return \CommunityTranslation\Entity\Package\Version|null Returns null if $availableVersions is empty, an entity instance otherwise
     */
    public function matchPackageVersionEntities(array $availableVersions, $wantedVersion)
    {
        if (empty($availableVersions)) {
            $result = null;
        } else {
            $keys = [];
            foreach ($availableVersions as $pv) {
                $keys[$pv->getVersion()] = $pv;
            }
            $bestKey = $this->matchVersions(array_keys($keys), $wantedVersion);
            $result = $keys[$bestKey];
        }

        return $result;
    }

    /**
     * Guess the best package version entity corresponding to a list of versions.
     *
     * @param string[] $availableVersions
     * @param string $wantedVersion
     *
     * @return string|null Returns null if $availableVersions is empty, a version otherwise
     */
    public function matchPackageVersions(array $availableVersions, $wantedVersion)
    {
        if (preg_match('/^(\d+(?:\.\d+)*)(?:dev|alpha|a|beta|b|rc)/i', $wantedVersion, $m)) {
            $wantedVersionBase = $m[1];
        } else {
            $wantedVersionBase = $wantedVersion;
        }
        $wantedVersionComparable = preg_replace('/^(.*?\d)(\.0+)+$/', '\1', $wantedVersionBase);
        $result = null;
        $availableVersions = $this->sortPackageVersions($availableVersions, false);
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
