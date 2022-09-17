<?php

namespace App\DTO\VersionControlSystemApiResponse\LabelsAll;

use App\DTO\VersionControlSystemApiResponse\Common\LabelDTO;

class LabelsAllDTO
{
    /** @var LabelDTO[] */
    public array $labels;

    public function addItem(LabelDTO $label): void
    {
        $this->labels[] = $label;
    }
}
