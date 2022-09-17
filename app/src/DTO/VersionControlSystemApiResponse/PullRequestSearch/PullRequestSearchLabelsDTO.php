<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestSearch;

use App\DTO\VersionControlSystemApiResponse\Common\LabelDTO;

class PullRequestSearchLabelsDTO
{
    public int $totalCount;
    /**
     * @var LabelDTO[]
     */
    public array $labels;

    public function addLabel(LabelDTO $label): void
    {
        $this->labels[] = $label;
    }
}
