<?php

declare(strict_types=1);

namespace CommunityTranslation\Repository;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use Doctrine\ORM\EntityRepository;

defined('C5_EXECUTE') or die('Access Denied.');

class DownloadStats extends EntityRepository
{
    public function logDownload(LocaleEntity $locale, PackageVersionEntity $packageVersion): void
    {
        $cn = $this->getEntityManager()->getConnection();
        $now = $cn->getDatabasePlatform()->getNowExpression();
        $cn->executeStatement(
            <<<EOT
INSERT INTO CommunityTranslationDownloadStats
    (locale, packageVersion, firstDowload, lastDowload, downloadCount)
    VALUES (?, ?, {$now}, {$now}, 1)
ON DUPLICATE KEY UPDATE
    lastDowload = {$now}, downloadCount = downloadCount + 1
EOT
            ,
            [
                $locale->getID(),
                $packageVersion->getID(),
            ]
        );
    }
}
