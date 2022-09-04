<?php

namespace Console\App\Service\Github;

use Github\Api\GitData;
use Github\Api\GraphQL;
use Github\Api\Issue;
use Github\Api\Organization;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Github\Api\Search;
use Github\Client;

class GithubTypedEndpointProvider
{
    const GITHUB_API_ENDPOINT_REPO = 'repo';
    const GITHUB_API_ENDPOINT_PULL_REQUEST = 'pull_request';
    const GITHUB_API_ENDPOINT_GIT_DATA = 'gitData';
    const GITHUB_API_ENDPOINT_ISSUE = 'issue';
    const GITHUB_API_ENDPOINT_GRAPHQL = 'graphql';
    const GITHUB_API_ENDPOINT_SEARCH = 'search';
    const GITHUB_API_ENDPOINT_ORGANIZATION = 'organization';

    public function getRepoEndpoint(Client $client): Repo
    {
        /**
         * @var Repo $repo
         */
        $repo = $client->api(self::GITHUB_API_ENDPOINT_REPO);

        return $repo;
    }

    public function getPullRequestEndpoint(Client $client): PullRequest
    {
        /**
         * @var PullRequest $pullRequest
         */
        $pullRequest = $client->api(self::GITHUB_API_ENDPOINT_PULL_REQUEST);

        return $pullRequest;
    }

    public function getGitDataEndpoint(Client $client): GitData
    {
        /**
         * @var GitData $gitData
         */
        $gitData = $client->api(self::GITHUB_API_ENDPOINT_GIT_DATA);

        return $gitData;
    }

    public function getIssueEndpoint(Client $client): Issue
    {
        /**
         * @var Issue $issue
         */
        $issue = $client->api(self::GITHUB_API_ENDPOINT_ISSUE);

        return $issue;
    }

    public function getGraphQLEndpoint(Client $client): GraphQL
    {
        /**
         * @var GraphQL $graphql
         */
        $graphql = $client->api(self::GITHUB_API_ENDPOINT_GRAPHQL);

        return $graphql;
    }

    public function getSearchEndpoint(Client $client): Search
    {
        /**
         * @var Search $search
         */
        $search = $client->api(self::GITHUB_API_ENDPOINT_SEARCH);

        return $search;
    }

    public function getOrganizationEndpoint(Client $client): Organization
    {
        /**
         * @var Organization $organization
         */
        $organization = $client->api(self::GITHUB_API_ENDPOINT_ORGANIZATION);

        return $organization;
    }
}
