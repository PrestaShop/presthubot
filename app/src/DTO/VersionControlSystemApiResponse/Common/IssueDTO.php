<?php

namespace App\DTO\VersionControlSystemApiResponse\Common;

use App\DTO\VersionControlSystemApiResponse\PullResquestResultInterface;
use DateTimeImmutable;

class IssueDTO implements PullResquestResultInterface
{
    public string $url;
    public string $repository_url;
    public string $labels_url;
    public string $comments_url;
    public string $events_url;
    public string $html_url;
    public int $id;
    public string $node_id;
    public int $number;
    public string $title;
    public UserDTO $user;
    /** @var LabelDTO[] */
    public array $labels;
    public string $state;
    public bool $locked;
    public UserDTO $assignee;
    /** @var UserDTO[] */
    public array $assignees;
    public MilestoneDTO $milestone;
    public int $comments;
    public DateTimeImmutable $created_at;
    public DateTimeImmutable $updated_at;
    public ?DateTimeImmutable $closed_at;
    public string $author_association;
    public ?string $active_lock_reason;
    public bool $draft;
    public PullRequestAbstractDTO $pull_request;
    public ?string $body;
    public ?UserDTO $closed_by;
    public ReactionsDTO $reactions;
    public string $timeline_url;
    public ?bool $performed_via_github_app;
    public ?string $state_reason;
    public float $score;

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
}
