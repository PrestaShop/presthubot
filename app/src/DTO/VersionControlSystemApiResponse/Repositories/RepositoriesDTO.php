<?php

namespace App\DTO\VersionControlSystemApiResponse\Repositories;

use App\DTO\VersionControlSystemApiResponse\Common\RepositoryDTO;

class RepositoriesDTO
{
    /**
     * @var RepositoryDTO[]
     */
    public array $repositories;

    public function addRepository(RepositoryDTO $repository): void
    {
        $this->repositories[] = $repository;
    }
}
