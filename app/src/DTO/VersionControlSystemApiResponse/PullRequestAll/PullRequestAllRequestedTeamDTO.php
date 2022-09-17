<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestAll;

class PullRequestAllRequestedTeamDTO
{
    public int $id;
    public string $node_id;
    public string $url;
    public string $html_url;
    public string $name;
    public string $slug;
    public string $description;
    public string $privacy;
    public string $permission;
    public string $members_url;
    public string $repositories_url;
    // TODO qualify $parent
    public $parent;
}
