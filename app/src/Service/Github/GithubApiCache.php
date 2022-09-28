<?php

namespace App\Service\Github;

use App\DTO\VersionControlSystemApiResponse\BranchesReferences\BranchesReferencesDTO;
use App\DTO\VersionControlSystemApiResponse\CodeSearch\SearchCodeDTO;
use App\DTO\VersionControlSystemApiResponse\CommitsCompare\CommitsCompareDTO;
use App\DTO\VersionControlSystemApiResponse\Common\IssueDTO;
use App\DTO\VersionControlSystemApiResponse\Common\RepositoryDTO;
use App\DTO\VersionControlSystemApiResponse\IssuesSearch\IssuesSearchDTO;
use App\DTO\VersionControlSystemApiResponse\LabelsAll\LabelsAllDTO;
use App\DTO\VersionControlSystemApiResponse\PullRequestAll\PullRequestAllsDTO;
use App\DTO\VersionControlSystemApiResponse\PullRequestSearch\PullRequestSearchNodeDTO;
use App\DTO\VersionControlSystemApiResponse\Repositories\RepositoriesDTO;
use App\DTO\VersionControlSystemApiResponse\RepositoryContent\RepositoryContentsDTO;
use App\DTO\VersionControlSystemApiResponse\RepositoryTopics\RepositoryTopicsDTO;
use Github\Client;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class GithubApiCache
{
    private Github $github;
    private int $numberOfHourCacheValidity;
    private CacheInterface $cache;

    public function __construct(
        int $numberOfHourCacheValidity,
        CacheInterface $cache,
        Github $github,
    ) {
        $this->numberOfHourCacheValidity = $numberOfHourCacheValidity;
        $this->cache = $cache;
        $this->github = $github;
    }

    public function getClient(): Client
    {
        return $this->github->getClient();
    }

    public function getLinkedIssue(PullRequestSearchNodeDTO $pullRequest): ?IssueDTO
    {
        $token = __FUNCTION__.md5(__FUNCTION__.json_encode($pullRequest));

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($pullRequest) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getLinkedIssue($pullRequest);
            }
        );
    }

    public function getRepoEndpointShow(string $org, string $repository): RepositoryDTO
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$org.$repository);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($org, $repository) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getRepoEndpointShow($org, $repository);
            }
        );
    }

    public function getRepoEndpointContentsShow(string $org, string $repository): RepositoryContentsDTO
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$org.$repository);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($org, $repository) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getRepoEndpointContentsShow($org, $repository);
            }
        );
    }

    public function getRepoEndpointContentsExists(string $org, string $repository, string $test, string $branch): bool
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$org.$repository.$test.$branch);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($org, $repository, $test, $branch) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getRepoEndpointContentsExists($org, $repository, $test, $branch);
            }
        );
    }

    public function getPullRequestEndpointAll(string $org, string $repository, array $arguments): PullRequestAllsDTO
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$org.$repository.json_encode($arguments));

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($org, $repository, $arguments) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getPullRequestEndpointAll($org, $repository, $arguments);
            }
        );
    }

    public function getGitDataEndpointReferencesBranches(string $org, string $repository): BranchesReferencesDTO
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$org.$repository);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($org, $repository) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getGitDataEndpointReferencesBranches($org, $repository);
            }
        );
    }

    // TODO must rreturn DTO
    public function getRepoEndpointReleasesLatest(string $org, string $repository): mixed
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$org.$repository);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($org, $repository) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getRepoEndpoint()->releases()->latest($org, $repository);
            }
        );
    }

    public function getRepoEndpointCommitsCompare(string $org, string $repository, string $masterLastCommitSha, string $devLastCommitSha): CommitsCompareDTO
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$org.$repository.$masterLastCommitSha.$devLastCommitSha);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($org, $repository, $masterLastCommitSha, $devLastCommitSha) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getRepoEndpointCommitsCompare($org, $repository, $masterLastCommitSha, $devLastCommitSha);
            }
        );
    }

    public function getRepoEndpointContentsDownload(string $org, string $repository, string $test, string $branch): string
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$org.$repository.$test.$branch);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($org, $repository, $test, $branch) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getRepoEndpointContentsDownload($org, $repository, $test, $branch);
            }
        );
    }

    public function getIssueEndpointLabelsAll(string $org, string $repository): LabelsAllDTO
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$org.$repository);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($org, $repository) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getIssueEndpointLabelsAll($org, $repository);
            }
        );
    }

    public function getSearchEndpointIssues(string $search): IssuesSearchDTO
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$search);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($search) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getSearchEndpointIssues($search);
            }
        );
    }

    public function countRepoFiles(string $org, string $repository, string $path = null): int
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$org.$repository.$path);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($org, $repository, $path) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->countRepoFiles($org, $repository, $path);
            }
        );
    }

    public function graphQLSearch(Query $graphQLQuery): array
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$graphQLQuery);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($graphQLQuery) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->search($graphQLQuery);
            }
        );
    }

    public function getRepoTopics(string $org, string $repository): RepositoryTopicsDTO
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$org.$repository);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($org, $repository) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getRepoTopics($org, $repository);
            }
        );
    }

    public function getOrganizationEndpointRepositories(string $org, ?string $type, ?string $page): ?RepositoriesDTO
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$org.$type.$page);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($org, $type, $page) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getOrganizationEndpointRepositories($org, $type, $page);
            }
        );
    }

    public function getSearchCode(string $query, int $numberPerPage, int $page): SearchCodeDTO
    {
        $token = __FUNCTION__.md5(__FUNCTION__.$query.$numberPerPage.$page);

        return $this->cache->get(
            $token,
            function (ItemInterface $item) use ($query, $numberPerPage, $page) {
                $item->expiresAfter($this->numberOfHourCacheValidity * 3600);

                return $this->github->getSearchCode($query, $numberPerPage, $page);
            }
        );
    }
}
