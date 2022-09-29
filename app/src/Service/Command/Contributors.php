<?php

namespace App\Service\Command;

use App\DTO\Presenter\ContributorDTO;
use App\DTO\Presenter\ContributorResumeDTO;
use App\DTO\VersionControlSystemApiResponse\Common\IssueDTO;
use App\DTO\VersionControlSystemApiResponse\Common\LabelDTO;
use App\DTO\VersionControlSystemApiResponse\Common\UserDTO;
use App\DTO\VersionControlSystemApiResponse\Contributors\ContributorsDTO;
use App\DTO\VersionControlSystemApiResponse\IssuesSearch\IssuesSearchDTO;
use App\Service\Github\GithubApiCache;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Contributors
{
    private GithubApiCache $githubApiCache;


    public function __construct(
        GithubApiCache $githubApiCache
    ) {

        $this->githubApiCache = $githubApiCache;
    }

    public function getContributors() {
        return $this->githubApiCache->getContributors('Prestashop', 'Prestashop');
    }

    public function getDetails(ContributorsDTO $contributorsDTO): iterable
    {
        foreach ($contributorsDTO->contributors as $author) {
            $issues = $this->githubApiCache->getSearchEndpointIssues('org:PrestaShop is:pr author:' . $author->login);
            $count = $issues->total_count;
            yield new ContributorResumeDTO(
                    $author,
                $count,
                $issues->items
            );
        }
    }

    public function isBug(IssueDTO $issues): bool
    {
        return array_reduce($issues->labels, function (bool $carry, LabelDTO $item) {
            if ($carry) {
                return $carry;
            }

            return 'Bug' === $item->name;
        }, false);
    }

    /**
     * @param IssueDTO $pullRequest
     * @return bool
     */
    public function isImprovement(IssueDTO $pullRequest): bool
    {
        return array_reduce($pullRequest->labels, function (bool $carry, LabelDTO $item) {
            if ($carry) {
                return $carry;
            }

            return 'Improvement' === $item->name;
        }, false);
    }

    /**
     * @param IssueDTO $pullRequest
     * @return string
     */
    public function getStatus(IssueDTO $pullRequest): string
    {
        return array_reduce($pullRequest->labels, function (string $carry, LabelDTO $item) {
            if (!empty($carry)) {
                return $carry;
            }
            if (in_array($item->name, [
                'Waiting for UX',
                'Waiting for PM',
                'Waiting for wording',
                'Waiting for author',
                'Waiting for dev',
            ])) {
                return $item->name;
            }

            return '';
        }, '');
    }

    /**
     * @param IssueDTO $pullRequest
     * @return string
     */
    public function getBranch(IssueDTO $pullRequest): string
    {
        return array_reduce($pullRequest->labels, function (bool $carry, LabelDTO $item) {
            if (!empty($carry)) {
                return $carry;
            }
            if ('develop' === $item->name) {
                return $item->name;
            }

            return '';
        }, '');
    }

    /**
     * @param IssueDTO|null $linkedIssue
     * @return string
     */
    public function getSeverity(?IssueDTO $linkedIssue): string
    {
        $severityIssue = '';
        if ($linkedIssue) {
            $severityIssue = array_reduce($linkedIssue->labels, function (string $carry, LabelDTO $item) {
                if (!empty($carry)) {
                    return $carry;
                }
                if (str_starts_with($item->description, 'Severity')) {
                    return $item->name;
                }

                return '';
            }, '');
        }
        return $severityIssue;
    }

    /**
     * @param IssueDTO[] $issues
     * @return iterable|ContributorDTO
     */
    public function getIssue(array $issues, UserDTO $author): iterable
    {
        foreach ($issues as $issue) {
            $repository = str_replace('https://api.github.com/repos/PrestaShop/', '', $issue->repository_url);
            $isBug = $this->isBug($issue);
            $isImprovement = $this->isImprovement($issue);
            $status = $this->getStatus($issue);
            $branch = $this->getBranch($issue);
            $apiPullRequest = $this->githubApiCache->getPullRequestEndpointShow('PrestaShop', $repository, $issue->number);
            $linkedIssue = $this->githubApiCache->getLinkedIssue($issue);
            $severityIssue = $this->getSeverity($linkedIssue);
            yield new ContributorDTO(
                // Repository
                $repository,
                // PR number
                $issue->number,
                // PR URL link
                $issue->html_url,
                // PR label (bug or improvement)
                $isBug ? 'bug' : ($isImprovement ? 'improvement' : ''),
                // PR status (WIP, reviewed, To be tested, Closed) if not merged
                $status,
                // PR branch
                $branch,
                // Date of creation of the PR,
                $issue->created_at,
                // Date of close of the PR
                $issue->closed_at ?? null,
                // Date of merge of the PR if there is one
                $apiPullRequest->merged ?? '',
                // PR author
                $author->login,
                // Issues related
                $linkedIssue ? $linkedIssue->number : '',
                // Issues URLs
                $linkedIssue ? $linkedIssue->html_url : '',
                // Severity labels of the issues if they are bugs
                $severityIssue,
                // Number of comments on the issues
                $linkedIssue ? $linkedIssue->comments : '',
                // Number of comments on the PR
                $issue->comments
            );
        }
    }
}