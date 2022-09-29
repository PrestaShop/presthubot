<?php

namespace App\DTO\VersionControlSystemApiResponse\BranchesReferences;

class BranchesReferencesDTO
{
    /**
     * @var BranchesReferenceDTO[]
     */
    public array $branchesReferences;

    public function addBranchesReference(BranchesReferenceDTO $branchesReference): void
    {
        $this->branchesReferences[] = $branchesReference;
    }
}
