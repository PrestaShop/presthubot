<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestAll;

class PullRequestAllsDTO
{
    /**
     * @var PullRequestAllDTO[]
     */
    public array $items;

    public function addItem(PullRequestAllDTO $item): void
    {
        $this->items[] = $item;
    }
}
