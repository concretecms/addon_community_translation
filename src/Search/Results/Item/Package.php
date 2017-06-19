<?php

namespace CommunityTranslation\Search\Results\Item;

use Concrete\Core\Search\Result\Item;
use Concrete\Core\Search\Result\Result;

class Package extends Item
{
    /**
     * @var \CommunityTranslation\Entity\Package
     */
    protected $package;

    /**
     * 
     * @var \CommunityTranslation\Entity\Stats|false|null
     */
    protected $stats;

    public function __construct(Result $result, array $item)
    {
        $this->package = $item[0];
        $this->stats = isset($item[1]) ? $item[1] : null;
    }

    /**
     * @return \CommunityTranslation\Entity\Package
     */
    public function getPackage()
    {
        return $this->package;
    }
    
    /**
     * @return bool
     */
    public function hasStats()
    {
        return $this->stats !== null;
    }

    /**
     * @return \CommunityTranslation\Entity\Stats|null
     */
    public function getStats()
    {
        return $this->stats ?: null;
    }
}
