<?php

namespace Console\App\Service\Branch;

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
    const GITHUB_API_ENDPOINT_REPO = 'repo';
    const GITHUB_API_ENDPOINT_PULL_REQUEST = 'pull_request';
    const GITHUB_API_ENDPOINT_GIT_DATA = 'gitData';

    /**
     * @var Client
     */
    private $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
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
        /**
         * @var Repo $repository
         */
        $repository = $this->client->api(self::GITHUB_API_ENDPOINT_REPO);
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
        /**
         * @var PullRequest $pullRequests
         */
        $pullRequests = $this->client->api(self::GITHUB_API_ENDPOINT_PULL_REQUEST);
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
        /**
         * @var GitData $gitData
         */
        $gitData = $this->client->api(self::GITHUB_API_ENDPOINT_GIT_DATA);
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
        /**
         * @var Repo $repository
         */
        $repository = $this->client->api(self::GITHUB_API_ENDPOINT_REPO);

        return $repository->commits()->compare(
            self::PRESTASHOP_USERNAME,
            $repositoryName,
            $masterLastCommitSha,
            $devLastCommitSha
        );
    }
}
