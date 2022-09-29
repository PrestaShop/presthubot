<?php

namespace App\DTO\VersionControlSystemApiResponse\PullRequestShow;

use App\DTO\VersionControlSystemApiResponse\Common\LabelDTO;
use App\DTO\VersionControlSystemApiResponse\Common\LinksDTO;
use App\DTO\VersionControlSystemApiResponse\Common\MilestoneDTO;
use App\DTO\VersionControlSystemApiResponse\Common\UserDTO;
use App\DTO\VersionControlSystemApiResponse\PullRequestAll\PullRequestAllRequestedTeamDTO;
use App\DTO\VersionControlSystemApiResponse\PullResquestResultInterface;
use DateTimeImmutable;

class PullRequestShowDTO implements PullResquestResultInterface
{
	public string $url;
	public int $id;
	public string $node_id;
	public string $html_url;
	public string $diff_url;
	public string $patch_url;
	public string $issue_url;
	public int $number;
	public string $state;
	public bool $locked;
	public string $title;
	public UserDTO $user;
	public ?string $body;
	public ?DateTimeImmutable $created_at;
	public ?DateTimeImmutable $updated_at;
	public ?DateTimeImmutable $closed_at;
	public ?DateTimeImmutable $merged_at;
	public ?string $merge_commit_sha;
    public UserDTO $assignee;
    /** @var UserDTO[] */
    public array $assignees;
    /** @var UserDTO[] */
	public array $requested_reviewers;
    /** @var PullRequestAllRequestedTeamDTO[] */
    public array $requested_teams;
    /** @var LabelDTO[] */
    public array $labels;
	public MilestoneDTO $milestone;
	public bool $draft;
	public string $commits_url;
	public string $review_comments_url;
	public string $review_comment_url;
	public string $comments_url;
	public string $statuses_url;
	public HeadDTO $head;
	public HeadDTO $base;
	public LinksDTO $_links;
	public string $author_association;
	public ?bool $auto_merge;
	public ?bool $active_lock_reason;
	public bool $merged;
	public ?bool $mergeable;
	public ?bool $rebaseable;
	public string $mergeable_state;
	public UserDTO $merged_by;
	public int $comments;
	public int $review_comments;
	public bool $maintainer_can_modify;
	public int $commits;
	public int $additions;
	public int $deletions;
	public int $changed_files;

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
