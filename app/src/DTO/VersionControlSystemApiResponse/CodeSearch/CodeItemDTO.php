<?php

namespace App\DTO\VersionControlSystemApiResponse\CodeSearch;

use App\DTO\VersionControlSystemApiResponse\Common\RepositoryDTO;

class CodeItemDTO
{
    public string $name;
    public string $path;
    public string $sha;
    public string $url;
    public string $git_url;
    public string $html_url;
    public RepositoryDTO $repository;
    public float $score;
}
