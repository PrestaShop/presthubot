<?php

namespace App\Presenter\Contributors\Web;

use App\DTO\Presenter\ContributorDTO;
use App\Presenter\Contributors\AbstractContributorPresenter;
use App\Presenter\Contributors\ContributorsViewModel;

class ContributorsPresenterWeb extends AbstractContributorPresenter
{
    public function present(ContributorDTO $contributorDTO): void
    {
        $this->viewModel = new ContributorsViewModel(
            $contributorDTO->repository,
            $contributorDTO->pullRequestNumber,
            '<a href='.$contributorDTO->pullRequestUrl.'>'.$contributorDTO->pullRequestNumber.'</a>',
            $contributorDTO->isBugOrImprovement,
            $contributorDTO->status,
            $contributorDTO->branch,
            $contributorDTO->pullRequestCreatedAt?$contributorDTO->pullRequestCreatedAt->format("Y-m-d"):'',
            $contributorDTO->pullRequestClosedAt?$contributorDTO->pullRequestClosedAt->format("Y-m-d"):'',
            $contributorDTO->merged?'Yes':'No',
            $contributorDTO->author,
            $contributorDTO->linkedIssue,
            $contributorDTO->linkedIssue?"<a href='.$contributorDTO->linkedIssueUrl.'>$contributorDTO->linkedIssue</a>":'',
            $contributorDTO->severityIssue,
            $contributorDTO->linkedIssueComment,
            $contributorDTO->pullRequestComments
        );
    }
}
