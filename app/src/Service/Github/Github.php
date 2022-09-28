<?php

namespace App\Service\Github;

use App\DTO\VersionControlSystemApiResponse\BranchesReferences\BranchesReferenceDTO;
use App\DTO\VersionControlSystemApiResponse\BranchesReferences\BranchesReferencesDTO;
use App\DTO\VersionControlSystemApiResponse\CodeSearch\SearchCodeDTO;
use App\DTO\VersionControlSystemApiResponse\CommitsCompare\CommitsCompareDTO;
use App\DTO\VersionControlSystemApiResponse\Common\IssueDTO;
use App\DTO\VersionControlSystemApiResponse\Common\LabelDTO;
use App\DTO\VersionControlSystemApiResponse\Common\RepositoryDTO;
use App\DTO\VersionControlSystemApiResponse\IssuesSearch\IssuesSearchDTO;
use App\DTO\VersionControlSystemApiResponse\LabelsAll\LabelsAllDTO;
use App\DTO\VersionControlSystemApiResponse\PullRequestAll\PullRequestAllDTO;
use App\DTO\VersionControlSystemApiResponse\PullRequestAll\PullRequestAllsDTO;
use App\DTO\VersionControlSystemApiResponse\PullRequestSearch\PullRequestSearchDTO;
use App\DTO\VersionControlSystemApiResponse\PullRequestSearch\PullRequestSearchNodeDTO;
use App\DTO\VersionControlSystemApiResponse\Repositories\RepositoriesDTO;
use App\DTO\VersionControlSystemApiResponse\RepositoryContent\RepositoryContentDTO;
use App\DTO\VersionControlSystemApiResponse\RepositoryContent\RepositoryContentsDTO;
use App\DTO\VersionControlSystemApiResponse\RepositoryTopics\RepositoryTopicsDTO;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Exception;
use Github\Api\GitData;
use Github\Api\GraphQL;
use Github\Api\Issue;
use Github\Api\Organization;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Github\Api\Search as SearchAlias;
use Github\Client;
use Github\Exception\RuntimeException;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class Github
{
    protected Client $client;

    public const GITHUB_API_ENDPOINT_REPO = 'repo';
    public const GITHUB_API_ENDPOINT_PULL_REQUEST = 'pull_request';
    public const GITHUB_API_ENDPOINT_GIT_DATA = 'gitData';
    public const GITHUB_API_ENDPOINT_ISSUE = 'issue';
    public const GITHUB_API_ENDPOINT_GRAPHQL = 'graphql';
    public const GITHUB_API_ENDPOINT_SEARCH = 'search';
    public const GITHUB_API_ENDPOINT_ORGANIZATION = 'organization';
    public const PULL_REQUEST_STATE_APPROVED = 'APPROVED';
    public const PULL_REQUEST_STATE_REQUEST_CHANGES = 'REQUEST_CHANGES';
    public const PULL_REQUEST_STATE_COMMENT = 'COMMENT';
    private SerializerInterface $serializer;
    private Search $search;

    public function __construct(
        string $githubToken,
        Search $search,
        SerializerInterface $serializer,
    ) {
        $filesystemAdapter = new Local(__DIR__.'/../../../var/');
        $filesystem = new Filesystem($filesystemAdapter);
        $pool = new FilesystemCachePool($filesystem);

        $this->client = new Client();
        $this->client->addCache($pool);

        if (!empty($githubToken)) {
            $this->client->authenticate($githubToken, null, Client::AUTH_ACCESS_TOKEN);
        }
        $this->serializer = $serializer;
        $this->search = $search;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @return array<array<mixed>>
     */
    public function search(Query $graphQLQuery): array
    {
        $result = [];
        do {
            $resultPage = $this->apiSearchGraphQL((string) $graphQLQuery);
            $result = array_merge($result, $resultPage['data']['search']['edges']);
            if (!empty($resultPage['data']['search']['pageInfo']['endCursor'])) {
                $graphQLQuery->setPageAfter($resultPage['data']['search']['pageInfo']['endCursor']);
            }
        } while ($resultPage['data']['search']['pageInfo']['hasNextPage']);

        return $this->serializer->denormalize(
            $result,
            PullRequestSearchDTO::class.'[]',
            null,
            [
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z',
            ]
        );
    }

    public function countSearch(Query $graphQLQuery): int
    {
        $resultPage = $this->apiSearchGraphQL((string) $graphQLQuery);

        return $resultPage['data']['search']['issueCount'];
    }

    public function countRepoFiles(string $org, string $repository, string $path = null): int
    {
        $numFiles = 0;

        $arrayPath = $this->getRepoEndpoint()->contents()->show($org, $repository, $path);
        foreach ($arrayPath as $itemPath) {
            if ('file' == $itemPath['type']) {
                ++$numFiles;
                continue;
            }
            if ('dir' == $itemPath['type']) {
                $numFiles += $this->countRepoFiles($org, $repository, $itemPath['path']);
                continue;
            }
        }

        return $numFiles;
    }

    public function getRepoEndpointShow(string $org, string $repository): RepositoryDTO
    {
        return $this->serializer->denormalize(
            $this->getRepoEndpoint()->show($org, $repository),
            RepositoryDTO::class,
            null,
            [
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z',
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]
        );
    }

    public function getSearchCode(string $query, int $numberPerPage, int $page): SearchCodeDTO
    {
        $this->search->setClient($this->client);
        $this->search->setPerPage($numberPerPage);
        $this->search->setPage($page);
        try {
            return $this->serializer->denormalize(
                $this->search->code($query),
                SearchCodeDTO::class,
                null,
                [
                    DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z',
                    DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
                ]
            );
        } catch (Exception $exception) {
            dd($exception);
        }
    }

    public function getSearchEndpointIssues(string $search): IssuesSearchDTO
    {
        return $this->serializer->denormalize(
            $this->getSearchEndpoint()->issues($search),
            IssuesSearchDTO::class,
            null,
            [
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z',
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]
        );
    }

    public function getIssueEndpointLabelsAll(string $org, string $repository): LabelsAllDTO
    {
        $result = new LabelsAllDTO();
        $result->labels = $this->serializer->denormalize(
            $this->getIssueEndpoint()->Labels()->All($org, $repository),
            LabelDTO::class.'[]',
            null,
            [
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z',
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]
        );

        return $result;
    }

    public function getRepoEndpointContentsShow(string $org, string $repository): RepositoryContentsDTO
    {
        $contents = new RepositoryContentsDTO();
        $contents->contents =
            $this->serializer->denormalize(
                $this->getRepoEndpoint()->contents()->show($org, $repository),
                RepositoryContentDTO::class.'[]',
                null,
                [
                    DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z',
                    DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
                ]
            );

        return $contents;
    }

    public function getRepoEndpointContentsExists(string $org, string $repository, string $test, string $branch): bool
    {
        return $this->getRepoEndpoint()->Contents()->exists($org, $repository, $test, $branch);
    }

    public function getPullRequestEndpointAll(string $org, string $repository, array $arguments): PullRequestAllsDTO
    {
        $result = new PullRequestAllsDTO();
        try {
            $result->items = $this->serializer->denormalize(
                $this->getPullRequestEndpoint()->all($org, $repository, $arguments),
                PullRequestAllDTO::class.'[]',
                null,
                [
                    DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z',
                    DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
                ]
            );
        } catch (Exception $e) {
            dd($e);
        }

        return $result;
    }

    public function getGitDataEndpointReferencesBranches(string $org, string $repository): BranchesReferencesDTO
    {
        $result = new BranchesReferencesDTO();
        $result->branchesReferences = $this->serializer->denormalize(
            $this->getGitDataEndpoint()->references()->branches($org, $repository),
            BranchesReferenceDTO::class.'[]',
            null,
            [
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z',
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]
        );

        return $result;
    }

    public function getRepoEndpointCommitsCompare(string $org, string $repository, string $masterLastCommitSha, string $devLastCommitSha): CommitsCompareDTO
    {
        return $this->serializer->denormalize(
            $this->getRepoEndpoint()->commits()->compare($org, $repository, $masterLastCommitSha, $devLastCommitSha),
            CommitsCompareDTO::class,
            null,
            [
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z',
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]
        );
    }

    public function getLinkedIssue(PullRequestSearchNodeDTO $pullRequest): ?IssueDTO
    {
        preg_match('#Fixes\s\#([0-9]{1,5})#', $pullRequest->body, $matches);
        $issueId = !empty($matches) && !empty($matches[1]) ? $matches[1] : null;
        if (empty($issueId)) {
            preg_match('#Fixes\sissue\s\#([0-9]{1,5})#', $pullRequest->body, $matches);
            $issueId = !empty($matches) && !empty($matches[1]) ? $matches[1] : null;
        }
        if (empty($issueId)) {
            preg_match('#Fixes\shttps:\/\/github.com\/PrestaShop\/PrestaShop\/issues\/([0-9]{1,5})#', $pullRequest->body, $matches);
            $issueId = !empty($matches) && !empty($matches[1]) ? $matches[1] : null;
        }
        try {
            $issue = is_null($issueId) ? null : $this->getIssueEndpoint()->show('PrestaShop', 'PrestaShop', $issueId);
        } catch (RuntimeException $e) {
            if ('Not Found' == $e->getMessage()) {
                throw new Exception(sprintf('Cannot find linked issue #%s in PR #%s', $issueId, $pullRequest->number));
            } else {
                throw $e;
            }
        }
        if (isset($pullRequest->_links)) {
            var_dump('PR #'.$pullRequest->number.' has _links in its API');
        }
        if (null === $issue) {
            return null;
        }
        $issue['assignees'][1] = $issue['assignee'];
        $issue['assignees'][1]['login'] = 'testSalim';

        return $this->serializer->denormalize(
            $issue,
            IssueDTO::class,
            null,
            [
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z',
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]
        );
    }

    public function getRepoEndpointContentsDownload(string $org, string $repository, string $test, string $branch): string
    {
        return $this->getRepoEndpoint()->Contents()->download($org, $repository, $test, $branch);
    }

    public function getRepoBranches(string $org, string $repository, bool $withDevelop = true): array
    {
        $query = '{
            repository(owner: "'.$org.'", name: "'.$repository.'") {
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
            if (!$withDevelop && 'develop' === $name) {
                continue;
            }
            $branches[] = $name;
        }

        return $branches;
    }

    public function getRepoReleases(string $org, string $repository, bool $withPreRelease = true): array
    {
        $query = '{
            repository(owner: "'.$org.'", name: "'.$repository.'") {
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
            if (!$withPreRelease && (false !== strpos($nameTag, 'alpha') || false !== strpos($nameTag, 'beta') || false !== strpos($nameTag, 'rc'))) {
                continue;
            }
            $tags[$node['updatedAt']] = $nameTag;
        }

        return $tags;
    }

    public function getRepoTopics(string $org, string $repository): RepositoryTopicsDTO
    {
        return $this->serializer->denormalize(
            $this->getRepoEndpoint()->topics($org, $repository),
            RepositoryTopicsDTO::class,
            null,
            [
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z',
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]
        );
    }

    public function getReviews(string $org, string $repository = ''): array
    {
        if (empty($repository)) {
            $search = 'org:'.$org;
        } else {
            $search = 'repo:'.$org.'/'.$repository;
        }
        $graphQLQuery = '{
            search(query: "'.$search.' is:pr archived:false", type: ISSUE, last: 100 %after%) {
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
                    ', after: "'.$resultPage['data']['search']['pageInfo']['endCursor'].'"',
                    $graphQLQuery
                );
            }
        } while ($resultPage['data']['search']['pageInfo']['hasNextPage']);

        return $result;
    }

    public function getOrganizationEndpointRepositories(string $org, ?string $type, ?string $page): ?RepositoriesDTO
    {
        $result = new RepositoriesDTO();
        $repositories = $this->serializer->denormalize(
            $this->getOrganizationEndpoint()->repositories($org, $type, $page),
            RepositoryDTO::class.'[]',
            null,
            [
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z',
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]
        );
        if ([] === $repositories) {
            return null;
        }
        $result->repositories = $repositories;

        return $result;
    }

    private function apiSearchGraphQL(string $graphQLQuery): array
    {
        try {
            $resultPage = $this->getGraphQLEndpoint()->execute($graphQLQuery, []);
        } catch (\RuntimeException $e) {
            // throw ($e);
        }

        return $resultPage ?? [];
    }

    public function getRepoEndpoint(): Repo
    {
        /**
         * @var Repo $repo
         */
        $repo = $this->client->api(self::GITHUB_API_ENDPOINT_REPO);

        return $repo;
    }

    public function getPullRequestEndpoint(): PullRequest
    {
        /**
         * @var PullRequest $pullRequest
         */
        $pullRequest = $this->client->api(self::GITHUB_API_ENDPOINT_PULL_REQUEST);

        return $pullRequest;
    }

    public function getGitDataEndpoint(): GitData
    {
        /**
         * @var GitData $gitData
         */
        $gitData = $this->client->api(self::GITHUB_API_ENDPOINT_GIT_DATA);

        return $gitData;
    }

    public function getIssueEndpoint(): Issue
    {
        /**
         * @var Issue $issue
         */
        $issue = $this->client->api(self::GITHUB_API_ENDPOINT_ISSUE);

        return $issue;
    }

    public function getGraphQLEndpoint(): GraphQL
    {
        /**
         * @var GraphQL $graphql
         */
        $graphql = $this->client->api(self::GITHUB_API_ENDPOINT_GRAPHQL);

        return $graphql;
    }

    public function getSearchEndpoint(): SearchAlias
    {
        /**
         * @var SearchAlias $search
         */
        $search = $this->client->api(self::GITHUB_API_ENDPOINT_SEARCH);

        return $search;
    }

    public function getOrganizationEndpoint(): Organization
    {
        /**
         * @var Organization $organization
         */
        $organization = $this->client->api(self::GITHUB_API_ENDPOINT_ORGANIZATION);

        return $organization;
    }
}
