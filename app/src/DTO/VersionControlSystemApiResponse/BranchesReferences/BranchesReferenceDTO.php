<?php

namespace App\DTO\VersionControlSystemApiResponse\BranchesReferences;

class BranchesReferenceDTO
{
    public string $ref;
    public string $node_id;
    public string $url;
    public BranchesReferenceObjectDTO $object;
}
