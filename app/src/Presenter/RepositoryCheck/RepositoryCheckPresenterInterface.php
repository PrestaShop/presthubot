<?php

namespace App\Presenter\RepositoryCheck;

use App\DTO\VersionControlSystemApiResponse\Common\RepositoryDTO;

interface RepositoryCheckPresenterInterface
{
    public function present(RepositoryDTO $repository): void;
}
