<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestSearch;

use App\DTO\VersionControlSystemApiResponse\Common\MilestoneDTO;
use DateTimeImmutable;

class PullRequestSearchNodeDTO
{
    public int $number;
    public PullRequestSearchAuthorDTO $author;
    public string $url;
    public string $title;
    public string $body;
    public DateTimeImmutable $createdAt;
    public bool $merged;
    public ?DateTimeImmutable $mergedAt;
    public PullRequestSearchFilesDTO $files;
    public MilestoneDTO $milestone;
    public PullRequestSearchRepositoryDTO $repository;
    public PullRequestSearchReviewsDTO $reviews;
    public PullRequestSearchLabelsDTO $labels;
}
