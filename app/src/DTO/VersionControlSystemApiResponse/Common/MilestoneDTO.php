<?php

namespace App\DTO\VersionControlSystemApiResponse\Common;

class MilestoneDTO
{
    public ?string $url;
    public ?string $html_url;
    public ?string $labels_url;
    public ?int $id;
    public ?string $node_id;
    public ?int $number;
    public ?string $state;
    public string $title;
    public ?string $description;
    public ?UserDTO $creator;
    public ?int $open_issues;
    public ?int $closed_issues;
    public ?string $created_at;
    public ?string $updated_at;
    public ?string $closed_at;
    public ?string $due_on;
}
