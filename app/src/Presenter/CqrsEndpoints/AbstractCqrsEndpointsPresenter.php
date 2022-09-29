<?php

namespace App\Presenter\CqrsEndpoints;

use App\DTO\VersionControlSystemApiResponse\CqrsEndpoints\CqrsEndpointDTO;

class AbstractCqrsEndpointsPresenter implements CqrsEndpointsPresenterInterface
{
    public CqrsEndpointViewModel $viewModel;

    public function present(CqrsEndpointDTO $contributorDTO): void
    {
        $this->viewModel = new CqrsEndpointViewModel(
            $contributorDTO->domain,
            $contributorDTO->type,
            $contributorDTO->name
        );
    }
}
