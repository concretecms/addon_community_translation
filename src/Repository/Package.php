<?php

declare(strict_types=1);

namespace CommunityTranslation\Repository;

use CommunityTranslation\Entity\Package as PackageEntity;
use Doctrine\ORM\EntityRepository;

defined('C5_EXECUTE') or die('Access Denied.');

class Package extends EntityRepository
{
    public function getByHandle(string $handle, bool $lookForAlias = true): ?PackageEntity
    {
        $package = $this->findOneBy(['handle' => $handle]);
        if ($package === null && $lookForAlias === true) {
            $alias = $this->getEntityManager()->getRepository(PackageEntity\Alias::class)->findOneBy(['handle' => $handle]);
            if ($alias !== null) {
                $package = $alias->getPackage();
            }
        }

        return $package;
    }
}
