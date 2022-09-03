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
    const REFS_HEADS_DEV = 'refs/heads/dev';
    const REFS_HEADS_DEVELOP = 'refs/heads/develop';
    const REFS_HEADS_MASTER = 'refs/heads/master';
    const REFS_HEADS_MAIN = 'refs/heads/main';
    const MASTER = 'master';
    const MAIN = 'main';

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
        /**
         * @var Repo $repository
         */
        $repository = $this->client->api('repo');
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

        /**
         * @var GitData $gitData
         */
        $gitData = $this->client->api('gitData');
        $references = $gitData->references()->branches(
            self::PRESTASHOP_USERNAME,
            $repositoryName
        );
        $devBranchData = $masterBranchData = [];
        $usedBranch = self::MAIN;
        foreach ($references as $branchData) {
            $branchName = $branchData['ref'];

            if ($branchName === self::REFS_HEADS_DEV) {
                $devBranchData = $branchData;
            }
            if ($branchName === self::REFS_HEADS_DEVELOP) {
                $devBranchData = $branchData;
            }
            if ($branchName === self::REFS_HEADS_MASTER) {
                $masterBranchData = $branchData;
                $usedBranch = self::MASTER;
            }
            if ($branchName === self::REFS_HEADS_MAIN) {
                $masterBranchData = $branchData;
                $usedBranch = self::MAIN;
            }
        }

        $devLastCommitSha = $devBranchData['object']['sha'];
        $masterLastCommitSha = $masterBranchData['object']['sha'];

        $comparison = $repository->commits()->compare(
            self::PRESTASHOP_USERNAME,
            $repositoryName,
            $masterLastCommitSha,
            $devLastCommitSha
        );

        /**
         * @var PullRequest $pullRequests
         */
        $pullRequests = $this->client->api('pull_request');
        $openPullRequests = $pullRequests->all(
            self::PRESTASHOP_USERNAME,
            $repositoryName,
            [
                'state' => 'open',
                'base' => $usedBranch
            ]
        );

        if ($openPullRequests) {
            $assignee = $openPullRequests[0]['assignee']['login'] ?? '';
            $openPullRequest = [
                'link' => $openPullRequests[0]['html_url'],
                'number' => $openPullRequests[0]['number'],
                'assignee' => $assignee
            ];
        } else {
            $openPullRequest = false;
        }

        return [
            'behind' => $comparison['behind_by'],
            'ahead' => $comparison['ahead_by'],
            'releaseDate' => $releaseDate,
            'pullRequest' => $openPullRequest,
        ];
    }
}
