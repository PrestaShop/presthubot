<?php

namespace App\DTO\VersionControlSystemApiResponse\Common;

class PullRequestAbstractDTO
{
    public string $url;
    public string $html_url;
    public string $diff_url;
    public string $patch_url;
    public $merged_at;
}
