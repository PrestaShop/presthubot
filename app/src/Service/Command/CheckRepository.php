<?php

namespace App\Service\Command;

use App\DTO\VersionControlSystemApiResponse\Common\RepositoryDTO;
use App\Service\Github\GithubApiCache;
use App\Service\PrestaShop\ModuleFlagAndRate;
use App\Service\PrestaShop\ModuleGlobalStatisticsDTO;

class CheckRepository
{
    private ModuleFlagAndRate $moduleFlagAndRate;
    private ModuleGlobalStatisticsDTO $statistics;
    private GithubApiCache $githubApiCache;

    private int $numberOfRepositories = 0;

    public function __construct(
        ModuleFlagAndRate $moduleFlagAndRate,
        GithubApiCache $githubApiCache
    ) {
        $this->moduleFlagAndRate = $moduleFlagAndRate;
        $this->statistics = new ModuleGlobalStatisticsDTO();
        $this->githubApiCache = $githubApiCache;
    }

    public function getStatistics(): ModuleGlobalStatisticsDTO
    {
        return $this->statistics;
    }

    /**
     * @return iterable|RepositoryDTO
     */
    public function getCheckedRepositories(
        string $org,
        ?string $type
    ): iterable {
        foreach ($this->getRepositories($org, $type) as $repository) {
            yield $repository;
        }
    }

    public function getNumberOfRepositories(): int
    {
        return $this->numberOfRepositories;
    }

    public function getRepositories(
        string $org,
        ?string $type,
    ): array {
        $page = 1;
        $results = [];
        do {
            $repositories = $this->githubApiCache->getOrganizationEndpointRepositories($org, $type, $page);
            ++$page;
            if (null !== $repositories) {
                $results = array_merge($results, $repositories->repositories);
            }
        } while (!empty($repositories->repositories) && null !== $repositories);

        uasort($results, function ($first, $second) {
            return strtolower($first->name) <=> strtolower($second->name);
        });
        $this->numberOfRepositories = count($results);

        return $results;
    }
}
