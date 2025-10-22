<?php

namespace Console\App\Service;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Console\App\Service\Github\Filters;
use Exception;
use Github\Client;
use Github\Exception\RuntimeException;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class Github
{
    /**
     * @var Client
     */
    protected $client;

    private const MAINTAINER_MEMBERS = [
        'atomiix',
        'eternoendless',
        'jolelievre',
        'kpodemski',
        'matks',
        'matthieu-rolland',
        'NeOMakinG',
        'PululuK',
        'sowbiba',
    ];

    private const SOFTWARE_DEVELOPERS_IN_TEST_MEMBERS = [
        'boubkerbribri',
        'cfarhani06',
        'Progi1984',
        'nesrineabdmouleh',
        'SD1982',
    ];

    public const REPOSITORIES_IGNORED = [
        'PrestaShop-1.6',
    ];

    public const PULL_REQUEST_STATE_APPROVED = 'APPROVED';
    public const PULL_REQUEST_STATE_REQUEST_CHANGES = 'REQUEST_CHANGES';
    public const PULL_REQUEST_STATE_COMMENT = 'COMMENT';

    public function __construct(?string $ghToken = null)
    {
        $filesystemAdapter = new Local(__DIR__ . '/../../../var/');
        $filesystem = new Filesystem($filesystemAdapter);
        $pool = new FilesystemCachePool($filesystem);

        $this->client = new Client();
        $this->client->addCache($pool);

        if (!empty($ghToken)) {
            $this->client->authenticate($ghToken, null, Client::AUTH_ACCESS_TOKEN);
        }
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @return array<array<mixed>>
     */
    public function search(Github\Query $graphQLQuery): array
    {
        $result = [];
        do {
            $resultPage = $this->apiSearchGraphQL((string) $graphQLQuery);
            $result = array_merge($result, $resultPage['data']['search']['edges']);
            if (!empty($resultPage['data']['search']['pageInfo']['endCursor'])) {
                $graphQLQuery->setPageAfter($resultPage['data']['search']['pageInfo']['endCursor']);
            }
        } while ($resultPage['data']['search']['pageInfo']['hasNextPage']);

        return $result;
    }

    public function countSearch(Github\Query $graphQLQuery): int
    {
        $resultPage = $this->apiSearchGraphQL((string) $graphQLQuery);

        return $resultPage['data']['search']['issueCount'];
    }

    public function countRepoFiles(string $org, string $repository, ?string $path = null): int
    {
        $numFiles = 0;

        $arrayPath = $this->client->api('repo')->contents()->show($org, $repository, $path);
        foreach ($arrayPath as $itemPath) {
            if ($itemPath['type'] == 'file') {
                ++$numFiles;
                continue;
            }
            if ($itemPath['type'] == 'dir') {
                $numFiles += $this->countRepoFiles($org, $repository, $itemPath['path']);
                continue;
            }
        }

        return $numFiles;
    }

    public function getRepoFile(string $org, string $repository, string $path): string
    {
        return $this->client->api('repo')->contents()->download($org, $repository, $path);
    }

    public function hasRepoFile(string $org, string $repository, string $path): bool
    {
        return $this->client->api('repo')->contents()->exists($org, $repository, $path);
    }

    public function getLinkedIssue(array $pullRequest)
    {
        // Linked Issue
        preg_match('#Fixes\s\#([0-9]{1,5})#', $pullRequest['body'], $matches);
        $issueId = !empty($matches) && !empty($matches[1]) ? $matches[1] : null;
        if (empty($issueId)) {
            preg_match('#Fixes\sissue\s\#([0-9]{1,5})#', $pullRequest['body'], $matches);
            $issueId = !empty($matches) && !empty($matches[1]) ? $matches[1] : null;
        }
        if (empty($issueId)) {
            preg_match('#Fixes\shttps:\/\/github.com\/PrestaShop\/PrestaShop\/issues\/([0-9]{1,5})#', $pullRequest['body'], $matches);
            $issueId = !empty($matches) && !empty($matches[1]) ? $matches[1] : null;
        }
        try {
            $issue = is_null($issueId) ? null : $this->client->api('issue')->show('PrestaShop', 'PrestaShop', $issueId);
        } catch (RuntimeException $e) {
            if ($e->getMessage() == 'Not Found') {
                throw new Exception(sprintf('Cannot find linked issue #%s in PR #%s', $issueId, $pullRequest['number']));
            } else {
                throw $e;
            }
        }

        // API Alert
        if (isset($pullRequest['_links'])) {
            var_dump('PR #' . $pullRequest['number'] . ' has _links in its API');
        }

        return $issue;
    }

    public function getRepoBranches(string $org, string $repository, bool $withDevelop = true): array
    {
        $query = '{
            repository(owner: "' . $org . '", name: "' . $repository . '") {
                refs(refPrefix: "refs/heads/", first: 100) {
                  nodes {
                    name
                  }
                }
            }
          }';

        $repositoryInfoGraphQL = $this->apiSearchGraphQL($query);
        $branches = [];
        foreach ($repositoryInfoGraphQL['data']['repository']['refs']['nodes'] as $node) {
            $name = $node['name'];
            if (!$withDevelop && $name === 'develop') {
                continue;
            }
            $branches[] = $name;
        }

        return $branches;
    }

    public function getRepoReleases(string $org, string $repository, bool $withPreRelease = true): array
    {
        $query = '{
            repository(owner: "' . $org . '", name: "' . $repository . '") {
                releases(first:100) {
                  nodes {
                    tagName 
                    updatedAt
                  }
                }
            }
          }';

        $repositoryInfoGraphQL = $this->apiSearchGraphQL($query);
        $tags = [];
        foreach ($repositoryInfoGraphQL['data']['repository']['releases']['nodes'] as $node) {
            $nameTag = $node['tagName'];
            if (!$withPreRelease && (strpos($nameTag, 'alpha') !== false || strpos($nameTag, 'beta') !== false || strpos($nameTag, 'rc') !== false)) {
                continue;
            }
            $tags[$node['updatedAt']] = $nameTag;
        }

        return $tags;
    }

    public function getRepoTopics(string $org, string $repository): array
    {
        $query = '{
            repository(owner: "' . $org . '", name: "' . $repository . '") {
              repositoryTopics(first: 10) {
                edges {
                  node {
                    topic {
                      name
                    }
                  }
                }
              }
            }
          }';

        $repositoryInfoGraphQL = $this->apiSearchGraphQL($query);
        $topics = [];
        foreach ($repositoryInfoGraphQL['data']['repository']['repositoryTopics']['edges'] as $edge) {
            $topics[] = $edge['node']['topic']['name'];
        }

        return $topics;
    }

    public function getReviews(string $org, string $repository = ''): array
    {
        if (empty($repository)) {
            $search = 'org:' . $org;
        } else {
            $search = 'repo:' . $org . '/' . $repository;
        }
        $graphQLQuery = '{
            search(query: "' . $search . ' is:pr archived:false", type: ISSUE, last: 100 %after%) {
              issueCount
              pageInfo {
                endCursor
                hasNextPage
              }
              nodes {
                ... on PullRequest {
                  number
                  author {
                    login
                  }
                  repository {
                    name
                  }
                  title
                  reviews(first: 100) {
                    totalCount
                    edges {
                      node {
                        state
                        createdAt
                        author {
                          login
                        }
                      }
                    }
                  }
                }
              }
            }
          }';

        $result = [];
        $query = str_replace('%after%', '', $graphQLQuery);
        do {
            $resultPage = $this->apiSearchGraphQL($query);
            $result = array_merge($result, $resultPage['data']['search']['nodes']);
            if (!empty($resultPage['data']['search']['pageInfo']['endCursor'])) {
                $query = str_replace(
                    '%after%',
                    ', after: "' . $resultPage['data']['search']['pageInfo']['endCursor'] . '"',
                    $graphQLQuery
                );
            }
        } while ($resultPage['data']['search']['pageInfo']['hasNextPage']);

        return $result;
    }

    public function isPRValid(array $pullRequest, Filters $filters): bool
    {
        // FIX : Some merged PR are displayed in open search
        if ($pullRequest['merged']) {
            return false;
        }
        // Filter ignored repositories
        if (in_array($pullRequest['repository']['name'], self::REPOSITORIES_IGNORED)) {
            return false;
        }

        // Filter Repository Name
        if ($filters->hasFilter(Filters::FILTER_REPOSITORY_NAME)) {
            if ($filters->isFilterIncluded(Filters::FILTER_REPOSITORY_NAME)
                && !in_array($pullRequest['repository']['name'], $filters->getFilterData(Filters::FILTER_REPOSITORY_NAME), true)) {
                return false;
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_REPOSITORY_NAME)
                && in_array($pullRequest['repository']['name'], $filters->getFilterData(Filters::FILTER_REPOSITORY_NAME), true)) {
                return false;
            }
        }

        // Filter Repository Private
        if ($filters->hasFilter(Filters::FILTER_REPOSITORY_PRIVATE)) {
            if ($filters->isFilterIncluded(Filters::FILTER_REPOSITORY_PRIVATE)
                && !in_array($pullRequest['repository']['isPrivate'], $filters->getFilterData(Filters::FILTER_REPOSITORY_PRIVATE), true)) {
                return false;
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_REPOSITORY_PRIVATE)
                && in_array($pullRequest['repository']['isPrivate'], $filters->getFilterData(Filters::FILTER_REPOSITORY_PRIVATE), true)) {
                return false;
            }
        }

        // Filter Author
        if ($filters->hasFilter(Filters::FILTER_AUTHOR)) {
            if ($filters->isFilterIncluded(Filters::FILTER_AUTHOR)
                && !in_array($pullRequest['author']['login'], $filters->getFilterData(Filters::FILTER_AUTHOR))) {
                return false;
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_AUTHOR)
                && in_array($pullRequest['author']['login'], $filters->getFilterData(Filters::FILTER_AUTHOR))) {
                return false;
            }
        }

        // Filter File Extensions
        if ($filters->hasFilter(Filters::FILTER_FILE_EXTENSION)) {
            $countFilesType = $this->countFileType($pullRequest);
            if ($filters->isFilterIncluded(Filters::FILTER_FILE_EXTENSION)) {
                foreach ($filters->getFilterData(Filters::FILTER_FILE_EXTENSION) as $fileExt) {
                    if (!in_array($fileExt, array_keys($countFilesType))) {
                        return false;
                    }
                }
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_FILE_EXTENSION)) {
                foreach ($filters->getFilterData(Filters::FILTER_FILE_EXTENSION) as $fileExt) {
                    if (in_array($fileExt, array_keys($countFilesType))) {
                        return false;
                    }
                }
            }
        }

        // Filter Num Approved
        if ($filters->hasFilter(Filters::FILTER_NUM_APPROVED)) {
            if ($filters->isFilterIncluded(Filters::FILTER_NUM_APPROVED)
                && !in_array(count($pullRequest['approved']), $filters->getFilterData(Filters::FILTER_NUM_APPROVED), true)) {
                return false;
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_NUM_APPROVED)
                && in_array(count($pullRequest['approved']), $filters->getFilterData(Filters::FILTER_NUM_APPROVED), true)) {
                return false;
            }
        }

        // Filter Reviewer
        if ($filters->hasFilter(Filters::FILTER_REVIEWER)) {
            if ($filters->isFilterIncluded(Filters::FILTER_REVIEWER)) {
                foreach ($filters->getFilterData(Filters::FILTER_REVIEWER) as $reviewer) {
                    if (!in_array($reviewer, $pullRequest['approved'])) {
                        return false;
                    }
                }
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_REVIEWER)) {
                foreach ($filters->getFilterData(Filters::FILTER_REVIEWER) as $reviewer) {
                    if (in_array($reviewer, $pullRequest['approved'])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function extractPullRequestState(array $pullRequest, string $state): array
    {
        $statesByLogin = [];
        foreach ($pullRequest['reviews']['nodes'] as $node) {
            $login = $node['author']['login'];
            if (!in_array($login, self::MAINTAINER_MEMBERS)
                && !in_array($login, self::SOFTWARE_DEVELOPERS_IN_TEST_MEMBERS)) {
                continue;
            }
            if ($node['state'] == $state) {
                if (!in_array($login, $statesByLogin)) {
                    $statesByLogin[] = $login;
                }
            } else {
                if (in_array($login, $statesByLogin)) {
                    $pos = array_search($login, $statesByLogin);
                    unset($statesByLogin[$pos]);
                }
            }
        }

        return $statesByLogin;
    }

    private function apiSearchGraphQL(string $graphQLQuery): array
    {
        do {
            try {
                $resultPage = $this->client->api('graphql')->execute($graphQLQuery, []);
            } catch (\RuntimeException $e) {
            }
        } while (!isset($resultPage));

        return $resultPage ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function getMaintainers(): array
    {
        return self::MAINTAINER_MEMBERS;
    }

    public function countFileType(array $pullRequest): array
    {
        $types = [];

        foreach ($pullRequest['files']['nodes'] as $file) {
            $extension = pathinfo($file['path'], PATHINFO_EXTENSION);
            if (!array_key_exists($extension, $types)) {
                $types[$extension] = 0;
            }
            ++$types[$extension];
        }
        ksort($types);

        return $types;
    }
}
