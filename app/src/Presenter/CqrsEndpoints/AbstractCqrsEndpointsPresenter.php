<?php

namespace App\Presenter\CqrsEndpoints;

use App\DTO\VersionControlSystemApiResponse\CqrsEndpoints\CqrsEndpointDTO;

class AbstractCqrsEndpointsPresenter implements CqrsEndpointsPresenterInterface
{
    public CqrsEndpointViewModel $viewModel;

    public function present(CqrsEndpointDTO $cqrsEndpointDTO): void
    {
        $this->viewModel = new CqrsEndpointViewModel(
            $cqrsEndpointDTO->domain,
            $cqrsEndpointDTO->type,
            $cqrsEndpointDTO->name
        );
    }
}
