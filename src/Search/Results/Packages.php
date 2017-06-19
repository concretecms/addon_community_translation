<?php

namespace CommunityTranslation\Search\Results;

use CommunityTranslation\Search\Lists\Packages as ItemList;
use Concrete\Core\Search\Result\Result as SearchResult;
use Pagerfanta\View\TwitterBootstrap3View;

/**
 * Class that contains the results of the package searches.
 */
class Packages extends SearchResult
{
    /**
     * Initialize the instance.
     *
     * @param ItemList $il
     */
    public function __construct(ItemList $il)
    {
        $this->list = $il;
        $this->pagination = $il->getPagination();
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

    /**
     * Builds the HTML to be used to control the pagination.
     *
     * @return string
     */
    public function getPaginationHTML()
    {
        if ($this->pagination->haveToPaginate()) {
            $view = new TwitterBootstrap3View();
            $me = $this;
            $result = $view->render(
                $this->pagination,
                function ($page) use ($me) {
                    $list = $me->getItemListObject();
                    $result = (string) $me->getBaseURL();
                    $result .= strpos($result, '?') === false ? '?' : '&';
                    $result .= ltrim($list->getQueryPaginationPageParameter(), '&') . '=' . $page;

                    return $result;
                },
                [
                    'prev_message' => tc('Pagination', '&larr; Previous'),
                    'next_message' => tc('Pagination', 'Next &rarr;'),
                    'active_suffix' => '<span class="sr-only">' . tc('Pagination', '(current)') . '</span>',
                ]
            );
        } else {
            $result = '';
        }

        return $result;
    }
}
