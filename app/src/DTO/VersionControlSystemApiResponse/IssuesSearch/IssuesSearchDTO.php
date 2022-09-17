<?php

namespace App\DTO\VersionControlSystemApiResponse\IssuesSearch;

use App\DTO\VersionControlSystemApiResponse\Common\IssueDTO;

class IssuesSearchDTO
{
    public int $total_count;
    public bool $incomplete_results;

    /** @var IssueDTO[] */
    public array $items;

    public function addItem(IssueDTO $item): void
    {
        $this->items[] = $item;
    }
}
