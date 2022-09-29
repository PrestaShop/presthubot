<?php

namespace App\DTO\VersionControlSystemApiResponse\RepositoryTopics;

class RepositoryTopicsDTO
{
    /**
     * @var string[]
     */
    public array $names;

    public function addName(string $name): void
    {
        $this->names[] = $name;
    }
}
