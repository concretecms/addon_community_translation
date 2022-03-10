<?php

declare(strict_types=1);

namespace CommunityTranslation\Search\Results\Item;

use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Stats as StatsEntity;
use Concrete\Core\Search\Result\Item;
use Concrete\Core\Search\Result\Result;

defined('C5_EXECUTE') or die('Access Denied.');

class Package extends Item
{
    private PackageEntity $package;

    private bool $mayHaveStats;

    private ?StatsEntity $stats;

    public function __construct(Result $result, array $item)
    {
        $this->package = $item[0];
        if (array_key_exists(1, $item)) {
            $this->mayHaveStats = true;
            $this->stats = $item[1];
        } else {
            $this->mayHaveStats = false;
            $this->stats = null;
        }
    }

    public function getPackage(): PackageEntity
    {
        return $this->package;
    }

    public function mayHaveStats(): bool
    {
        return $this->mayHaveStats;
    }

    public function getStats(): ?StatsEntity
    {
        return $this->stats;
    }
}
