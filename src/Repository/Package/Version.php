<?php

declare(strict_types=1);

namespace CommunityTranslation\Repository\Package;

use CommunityTranslation\Entity\Package;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;

defined('C5_EXECUTE') or die('Access Denied.');

class Version extends EntityRepository
{
    /**
     * Find a version given the package handle and the version.
     */
    public function findByHandleAndVersion(string $packageHandle, string $packageVersion): ?Package\Version
    {
        if ($packageHandle === '' || $packageVersion === '') {
            return null;
        }
        $query = $this->createQueryBuilder('v')
            ->innerJoin(Package::class, 'p', Expr\Join::WITH, 'v.package = p.id')
            ->where('p.handle = :handle')->setParameter('handle', $packageHandle)
            ->andWhere('v.version = :version')->setParameter('version', $packageVersion)
            ->setMaxResults(1)
            ->getQuery()
        ;
        $list = $query->execute();

        return array_pop($list);
    }
}
