<?php

namespace CommunityTranslation\Repository\Package;

use CommunityTranslation\Entity\Package;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;

class Version extends EntityRepository
{
    /**
     * Get the latest version of a translated package.
     *
     * @param Package $package
     *
     * @return \CommunityTranslation\Entity\Package\Version|null
     */
    public function getLatestVersion(Package $package)
    {
        $result = null;
        foreach ($package->getVersions() as $version) {
            /* @var \CommunityTranslation\Entity\Package\Version $version */
            if (!$version->isDevVersion()) {
                if ($result === null || version_compare($version->getVersion(), $result->getVersion) > 0) {
                    $result = $version;
                }
            }
        }

        return $result;
    }

    /**
     * Find a version given the package handle and the version.
     *
     * @param string $packageHandle
     * @param string $packageVersion
     *
     * @return \CommunityTranslation\Entity\Package\Version|null
     */
    public function findByHandleAndVersion($packageHandle, $packageVersion)
    {
        $result = null;
        $packageHandle = (string) $packageHandle;
        if ($packageHandle !== '') {
            $packageVersion = (string) $packageVersion;
            if ($packageVersion !== '') {
                $list = $this->createQueryBuilder('v')
                    ->innerJoin(Package::class, 'p', Expr\Join::WITH, 'v.package = p.id')
                    ->where('p.handle = :handle')->setParameter('handle', $packageHandle)
                    ->andWhere('v.version = :version')->setParameter('version', $packageVersion)
                    ->setMaxResults(1)
                    ->getQuery()->execute();
                if (!empty($list)) {
                    $result = $list[0];
                }
            }
        }

        return $result;
    }
}
