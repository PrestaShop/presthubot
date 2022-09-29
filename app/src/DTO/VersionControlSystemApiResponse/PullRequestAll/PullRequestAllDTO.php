<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestAll;

use App\DTO\VersionControlSystemApiResponse\Common\LabelDTO;
use App\DTO\VersionControlSystemApiResponse\Common\LinksDTO;
use App\DTO\VersionControlSystemApiResponse\Common\MilestoneDTO;
use App\DTO\VersionControlSystemApiResponse\Common\UserDTO;
use DateTimeImmutable;

class PullRequestAllDTO
{
    public string $url;
    public int $id;
    public string $node_id;
    public string $html_url;
    public string $diff_url;
    public string $patch_url;
    public string $issue_url;
    public string $commits_url;
    public string $review_comments_url;
    public string $review_comment_url;
    public string $comments_url;
    public string $statuses_url;
    public int $number;
    public string $state;
    public bool $locked;
    public string $title;
    public UserDTO $user;
    public string $body;
    /** @var LabelDTO[] */
    public array $labels;
    public MilestoneDTO $milestone;
    public ?string $active_lock_reason;
    public ?DateTimeImmutable $created_at;
    public ?DateTimeImmutable $updated_at;
    public ?DateTimeImmutable $closed_at;
    public ?DateTimeImmutable $merged_at;
    public string $merge_commit_sha;
    public UserDTO $assignee;
    /** @var UserDTO[] */
    public array $assignees;
    /** @var UserDTO[] */
    public array $requested_reviewers;
    /** @var PullRequestAllRequestedTeamDTO[] */
    public array $requested_teams;
    public PullRequestAllBaseDTO $head;
    public PullRequestAllBaseDTO $base;
    public LinksDTO $_links;
    public string $author_association;
    public $auto_merge;
    public bool $draft;

    public function addLabel(LabelDTO $label): void
    {
        $this->labels[] = $label;
    }

    public function setAssignee(UserDTO $assignee): void
    {
        $this->assignee = $assignee;
    }

    public function addAssignee(UserDTO $assignee): void
    {
        $this->assignees[] = $assignee;
    }

    public function addRequestedTeam(PullRequestAllRequestedTeamDTO $requested_team): void
    {
        $this->requested_teams[] = $requested_team;
    }
}
