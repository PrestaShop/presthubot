<?php

namespace App\DTO\VersionControlSystemApiResponse\CommitsCompare;

use App\DTO\VersionControlSystemApiResponse\Common\UserDTO;

class CommitsCompareBaseCommitDTO
{
    public string $sha;
    public string $node_id;
    public CommitsCompareCommitDTO $commit;
    public string $url;
    public string $html_url;
    public string $comments_url;
    public UserDTO $author;
    public UserDTO $committer;
    /** @var CommitsCompareParentDTO[] */
    public array $parents;

    public function addParent(CommitsCompareParentDTO $parent): void
    {
        $this->parents[] = $parent;
    }
}
