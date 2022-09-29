<?php

namespace App\DTO\VersionControlSystemApiResponse\CommitsCompare;

use App\DTO\VersionControlSystemApiResponse\Common\UserDTO;

class CommitsCompareCommitDTO
{
    public UserDTO $author;
    public UserDTO $committer;
    public string $message;
    public CommitsCompareTreeDTO $tree;
    public string $url;
    public int $comment_count;
    public CommitsCompareVerificationDTO $verification;
}
