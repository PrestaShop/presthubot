<?php

namespace App\DTO\Presenter;

use DateTimeImmutable;

class ContributorDTO
{
    public function __construct(
        public string $repository,
        public string $pullRequestNumber,
        public string $pullRequestUrl,
        public string $isBugOrImprovement,
        public string $status,
        public string $branch,
        public ?DateTimeImmutable $pullRequestCreatedAt,
        public ?DateTimeImmutable $pullRequestClosedAt,
        public bool $merged,
        public string $author,
        public string $linkedIssue,
        public string $linkedIssueUrl,
        public string $severityIssue,
        public string $linkedIssueComment,
        public string $pullRequestComments
    ) {
    }
}