<?php

declare(strict_types=1);

namespace CommunityTranslation\Search\Results;

use CommunityTranslation\Search\Lists\Packages as ItemList;
use Concrete\Core\Search\Pagination\PaginationFactory;
use Concrete\Core\Search\Result\Result as SearchResult;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Class that contains the results of the package searches.
 */
class Packages extends SearchResult
{
    public function __construct(ItemList $list, PaginationFactory $paginationFactory)
    {
        $this->list = $list;
        $this->pagination = $paginationFactory->deliverPaginationObject($list, $list->createPaginationObject());
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Search\Result\Result::getItemDetails()
     */
    public function getItemDetails($item)
    {
        return new Item\Package($this, $item);
    }
}
