<?php

namespace App\Service\Command;

use App\DTO\VersionControlSystemApiResponse\PullRequestSearch\PullRequestSearchNodeDTO;
use App\Service\Github\Filters;
use App\Service\Github\Github;
use App\Service\Github\GithubApiCache;
use App\Service\Github\Query;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;

class GithubCheckPR
{
    private GithubApiCache $githubApiCache;
    private array $githubMaintainers;
    private array $ignoredRepositories;
    private int $countRows = 0;

    public function __construct(
        array $githubMaintainers,
        array $githubIgnoredRepositories,
    ) {
        $this->githubMaintainers = $githubMaintainers;
        $this->ignoredRepositories = $githubIgnoredRepositories;
    }

    public function checkPR(
        Table $table,
        string $title,
        Query $graphQLQuery,
        array $orderBy,
        Filters $filters,
        bool $hasRows
    ) {
        $resultAPI = $this->githubApiCache->graphQLSearch($graphQLQuery);
        $rows = [];
        foreach ($resultAPI as $key => $pullRequest) {
            $resultAPI[$key] = $pullRequest->node;
            $resultAPI[$key]->approved = $this->extractPullRequestState($resultAPI[$key], Github::PULL_REQUEST_STATE_APPROVED);
            $resultAPI[$key]->request_changes = $this->extractPullRequestState($resultAPI[$key], Github::PULL_REQUEST_STATE_REQUEST_CHANGES);
            $resultAPI[$key]->comment = $this->extractPullRequestState($resultAPI[$key], Github::PULL_REQUEST_STATE_COMMENT);
        }
        uasort($resultAPI, function () use ($orderBy) {
            $return = 0;
            foreach ($orderBy as $orderKey) {
                if (isset(${$orderKey.'1'}, ${$orderKey.'2'})) {
                    if (${$orderKey.'1'} == ${$orderKey.'2'}) {
                        continue;
                    }

                    return ${$orderKey.'1'} < ${$orderKey.'2'} ? -1 : 1;
                }
            }

            return $return;
        });
        $countPR = 0;
        foreach ($resultAPI as $pullRequest) {
            if (!$this->isPRValid($pullRequest, $filters)) {
                continue;
            }
            $pullRequestTitle = str_split($pullRequest->title, 70);
            $pullRequestTitle = implode(PHP_EOL, $pullRequestTitle);
            $pullRequestTitle = '('.count($pullRequest->approved).'✓) '.$pullRequestTitle;
            $linkedIssue = $this->githubApiCache->getLinkedIssue($pullRequest);
            $currentRow = [
                '<href='.$pullRequest->repository->url.'>'.$pullRequest->repository->name.'</>',
                '<href='.$pullRequest->url.'>#'.$pullRequest->number.'</>',
                $pullRequest->createdAt->format('Y-m-d H:i:s'),
                $pullRequestTitle,
                '<href='.$pullRequest->author->url.'>'.$pullRequest->author->login.'</>',
                !empty($pullRequest->milestone) ? '    <info>✓</info>' : '    <error>✗ </error>',
                !is_null($linkedIssue) && 'PrestaShop' == $pullRequest->repository->name
                    ? (!empty($linkedIssue->milestone) ? '<info>✓ </info>' : '<error>✗ </error>').' <href='.$linkedIssue->html_url.'>#'.$linkedIssue->number.'</>'
                    : '',
            ];
            $rows[] = $currentRow;
            ++$countPR;
        }
        if (empty($rows)) {
            return $hasRows;
        }
        if ($hasRows) {
            $table->addRows([new TableSeparator()]);
        }

        $headers = [
            '<info>Project</info>',
            '<info>#</info>',
            '<info>Created At</info>',
            '<info>Title</info>',
            '<info>Author</info>',
            '<info>Milestone</info>',
            '<info>Issue</info>',
        ];
        $table->addRows([
            [new TableCell('<fg=black;bg=white;options=bold> '.$title.' ('.$countPR.') </>', ['colspan' => 7])],
            new TableSeparator(),
            $headers,
        ]);
        $table->addRows($rows);

        $this->countRows += $countPR;

        return true;
    }

    private function extractPullRequestState(PullRequestSearchNodeDTO $pullRequest, string $state): array
    {
        $statesByLogin = [];
        foreach ($pullRequest->reviews->nodes as $node) {
            $login = $node->author->login;
            if (!in_array($login, $this->githubMaintainers)) {
                continue;
            }
            if ($node->state == $state) {
                if (!in_array($login, $statesByLogin)) {
                    $statesByLogin[] = $login;
                }
            } else {
                if (in_array($login, $statesByLogin)) {
                    $pos = array_search($login, $statesByLogin);
                    unset($statesByLogin[$pos]);
                }
            }
        }    /*
     * @param GithubPullRequestSearchResultDTO[] $pullRequest
     * @param string $state
     * @return array
     */

        return $statesByLogin;
    }

    public function setGithubApiCache(GithubApiCache $githubApiCache): void
    {
        $this->githubApiCache = $githubApiCache;
    }

    public function isPRValid(PullRequestSearchNodeDTO $pullRequest, Filters $filters): bool
    {
        // FIX : Some merged PR are displayed in open search
        if ($pullRequest->merged) {
            return false;
        }
        // Filter ignored repositories
        if (in_array($pullRequest->repository->name, $this->ignoredRepositories)) {
            return false;
        }

        // Filter Repository Name
        if ($filters->hasFilter(Filters::FILTER_REPOSITORY_NAME)) {
            if ($filters->isFilterIncluded(Filters::FILTER_REPOSITORY_NAME)
                && !in_array($pullRequest->repository->name, $filters->getFilterData(Filters::FILTER_REPOSITORY_NAME), true)) {
                return false;
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_REPOSITORY_NAME)
                && in_array($pullRequest->repository->name, $filters->getFilterData(Filters::FILTER_REPOSITORY_NAME), true)) {
                return false;
            }
        }

        // Filter Repository Private
        if ($filters->hasFilter(Filters::FILTER_REPOSITORY_PRIVATE)) {
            if ($filters->isFilterIncluded(Filters::FILTER_REPOSITORY_PRIVATE)
                && !in_array($pullRequest->repository->isPrivate, $filters->getFilterData(Filters::FILTER_REPOSITORY_PRIVATE), true)) {
                return false;
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_REPOSITORY_PRIVATE)
                && in_array($pullRequest->repository->isPrivate, $filters->getFilterData(Filters::FILTER_REPOSITORY_PRIVATE), true)) {
                return false;
            }
        }

        // Filter Author
        if ($filters->hasFilter(Filters::FILTER_AUTHOR)) {
            if ($filters->isFilterIncluded(Filters::FILTER_AUTHOR)
                && !in_array($pullRequest->author->login, $filters->getFilterData(Filters::FILTER_AUTHOR))) {
                return false;
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_AUTHOR)
                && in_array($pullRequest->author->login, $filters->getFilterData(Filters::FILTER_AUTHOR))) {
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
