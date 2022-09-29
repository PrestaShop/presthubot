<?php

namespace App\Presenter\Contributors;

use App\DTO\Presenter\ContributorDTO;

class AbstractContributorPresenter implements ContributorsPresenterInterface
{
    public ContributorsViewModel $viewModel;

    public function present(ContributorDTO $contributorDTO): void
    {
        $this->viewModel = new ContributorsViewModel(
         $contributorDTO->repository,
         $contributorDTO->pullRequestNumber,
         '<href='.$contributorDTO->pullRequestUrl.'>click here</a>',
         $contributorDTO->isBugOrImprovement,
         $contributorDTO->status,
         $contributorDTO->branch,
         $contributorDTO->pullRequestCreatedAt?$contributorDTO->pullRequestCreatedAt->format("Y-m-d"):'',
         $contributorDTO->pullRequestClosedAt?$contributorDTO->pullRequestClosedAt->format("Y-m-d"):'',
         $contributorDTO->merged?'Yes':'No',
         $contributorDTO->author,
         $contributorDTO->linkedIssue,
            '<href='.$contributorDTO->linkedIssueUrl.'>click here</a>',
         $contributorDTO->severityIssue,
         $contributorDTO->linkedIssueComment,
         $contributorDTO->pullRequestComments
        );
    }
}
