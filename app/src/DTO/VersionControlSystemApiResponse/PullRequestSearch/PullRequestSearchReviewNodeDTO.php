<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestSearch;

use DateTimeImmutable;

class PullRequestSearchReviewNodeDTO
{
    public PullRequestSearchAuthorDTO $author;
    public string $state;
    public DateTimeImmutable $createdAt;
}
