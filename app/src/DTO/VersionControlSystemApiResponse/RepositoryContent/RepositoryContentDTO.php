<?php

namespace App\DTO\VersionControlSystemApiResponse\RepositoryContent;

class RepositoryContentDTO
{
    public string $name;
    public string $path;
    public string $sha;
    public int $size;
    public string $url;
    public string $html_url;
    public string $git_url;
    public ?string $download_url;
    public string $type;
    public RepositoryContentLinkDTO $_links;
}
