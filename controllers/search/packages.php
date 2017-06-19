<?php

namespace Concrete\Package\CommunityTranslation\Controller\Search;

use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Search\Lists\Packages as SearchList;
use CommunityTranslation\Search\Results\Packages as SearchResult;
use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Search\StickyRequest;

/**
 * Controller for the packages search.
 */
class Packages extends AbstractController
{
    /**
     * Instance of a class that holds the criteria of the last performed search.
     *
     * @var StickyRequest|null
     */
    private $stickyRequest;

    /**
     * Get the instance of a class that holds the criteria of the last performed search.
     *
     * @return StickyRequest
     */
    public function getStickyRequest()
    {
        if ($this->stickyRequest === null) {
            $this->stickyRequest = $this->app->make(StickyRequest::class, ['community_translation.packages']);
        }

        return $this->stickyRequest;
    }

    /**
     * Instance of a class that defines the search list.
     *
     * @var SearchList
     */
    private $searchList;

    /**
     * Get the instance of a class that defines the search list.
     *
     * @return SearchList
     */
    public function getSearchList()
    {
        if ($this->searchList === null) {
            $this->searchList = $this->app->make(SearchList::class, [$this->getStickyRequest()]);
        }

        return $this->searchList;
    }

    /**
     * Instance of a class that defines the search results.
     *
     * @var SearchResult|null
     */
    private $searchResult;

    /**
     * Perform the search.
     *
     * @param bool $reset Should we reset all the previous search criteria?
     */
    public function search($reset = false)
    {
        $stickyRequest = $this->getStickyRequest();
        $searchList = $this->getSearchList();
        if ($reset) {
            $stickyRequest->resetSearchRequest();
        }
        $req = $stickyRequest->getSearchRequest();

        $valn = $this->app->make('helper/validation/numbers');
        /* @var \Concrete\Core\Utility\Service\Validation\Numbers $valn */
        $req = $stickyRequest->getSearchRequest();

        if (isset($req['keywords']) && $req['keywords'] !== '') {
            $searchList->filterByKeywords($req['keywords']);
        }
        if (isset($req['locale']) && is_string($req['locale']) && $req['locale'] !== '') {
            $repo = $this->app->make(LocaleRepository::class);
            $locale = $repo->findApproved($req['locale']);
            if ($locale !== null) {
                $searchList->showLocaleStats($locale);
            }
        }
        $this->searchResult = new SearchResult($searchList);
    }

    /**
     * Get the search result (once the search() method has been called).
     *
     * @return SearchResult|null
     */
    public function getSearchResultObject()
    {
        return $this->searchResult;
    }
}
