<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestSearch;

class PullRequestSearchRepositoryDTO
{
    public string $name;
    public string $url;
    public bool $isPrivate;
}
