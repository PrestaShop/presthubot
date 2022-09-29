<?php

namespace App\Presenter\Contributors;

use DateTimeImmutable;

class ContributorsViewModel
{
    public function __construct(
        public string             $repository,
        public string             $pullRequestNumber,
        public string             $pullRequestUrl,
        public string             $isBugOrImprovement,
        public string             $status,
        public string             $branch,
        public string $pullRequestCreatedAt,
        public string $pullRequestClosedAt,
        public bool               $merged,
        public string             $author,
        public string             $linkedIssue,
        public ?string             $linkedIssueUrl = null,
        public string             $severityIssue,
        public string             $linkedIssueComment,
        public string             $pullRequestComments
    ) {
    }
}
