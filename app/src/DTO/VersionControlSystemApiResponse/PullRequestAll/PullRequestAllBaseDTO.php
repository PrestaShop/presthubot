<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestAll;

use App\DTO\VersionControlSystemApiResponse\Common\RepositoryDTO;
use App\DTO\VersionControlSystemApiResponse\Common\UserDTO;

class PullRequestAllBaseDTO
{
    public string $label;
    public string $ref;
    public string $sha;
    public UserDTO $user;
    public RepositoryDTO $repo;
}
