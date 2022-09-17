<?php

namespace App\Service\PrestaShop;

class ModuleFlagsDTO
{
    public ?bool $archived = null;
    public ?bool $moved = null;
    public ?string $url = null;
    public int $numStargazers;
    public int $numPROpened;
    public int $numFiles;
    public ?bool $hasIssuesOpened = null;
    public int $numIssuesOpened;
    public ?string $license = null;
    public array $labels = [];
    public array $branches = [];
    public array $files = [];
    public array $githubTopics = [];
}
