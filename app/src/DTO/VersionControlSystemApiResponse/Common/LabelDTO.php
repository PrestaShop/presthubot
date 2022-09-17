<?php

namespace App\DTO\VersionControlSystemApiResponse\Common;

class LabelDTO
{
    public int $id;
    public string $node_id;
    public string $url;
    public string $name;
    public ?string $description;
    public string $color;
    public bool $default;
}
