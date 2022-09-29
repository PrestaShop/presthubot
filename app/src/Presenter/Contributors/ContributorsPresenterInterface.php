<?php

namespace App\Presenter\Contributors;

use App\DTO\Presenter\ContributorDTO;
use App\DTO\VersionControlSystemApiResponse\Common\UserDTO;
use App\DTO\VersionControlSystemApiResponse\Contributors\ContributorsDTO;
use App\DTO\VersionControlSystemApiResponse\CqrsEndpoints\CqrsEndpointDTO;

interface ContributorsPresenterInterface
{
    public function present(ContributorDTO $cqrsEndpointDTO): void;
}
