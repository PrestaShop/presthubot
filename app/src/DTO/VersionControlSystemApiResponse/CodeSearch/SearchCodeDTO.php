<?php

namespace App\DTO\VersionControlSystemApiResponse\CodeSearch;

class SearchCodeDTO
{
    public int $total_count;
    public bool $incomplete_results;

    /** @var CodeItemDTO[] */
    public array $items;

    public function addItem(CodeItemDTO $item): void
    {
        $this->items[] = $item;
    }
}
