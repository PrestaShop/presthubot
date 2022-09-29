<?php

namespace App\DTO\VersionControlSystemApiResponse\RepositoryContent;

class RepositoryContentsDTO
{
    /**
     * @var RepositoryContentDTO[]
     */
    public array $contents;

    public function addContent(RepositoryContentDTO $content): void
    {
        $this->contents[] = $content;
    }
}
