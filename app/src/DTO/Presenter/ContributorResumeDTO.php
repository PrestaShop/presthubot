<?php

namespace App\DTO\Presenter;

use App\DTO\VersionControlSystemApiResponse\Common\IssueDTO;
use App\DTO\VersionControlSystemApiResponse\Common\UserDTO;

class ContributorResumeDTO
{
    public function __construct(
        public UserDTO $author,
        public int $numberOfIssues,
        /**
         * @var IssueDTO[] $issues
         */
        public array $issues,
    ) {
    }
}