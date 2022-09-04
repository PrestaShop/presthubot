<?php

namespace Console\App\Service\Github;

use DateTime;
use Exception;
use Github\Api\GitData;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Github\Client;

class BranchManager
{
    const PRESTASHOP_USERNAME = 'prestashop';
    const BRANCH_REFS_HEADS_DEV = 'refs/heads/dev';
    const BRANCH_REFS_HEADS_DEVELOP = 'refs/heads/develop';
    const BRANCH_REFS_HEADS_MASTER = 'refs/heads/master';
    const BRANCH_REFS_HEADS_MAIN = 'refs/heads/main';
    const BRANCH_NAME_MASTER = 'master';
    const BRANCH_NAME_MAIN = 'main';

    /**
     * @var Client
     */
    private $client;
    /**
     * @var GithubTypedEndpointProvider
     */
    private $githubTypedEndpointProvider;

    /**
     * @param Client $client
     */
    public function __construct(Client $client, GithubTypedEndpointProvider $githubTypedEndpointProvider)
    {
        $this->client = $client;
        $this->githubTypedEndpointProvider = $githubTypedEndpointProvider;
    }

    public function getReleaseData(string $repositoryName): array
    {
        list($masterBranchData, $devBranchData, $usedBranch) = $this->getBranches($repositoryName);
        $devLastCommitSha = $devBranchData['object']['sha'];
        $masterLastCommitSha = $masterBranchData['object']['sha'];

        $releaseDate = $this->getReleaseDate($repositoryName);
        $comparison = $this->getComparison($repositoryName, $masterLastCommitSha, $devLastCommitSha);
        $openPullRequest = $this->getOpenPullRequest($repositoryName, $usedBranch);

        return [
            'behind' => $comparison['behind_by'],
            'ahead' => $comparison['ahead_by'],
            'releaseDate' => $releaseDate,
            'pullRequest' => $openPullRequest,
        ];
    }

    public function getReleaseDate(string $repositoryName): string
    {
        $repository = $this->githubTypedEndpointProvider->getRepoEndpoint($this->client);
        try {
            $release = $repository->releases()->latest(
                self::PRESTASHOP_USERNAME,
                $repositoryName
            );
            $date = new DateTime($release['created_at']);
            $releaseDate = $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $releaseDate = 'NA';
        }

        return $releaseDate;
    }

    public function getOpenPullRequest(string $repositoryName, string $usedBranch): ?array
    {
        $pullRequests = $this->githubTypedEndpointProvider->getPullRequestEndpoint($this->client);
        $openPullRequests = $pullRequests->all(
            self::PRESTASHOP_USERNAME,
            $repositoryName,
            [
                'state' => 'open',
                'base' => $usedBranch,
            ]
        );

        if ($openPullRequests) {
            $assignee = $openPullRequests[0]['assignee']['login'] ?? '';
            $openPullRequest = [
                'link' => $openPullRequests[0]['html_url'],
                'number' => $openPullRequests[0]['number'],
                'assignee' => $assignee,
            ];
        } else {
            $openPullRequest = null;
        }

        return $openPullRequest;
    }

    public function getBranches(string $repositoryName): array
    {
        $gitData = $this->githubTypedEndpointProvider->getGitDataEndpoint($this->client);
        $references = $gitData->references()->branches(
            self::PRESTASHOP_USERNAME,
            $repositoryName
        );
        $devBranchData = $masterBranchData = [];
        $usedBranch = self::BRANCH_NAME_MAIN;
        foreach ($references as $branchData) {
            $branchName = $branchData['ref'];

            if ($branchName === self::BRANCH_REFS_HEADS_DEV) {
                $devBranchData = $branchData;
            }
            if ($branchName === self::BRANCH_REFS_HEADS_DEVELOP) {
                $devBranchData = $branchData;
            }
            if ($branchName === self::BRANCH_REFS_HEADS_MASTER) {
                $masterBranchData = $branchData;
                $usedBranch = self::BRANCH_NAME_MASTER;
            }
            if ($branchName === self::BRANCH_REFS_HEADS_MAIN) {
                $masterBranchData = $branchData;
                $usedBranch = self::BRANCH_NAME_MAIN;
            }
        }

        return [$masterBranchData, $devBranchData, $usedBranch];
    }

    public function getComparison(string $repositoryName, $masterLastCommitSha, $devLastCommitSha)
    {
        $repository = $this->githubTypedEndpointProvider->getRepoEndpoint($this->client);

        return $repository->commits()->compare(
            self::PRESTASHOP_USERNAME,
            $repositoryName,
            $masterLastCommitSha,
            $devLastCommitSha
        );
    }
}
