<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\Search;

use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Search\Lists\Packages as SearchList;
use CommunityTranslation\Search\Results\Packages as SearchResult;
use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Search\StickyRequest;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Controller for the packages search.
 */
class Packages extends AbstractController
{
    /**
     * Instance of a class that holds the criteria of the last performed search.
     */
    private ?StickyRequest $stickyRequest = null;

    /**
     * Instance of a class that defines the search list.
     *
     * @var
     */
    private ?SearchList $searchList = null;

    /**
     * Instance of a class that defines the search results.
     */
    private ?SearchResult $searchResult = null;

    /**
     * Get the instance of a class that holds the criteria of the last performed search.
     */
    public function getStickyRequest(): StickyRequest
    {
        if ($this->stickyRequest === null) {
            $this->stickyRequest = $this->app->make(StickyRequest::class, ['namespace' => 'community_translation.packages']);
        }

        return $this->stickyRequest;
    }

    /**
     * Get the instance of a class that defines the search list.
     */
    public function getSearchList(): SearchList
    {
        if ($this->searchList === null) {
            $this->searchList = $this->app->make(SearchList::class, ['req' => $this->getStickyRequest()]);
        }

        return $this->searchList;
    }

    /**
     * Perform the search.
     *
     * @param bool $reset Should we reset all the previous search criteria?
     */
    public function search(bool $reset = false): SearchResult
    {
        $stickyRequest = $this->getStickyRequest();
        $searchList = $this->getSearchList();
        if ($reset) {
            $stickyRequest->resetSearchRequest();
        }
        $req = $stickyRequest->getSearchRequest();

        if (is_string($req['keywords'] ?? null) && $req['keywords'] !== '') {
            $searchList->filterByKeywords($req['keywords']);
        }
        if (is_string($req['locale'] ?? null) && $req['locale'] !== '') {
            $repo = $this->app->make(LocaleRepository::class);
            $locale = $repo->findApproved($req['locale']);
            if ($locale !== null) {
                $searchList->showStatsForLocale($locale);
            }
        }
        $this->searchResult = $this->app->make(SearchResult::class, ['list' => $searchList]);

        return $this->searchResult;
    }

    /**
     * Get the search result (once the search() method has been called).
     */
    public function getSearchResultObject(): ?SearchResult
    {
        return $this->searchResult;
    }
}
