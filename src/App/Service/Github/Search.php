<?php

namespace Console\App\Service\Github;

use Github\Api\AbstractApi;
use Github\Api\AcceptHeaderTrait;

/**
 * Implement the Search API. To override the default class that not support per_page and page parameters,
 */
class Search extends AbstractApi
{
    use AcceptHeaderTrait;

    /**
     * @var int
     */
    protected $perPage;

    /**
     * @var int
     */
    protected $page;

    /**
     * Search repositories by filter (q).
     *
     * @see https://developer.github.com/v3/search/#search-repositories
     *
     * @param string $q the filter
     * @param string $sort the sort field
     * @param string $order asc/desc
     *
     * @return array list of repositories found
     */
    public function repositories($q, $sort = 'updated', $order = 'desc')
    {
        return $this->get('/search/repositories', ['q' => $q, 'sort' => $sort, 'order' => $order, 'per_page' => $this->getPerPage(), 'page' => $this->getPage()]);
    }

    /**
     * Search issues by filter (q).
     *
     * @see https://developer.github.com/v3/search/#search-issues
     *
     * @param string $q the filter
     * @param string $sort the sort field
     * @param string $order asc/desc
     *
     * @return array list of issues found
     */
    public function issues($q, $sort = 'updated', $order = 'desc')
    {
        return $this->get('/search/issues', ['q' => $q, 'sort' => $sort, 'order' => $order, 'per_page' => $this->getPerPage(), 'page' => $this->getPage()]);
    }

    /**
     * Search code by filter (q).
     *
     * @see https://developer.github.com/v3/search/#search-code
     *
     * @param string $q the filter
     * @param string $sort the sort field
     * @param string $order asc/desc
     *
     * @return array list of code found
     */
    public function code($q, $sort = 'updated', $order = 'desc')
    {
        return $this->get('/search/code', ['q' => $q, 'sort' => $sort, 'order' => $order, 'per_page' => $this->getPerPage(), 'page' => $this->getPage()]);
    }

    /**
     * Search users by filter (q).
     *
     * @see https://developer.github.com/v3/search/#search-users
     *
     * @param string $q the filter
     * @param string $sort the sort field
     * @param string $order asc/desc
     *
     * @return array list of users found
     */
    public function users($q, $sort = 'updated', $order = 'desc')
    {
        return $this->get('/search/users', ['q' => $q, 'sort' => $sort, 'order' => $order, 'per_page' => $this->getPerPage(), 'page' => $this->getPage()]);
    }

    /**
     * Search commits by filter (q).
     *
     * @see https://developer.github.com/v3/search/#search-commits
     *
     * @param string $q the filter
     * @param string $sort the sort field
     * @param string $order sort order. asc/desc
     *
     * @return array
     */
    public function commits($q, $sort = null, $order = 'desc')
    {
        //This api is in preview mode, so set the correct accept-header
        $this->acceptHeaderValue = 'application/vnd.github.cloak-preview';

        return $this->get('/search/commits', ['q' => $q, 'sort' => $sort, 'order' => $order, 'per_page' => $this->getPerPage(), 'page' => $this->getPage()]);
    }

    /**
     * Search commits by filter (q).
     *
     * @see https://developer.github.com/v3/search/#search-topics
     *
     * @param string $q the filter
     *
     * @return array
     */
    public function topics($q)
    {
        //This api is in preview mode, so set the correct accept-header
        $this->acceptHeaderValue = 'application/vnd.github.mercy-preview+json';

        return $this->get('/search/topics', ['q' => $q]);
    }

    /**
     * @return int
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * @param int $perPage
     */
    public function setPerPage(int $perPage): void
    {
        $this->perPage = $perPage;
    }

    /**
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @param int $page
     */
    public function setPage(int $page): void
    {
        $this->page = $page;
    }
}
