<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestSearch;

use App\DTO\VersionControlSystemApiResponse\Common\MilestoneDTO;
use App\DTO\VersionControlSystemApiResponse\PullResquestResultInterface;
use DateTimeImmutable;

class PullRequestSearchNodeDTO implements PullResquestResultInterface
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
