<?php

namespace App\Presenter\ModuleCheck;

use App\DTO\VersionControlSystemApiResponse\ModuleCheck\ModuleCheckDTO;
use App\Service\PrestaShop\ModuleFlagAndRate;

class AbstractModuleCheckPresenter implements ModuleCheckPresenterInterface
{
    protected const SYMBOL_CHECK = 'ok';
    protected const SYMBOL_FAIL = 'fail';
    protected const SYMBOL_CRLF = 'crlf';

    public ModuleCheckViewModel $viewModel;

    public function present(ModuleCheckDTO $githubModuleCheckDTO): void
    {
        $this->viewModel = new ModuleCheckViewModel(
            $this->getFormattedLink(
                $githubModuleCheckDTO->repositoryLink,
                $githubModuleCheckDTO->repositoryName
            ),
            $this->getFormattedKpis(
                $githubModuleCheckDTO->numberOfStargazers,
                $githubModuleCheckDTO->numberOfPullRequestOpened,
                $githubModuleCheckDTO->numberOfFiles
            ),
            $this->getStatusSentence($githubModuleCheckDTO->hasIssueOpened, $githubModuleCheckDTO->numberIssuesOpened),
            $githubModuleCheckDTO->descriptionRating ? $this::SYMBOL_CHECK : $this::SYMBOL_FAIL,
            ('' !== $githubModuleCheckDTO->license ? $this::SYMBOL_CHECK : $this::SYMBOL_FAIL.' ').$githubModuleCheckDTO->license,
            $this->getFormattedCheckLabels($githubModuleCheckDTO->checkLabels),
            $this->getFormattedCheckBranches($githubModuleCheckDTO->checkBranches),
            $this->getFormattedCheckFiles($githubModuleCheckDTO->checkFiles),
            $this->getFormattedCheckTopics($githubModuleCheckDTO->checkTopics),
            number_format(
                $githubModuleCheckDTO->globalRating,
                2
            ).'%'
        );
    }

    private function getFormattedCheckFiles(array $checkFileInputs): string
    {
        $checkFiles = '';
        foreach ($checkFileInputs as $key => $check) {
            $status = $check[ModuleFlagAndRate::CHECK_FILES_EXIST];
            if (isset($check[ModuleFlagAndRate::CHECK_COMPOSER_VALID])) {
                $status = $status && $check[ModuleFlagAndRate::CHECK_COMPOSER_VALID];
            }
            if (isset($check[ModuleFlagAndRate::CHECK_FILES_CONTAIN])) {
                foreach ($check as $value) {
                    if (ModuleFlagAndRate::CHECK_FILES_EXIST == $check) {
                        continue;
                    }
                    $status = $status && $value;
                }
            }
            if (isset($check[ModuleFlagAndRate::CHECK_FILES_TEMPLATE])) {
                $status = $status && $check[ModuleFlagAndRate::CHECK_FILES_TEMPLATE];
            }
            $checkFiles .= ($status ? $this::SYMBOL_CHECK : $this::SYMBOL_FAIL).' '.$key.$this::SYMBOL_CRLF;
        }

        return $checkFiles;
    }

    private function getFormattedCheckTopics(array $checkTopicInputs): string
    {
        $checkTopics = '';
        foreach ($checkTopicInputs as $topicName => $hasTopic) {
            $checkTopics .= ($hasTopic ? $this::SYMBOL_CHECK : $this::SYMBOL_FAIL).' '.$topicName.$this::SYMBOL_CRLF;
        }

        return $checkTopics;
    }

    private function getFormattedLink(
        string $link,
        string $name
    ): string {
        return sprintf('<href="%s">%s</>', $link, $name);
    }

    private function getFormattedKpis(
        int $numberOfStargazers,
        int $numberOfPullRequestOpened,
        int $numberOfFiles
    ): string {
        return sprintf(
            'Stars : %s%sPR : %s%sFiles : %s',
            $numberOfStargazers,
            $this::SYMBOL_CRLF,
            $numberOfPullRequestOpened,
            $this::SYMBOL_CRLF,
            $numberOfFiles
        );
    }

    private function getFormattedCheckLabels(array $checkLabelInputs): string
    {
        $checkLabels = '';
        foreach ($checkLabelInputs as $key => $value) {
            $lineStart = '';
            $lineEnd = $this::SYMBOL_CRLF;
            $part1 = $value['name'] ? $this::SYMBOL_CHECK : $this::SYMBOL_FAIL;
            $part2 = $value['color'] ? $this::SYMBOL_CHECK : $this::SYMBOL_FAIL;
            $part3 = str_replace('✔️', '✓', $key);
            $checkLabels .= sprintf(
                '%s%s %s %s%s',
                $lineStart,
                $part1,
                $part2,
                $part3,
                $lineEnd,
            );
        }

        return $checkLabels;
    }

    private function getFormattedCheckBranches(array $checkBranches): string
    {
        $branchOutput = 'Branch : ';
        $branchOutput .= $checkBranches['develop'] ? sprintf('%s (%s)', $this::SYMBOL_CHECK, $checkBranches['develop']) : $this::SYMBOL_FAIL;
        $branchOutput .= $this::SYMBOL_CRLF.'Default (dev) : '.(!$checkBranches['isDefault'] ? $this::SYMBOL_FAIL : $this::SYMBOL_CHECK);
        $branchOutput .= $checkBranches['develop'] ? $this::SYMBOL_CRLF.'Status : '.($checkBranches['hasDiffMaster'] ? $this::SYMBOL_FAIL : $this::SYMBOL_CHECK) : '';
        if (!empty($checkBranches['status']) && $checkBranches['status']['ahead'] > 0) {
            $branchOutput .= $this::SYMBOL_CRLF.sprintf('- dev < master by %d commits', $checkBranches['status']['ahead']).$this::SYMBOL_CRLF;
            $branchOutput .= 'THIS MODULE NEEDS A RELEASE';
        }
        if (!empty($checkBranches['status']) && $checkBranches['status']['behind'] > 0) {
            $branchOutput .= $this::SYMBOL_CRLF.sprintf('- master > dev by %d commits', $checkBranches['status']['behind']).$this::SYMBOL_CRLF;
        }

        return $branchOutput;
    }

    private function getStatusSentence(
        bool $hasIssueOpened,
        int $numberIssuesOpened
    ): string {
        return 'Closed : '.(!$hasIssueOpened ? $this::SYMBOL_CHECK : $this::SYMBOL_FAIL).$this::SYMBOL_CRLF.'Number : '.$numberIssuesOpened;
    }
}
