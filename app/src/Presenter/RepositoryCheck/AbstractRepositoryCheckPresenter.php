<?php

namespace App\Presenter\RepositoryCheck;

use App\DTO\VersionControlSystemApiResponse\Common\LicenseDTO;
use App\DTO\VersionControlSystemApiResponse\Common\RepositoryDTO;

class AbstractRepositoryCheckPresenter implements RepositoryCheckPresenterInterface
{
    protected const SYMBOL_CHECK = 'ok';
    protected const SYMBOL_FAIL = 'fail';
    protected const SYMBOL_CRLF = 'crlf';

    public RepositoryCheckViewModel $viewModel;

    public function present(RepositoryDTO $repository): void
    {
        $this->viewModel = new RepositoryCheckViewModel(
            $repository->name,
            $repository->stargazers_count,
            $repository->description ? $this::SYMBOL_CHECK : $this::SYMBOL_FAIL,
            $this->getStatusSentence((bool) $repository->open_issues_count, $repository->open_issues_count),
            $this->getLicense($repository->license)
        );
    }

    private function getLicense(?LicenseDTO $license): string
    {
        if (null === $license) {
            return $this::SYMBOL_FAIL;
        }

        return $this::SYMBOL_CHECK.$license->spdx_id;
    }

    private function getStatusSentence(
        bool $hasIssueOpened,
        int $numberIssuesOpened
    ): string {
        return 'Closed : '.(!$hasIssueOpened ? $this::SYMBOL_CHECK : $this::SYMBOL_FAIL).$this::SYMBOL_CRLF.'Number : '.$numberIssuesOpened;
    }
}
