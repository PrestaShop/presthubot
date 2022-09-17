<?php

namespace App\Service\Command;

use App\DTO\VersionControlSystemApiResponse\ModuleCheck\ModuleCheckDTO;
use App\Service\PrestaShop\ModuleFlagAndRate;
use App\Service\PrestaShop\ModuleFlagsAndRatesDTO;
use App\Service\PrestaShop\ModuleGlobalStatisticsDTO;

class CheckModule
{
    private ModuleFlagAndRate $moduleFlagAndRate;
    private ModuleGlobalStatisticsDTO $statistics;

    public function __construct(
        ModuleFlagAndRate $moduleFlagAndRate,
    ) {
        $this->moduleFlagAndRate = $moduleFlagAndRate;
        $this->statistics = new ModuleGlobalStatisticsDTO();
    }

    public function getStatistics(): ModuleGlobalStatisticsDTO
    {
        return $this->statistics;
    }

    public function getCheckedRepositories(
        ?string $module,
        string $branch,
        ?int $from,
        ?int $numberOfItems
    ): iterable {
        foreach ($this->getRepositories($module, $from, $numberOfItems) as $repository) {
            yield $this->checkRepository('PrestaShop', $repository, $branch);
        }
    }

    public function getNumberOfRepositories(?string $module): int
    {
        $arrayRepositories = $module ? [$module] : $this->getRepositories($module);

        return count($arrayRepositories);
    }

    private function checkRepository(string $org, string $repository, string $branch): ?ModuleCheckDTO
    {
        $report = $this->moduleFlagAndRate->checkRepository($org, $repository, $branch);
        if (null === $report || $report->flags->archived || $report->flags->moved) {
            echo 'Please remove `'.$org.'/'.$repository.'`for the Presthubot analysis'.PHP_EOL;

            return null;
        }
        $this->updateGlobalStatistics($report, $this->statistics);

        return new ModuleCheckDTO(
            $repository,
            $report->flags->url,
            $report->flags->numStargazers,
            $report->flags->numPROpened,
            $report->flags->numFiles,
            $report->flags->hasIssuesOpened,
            $report->flags->numIssuesOpened,
            $report->flags->license,
            $report->flags->labels,
            $report->flags->branches,
            $report->flags->files,
            $report->flags->githubTopics,
            $report->rates->rating_description ?? 0,
            ($this->moduleFlagAndRate->getRatingGlobal($report->rates) / ModuleGlobalStatisticsDTO::RATING_GLOBAL_MAX) * 100)
        ;
    }

    public function getRepositories(
        ?string $module = null,
        ?int $from = null,
        ?int $numberOfItems = null
    ): array {
        $arrayRepositories = null !== $module ? [$module] : $this->moduleFlagAndRate->getModules();

        $numRepositories = count($arrayRepositories);
        if ($numRepositories > 1) {
            $arrayRepositories = array_slice($arrayRepositories, $from, $numberOfItems ?? null);
        }

        return $arrayRepositories;
    }

    public function updateGlobalStatistics(?ModuleFlagsAndRatesDTO $report, ModuleGlobalStatisticsDTO $stats): void
    {
        $stats->branch += $report->rates->rating_branch;
        $stats->description += $report->rates->rating_description;
        $stats->files += $report->rates->rating_files;
        $stats->issues += $report->rates->rating_issues;
        $stats->labels += $report->rates->rating_labels;
        $stats->license += $report->rates->rating_license;
        $stats->topics += $report->rates->rating_topics;
        $stats->all += $report->rates->rating_global;
    }
}
