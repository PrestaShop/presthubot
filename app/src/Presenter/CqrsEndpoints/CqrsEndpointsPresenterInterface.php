<?php

namespace App\Presenter\CqrsEndpoints;

use App\DTO\VersionControlSystemApiResponse\CqrsEndpoints\CqrsEndpointDTO;

interface CqrsEndpointsPresenterInterface
{
    public function present(CqrsEndpointDTO $cqrsEndpointDTO): void;
}
