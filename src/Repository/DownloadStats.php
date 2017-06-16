<?php

namespace CommunityTranslation\Repository;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use Doctrine\ORM\EntityRepository;

class DownloadStats extends EntityRepository
{
    public function logDownload(LocaleEntity $locale, PackageVersionEntity $packageVersion)
    {
        $cn = $this->getEntityManager()->getConnection();
        $now = $cn->getDatabasePlatform()->getNowExpression();
        $cn->executeQuery(
            "
                INSERT INTO CommunityTranslationDownloadStats
                    (locale, packageVersion, firstDowload, lastDowload, downloadCount)
                    VALUES (?, ?, {$now}, {$now}, 1)
                ON DUPLICATE KEY UPDATE
                    lastDowload = {$now}, downloadCount = downloadCount + 1
            ",
            [
                $locale->getID(),
                $packageVersion->getID(),
            ]
        );
    }
}
