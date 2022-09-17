<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestSearch;

class PullRequestSearchFilesDTO
{
    /**
     * @var PullRequestSearchFileNodeDTO[]
     */
    public array $nodes;

    public function addNode(PullRequestSearchFileNodeDTO $node): void
    {
        $this->nodes[] = $node;
    }
}
