<?php

namespace App\DTO\VersionControlSystemApiResponse\Contributors;

use App\DTO\VersionControlSystemApiResponse\Common\IssueDTO;
use App\DTO\VersionControlSystemApiResponse\Common\UserDTO;

class ContributorsDTO
{
    /** @var UserDTO[] */
    public array $contributors;

    public function addContributor(UserDTO $contributor): void
    {
        $this->contributors[] = $contributor;
    }
}
