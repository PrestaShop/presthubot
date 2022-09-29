<?php

namespace App\Presenter\ModuleCheck;

use App\DTO\VersionControlSystemApiResponse\ModuleCheck\ModuleCheckDTO;

interface ModuleCheckPresenterInterface
{
    public function present(ModuleCheckDTO $githubModuleCheckDTO): void;
}
