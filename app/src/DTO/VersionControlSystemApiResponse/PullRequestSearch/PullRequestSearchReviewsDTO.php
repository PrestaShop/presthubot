<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestSearch;

class PullRequestSearchReviewsDTO
{
    public int $totalCount;
    /**
     * @var PullRequestSearchReviewNodeDTO[]
     */
    public array $nodes;

    public function addNode(PullRequestSearchReviewNodeDTO $node): void
    {
        $this->nodes[] = $node;
    }
}
