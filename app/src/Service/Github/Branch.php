<?php

namespace App\Service\Github;

use DateTime;
use Exception;

class Branch
{
    public const PRESTASHOP_USERNAME = 'prestashop';
    public const BRANCH_REFS_HEADS_DEV = 'refs/heads/dev';
    public const BRANCH_REFS_HEADS_DEVELOP = 'refs/heads/develop';
    public const BRANCH_REFS_HEADS_MASTER = 'refs/heads/master';
    public const BRANCH_REFS_HEADS_MAIN = 'refs/heads/main';
    public const BRANCH_NAME_MASTER = 'master';
    public const BRANCH_NAME_MAIN = 'main';

    private GithubApiCache $githubApiCache;

    public function __construct(GithubApiCache $githubApiCache)
    {
        $this->githubApiCache = $githubApiCache;
    }

    public function getReleaseData(string $repositoryName): array
    {
        list($masterBranchData, $devBranchData, $usedBranch) = $this->getBranches($repositoryName);
        $devLastCommitSha = $devBranchData->object->sha;
        $masterLastCommitSha = $masterBranchData->object->sha;

        $releaseDate = $this->getReleaseDate($repositoryName);
        $comparison = $this->getComparison($repositoryName, $masterLastCommitSha, $devLastCommitSha);
        $openPullRequest = $this->getOpenPullRequest($repositoryName, $usedBranch);

        return [
            'behind' => $comparison->behind_by,
            'ahead' => $comparison->ahead_by,
            'releaseDate' => $releaseDate,
            'pullRequest' => $openPullRequest,
        ];
    }

    public function getReleaseDate(string $repositoryName): string
    {
        try {
            $release = $this->githubApiCache->getRepoEndpointReleasesLatest(
                self::PRESTASHOP_USERNAME,
                $repositoryName
            );
            $date = new DateTime($release->created_at);
            $releaseDate = $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $releaseDate = 'NA';
        }

        return $releaseDate;
    }

    public function getOpenPullRequest(string $repositoryName, string $usedBranch): ?array
    {
        $openPullRequests = $this->githubApiCache->getPullRequestEndpointAll(
            self::PRESTASHOP_USERNAME,
            $repositoryName,
            [
                'state' => 'open',
                'base' => $usedBranch,
            ]
        );

        if ($openPullRequests) {
            $assignee = $openPullRequests[0]->assignee->login ?? '';
            $openPullRequest = [
                'link' => $openPullRequests[0]->html_url,
                'number' => $openPullRequests[0]->number,
                'assignee' => $assignee,
            ];
        } else {
            $openPullRequest = null;
        }

        return $openPullRequest;
    }

    public function getBranches(string $repositoryName): array
    {
        $references = $this->githubApiCache->getGitDataEndpointReferencesBranches(
            self::PRESTASHOP_USERNAME,
            $repositoryName
        );
        $devBranchData = $masterBranchData = [];
        $usedBranch = self::BRANCH_NAME_MAIN;
        foreach ($references as $branchData) {
            $branchName = $branchData->ref;

            if (self::BRANCH_REFS_HEADS_DEV === $branchName) {
                $devBranchData = $branchData;
            }
            if (self::BRANCH_REFS_HEADS_DEVELOP === $branchName) {
                $devBranchData = $branchData;
            }
            if (self::BRANCH_REFS_HEADS_MASTER === $branchName) {
                $masterBranchData = $branchData;
                $usedBranch = self::BRANCH_NAME_MASTER;
            }
            if (self::BRANCH_REFS_HEADS_MAIN === $branchName) {
                $masterBranchData = $branchData;
                $usedBranch = self::BRANCH_NAME_MAIN;
            }
        }

        return [$masterBranchData, $devBranchData, $usedBranch];
    }

    public function getComparison(string $repositoryName, $masterLastCommitSha, $devLastCommitSha)
    {
        return $this->githubApiCache->getRepoEndpointCommitsCompare(
            self::PRESTASHOP_USERNAME,
            $repositoryName,
            $masterLastCommitSha,
            $devLastCommitSha
        );
    }
}
