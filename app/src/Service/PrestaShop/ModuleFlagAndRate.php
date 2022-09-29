<?php

namespace App\Service\PrestaShop;

use App\DTO\VersionControlSystemApiResponse\BranchesReferences\BranchesReferenceDTO;
use App\DTO\VersionControlSystemApiResponse\BranchesReferences\BranchesReferencesDTO;
use App\DTO\VersionControlSystemApiResponse\Common\RepositoryDTO;
use App\Service\Github\GithubApiCache;

use function in_array;
use function is_array;

use JsonSchema\Validator;
use Symfony\Component\Yaml\Yaml;

class ModuleFlagAndRate
{
    public const CHECK_TOPICS = ['prestashop', 'prestashop-module'];

    public const CHECK_FILES = [
        'README.md' => [
            self::CHECK_FILES_EXIST => true,
        ],
        'CONTRIBUTORS.md' => [
            self::CHECK_FILES_EXIST => true,
        ],
        'CHANGELOG.txt' => [
            self::CHECK_FILES_EXIST => true,
        ],
        'composer.json' => [
            self::CHECK_FILES_EXIST => true,
            self::CHECK_COMPOSER_VALID => true,
        ],
        'composer.lock' => [
            self::CHECK_FILES_EXIST => true,
        ],
        'config.xml' => [
            self::CHECK_FILES_EXIST => true,
        ],
        'logo.png' => [
            self::CHECK_FILES_EXIST => true,
        ],
        '%dirPHPStan%.sh' => [
            self::CHECK_FILES_EXIST => true,
            self::CHECK_FILES_TEMPLATE => 'var/data/templates/tests/phpstan.sh',
        ],
        '.github/dependabot.yml' => [
            self::CHECK_FILES_EXIST => true,
            self::CHECK_FILES_TEMPLATE => 'var/data/templates/.github/dependabot.yml',
        ],
        '.github/mktp-metadata.json' => [
            self::CHECK_FILES_EXIST => true,
        ],
        '.github/release-drafter.yml' => [
            self::CHECK_FILES_EXIST => true,
            self::CHECK_FILES_TEMPLATE => 'var/data/templates/.github/release-drafter.yml',
        ],
        '.github/workflows/build-release.yml' => [
            self::CHECK_FILES_EXIST => true,
            self::CHECK_FILES_TEMPLATE => [
                'var/data/templates/.github/workflows/build-release_with_js.yml',
                'var/data/templates/.github/workflows/build-release_without_js.yml',
            ],
        ],
        '.github/workflows/js.yml' => [
            self::CHECK_FILES_EXIST => true,
        ],
        '.github/workflows/php.yml' => [
            self::CHECK_FILES_EXIST => true,
            self::CHECK_FILES_TEMPLATE => [
                'var/data/templates/.github/workflows/php_with_tests.yml',
                'var/data/templates/.github/workflows/php_without_tests.yml',
            ],
        ],
        '.github/workflows/publish-to-marketplace.yml' => [
            self::CHECK_FILES_EXIST => true,
            self::CHECK_FILES_TEMPLATE => 'var/data/templates/.github/workflows/publish-to-marketplace.yml',
        ],
        '.github/PULL_REQUEST_TEMPLATE.md' => [
            self::CHECK_FILES_EXIST => true,
            self::CHECK_FILES_TEMPLATE => 'var/data/templates/.github/PULL_REQUEST_TEMPLATE.md',
        ],
        '.gitignore' => [
            self::CHECK_FILES_EXIST => true,
            self::CHECK_FILES_CONTAIN => ['vendor'],
        ],
        '.travis.yml' => [
            self::CHECK_FILES_EXIST => false,
        ],
    ];
    public const CHECK_FILES_EXIST = 1;
    public const CHECK_FILES_CONTAIN = 2;
    public const CHECK_FILES_TEMPLATE = 3;
    public const CHECK_COMPOSER_VALID = 4;

    public const CHECK_LABELS = [
        'waiting for QA' => 'fbca04',
        'QA ✔️' => 'b8ed50',
        'waiting for author' => 'fbca04',
        'waiting for PM' => 'fbca04',
    ];

    protected GithubApiCache $githubApiCache;

    public function __construct(GithubApiCache $githubApiCache)
    {
        $this->githubApiCache = $githubApiCache;
    }

    private function checkRepositoryIsValid(ModuleFlagsAndRatesDTO $report, RepositoryDTO $repositoryInfo, string $org): bool
    {
        $report->flags->archived = $repositoryInfo->archived;
        if ($report->flags->archived) {
            return false;
        }
        $report->flags->moved = ($repositoryInfo->owner->login !== $org);
        if ($report->flags->moved) {
            return false;
        }

        return true;
    }

    public function checkRepository(string $org, string $repository, string $branch = 'master'): ?ModuleFlagsAndRatesDTO
    {
        $repositoryInfo = $this->githubApiCache->getRepoEndpointShow($org, $repository);
        $report = new ModuleFlagsAndRatesDTO();
        if (!$this->checkRepositoryIsValid($report, $repositoryInfo, $org)) {
            return null;
        }
        $report->flags->url = $repositoryInfo->html_url;
        $this->analyzeKpis($repositoryInfo, $report, $org, $repository);
        $this->analyzeIssues($repositoryInfo, $report, $org, $repository);
        $this->analyzeDescription($repositoryInfo, $report);
        $this->analyzeLicense($repositoryInfo, $report);
        $this->analyzeLabels($org, $repository, $report);
        $this->analyzeBranches($org, $repository, $report);
        $this->analyzeFiles($org, $repository, $branch, $report);
        $this->analyzeTopics($org, $repository, $report);

        return $report;
    }

    public function getRatingGlobal(ModuleRatesDTO $rates): int
    {
        return $rates->getSum();
    }

    protected function analyzeFiles(string $org, string $repository, string $branch, ModuleFlagsAndRatesDTO $report): void
    {
        $report->flags->files = [];
        foreach (self::CHECK_FILES as $path => $checks) {
            $dirPHPStan = $this->githubApiCache->getRepoEndpointContentsExists($org, $repository, 'tests/php/phpstan', 'refs/heads/'.$branch)
                ? 'tests/php/phpstan' : 'tests/phpstan';
            $path = str_replace('%dirPHPStan%', $dirPHPStan, $path);
            $report->flags->files[$path] = [];
            foreach ($checks as $checkType => $checkData) {
                switch ($checkType) {
                    case self::CHECK_COMPOSER_VALID:
                        $status = false;
                        if ($report->flags->files[$path][self::CHECK_FILES_EXIST]) {
                            $data = $this->githubApiCache->getRepoEndpointContentsDownload($org, $repository, 'composer.json', 'refs/heads/'.$branch);
                            $data = json_decode($data);
                            $validator = new Validator();
                            $validator->validate($data, (object) ['$ref' => 'https://getcomposer.org/schema.json']);
                            $status = $validator->isValid();
                        }
                        $report->flags->files[$path][$checkType] = $status;
                        $report->rates->rating_files += $status ? 1 : 0;
                        break;
                    case self::CHECK_FILES_EXIST:
                        $isExist = $this->githubApiCache->getRepoEndpointContentsExists($org, $repository, $path, 'refs/heads/'.$branch);
                        $report->flags->files[$path][$checkType] = ($isExist == $checkData);
                        $report->rates->rating_files += ($isExist == $checkData) ? 1 : 0;
                        break;
                    case self::CHECK_FILES_CONTAIN:
                        $contents = $report->flags->files[$path][self::CHECK_FILES_EXIST]
                            ? $this->githubApiCache->getRepoEndpointContentsDownload($org, $repository, $path, 'refs/heads/'.$branch)
                            : '';
                        $allContains = true;
                        foreach ($checkData as $value) {
                            $allContains = str_contains($contents, $value);
                            if (!$allContains) {
                                break;
                            }
                        }
                        $report->flags->files[$path][$checkType] = $allContains;
                        $report->rates->rating_files += $allContains ? 1 : 0;
                        break;
                    case self::CHECK_FILES_TEMPLATE:
                        $status = false;
                        if ($report->flags->files[$path][self::CHECK_FILES_EXIST]) {
                            // File available on the repository
                            $contents = $this->githubApiCache->getRepoEndpointContentsDownload($org, $repository, $path, 'refs/heads/'.$branch);
                            // Template
                            $checkData = is_array($checkData) ? $checkData : [$checkData];
                            foreach ($checkData as $checkDataPath) {
                                $template = file_get_contents($checkDataPath);

                                if (in_array(pathinfo($checkDataPath, PATHINFO_EXTENSION), ['yml', 'sh'])) {
                                    $yaml = Yaml::parse($contents);
                                    $prestaVersions = $yaml['jobs']['phpstan']['strategy']['matrix']['presta-versions'] ?? [];
                                    $prestaVersions = array_map(function ($value) {
                                        return "'".$value."'";
                                    }, $prestaVersions);
                                    $prestaVersions = implode(', ', $prestaVersions);

                                    $template = str_replace('%module%', $repository, $template);
                                    $template = str_replace('%prestaVersions%', $prestaVersions, $template);
                                    $template = str_replace('%dirPHPStan%', $dirPHPStan, $template);
                                }

                                $status = $contents === $template;
                                if ($status) {
                                    break;
                                }
                            }
                        }

                        $report->flags->files[$path][$checkType] = $status;
                        $report->rates->rating_files += $status ? 1 : 0;
                        break;
                }
            }
        }
    }

    protected function analyzeTopics(string $org, string $repository, ModuleFlagsAndRatesDTO $report): void
    {
        $topics = $this->githubApiCache->getRepoTopics($org, $repository);
        $report->flags->githubTopics = [];
        // search mandatory topics
        foreach (self::CHECK_TOPICS as $ghTopic) {
            $report->flags->githubTopics[$ghTopic] = in_array($ghTopic, $topics->names);
            $report->rates->rating_topics += ($report->flags->githubTopics[$ghTopic] ? 1 : 0);
        }
        // add additional topics
        foreach ($topics->names as $name) {
            $report->flags->githubTopics[$name] = true;
            $report->rates->rating_topics += ($report->flags->githubTopics[$name] ? 1 : 0);
        }
    }

    protected function analyzeLabels(string $org, string $repository, ModuleFlagsAndRatesDTO $report): void
    {
        $labelsInfo = $this->githubApiCache->getIssueEndpointLabelsAll($org, $repository);
        $labels = [];
        foreach ($labelsInfo->labels as $info) {
            $labels[$info->name] = $info->color;
        }
        $report->flags->labels = [];
        foreach (self::CHECK_LABELS as $name => $color) {
            $report->flags->labels[$name] = [
                'name' => in_array($name, array_keys($labels)),
                'color' => (in_array($name, array_keys($labels)) && $labels[$name] === $color),
            ];
            $report->rates->rating_labels += ($report->flags->labels[$name]['name'] ? 1 : 0) + ($report->flags->labels[$name]['color'] ? 1 : 0);
        }
    }

    public function analyzeBranches(string $org, string $repository, ModuleFlagsAndRatesDTO $report): void
    {
        // Fetch main branch from the repository
        $repositoryInfo = $this->githubApiCache->getRepoEndpointShow($org, $repository);
        $mainBranch = $repositoryInfo->default_branch;

        // Fetch branches from Github
        $references = $this->githubApiCache->getGitDataEndpointReferencesBranches($org, $repository);
        $branches = [];
        foreach ($references->branchesReferences as $info) {
            $branches[str_replace('refs/heads/', '', $info->ref)] = $info->object->sha;
        }

        // Fetch pulls requests
        $pullRequests = $this->githubApiCache->getPullRequestEndpointAll($org, $repository, []);
        $hasPRRelease = false;
        foreach ($pullRequests->items as $pullRequest) {
            if ($pullRequest->title && str_starts_with($pullRequest->title, 'Release ')) {
                $hasPRRelease = true;
            }
        }
        // Name of develop branch
        $report->flags->branches = [];
        $report->flags->branches['develop'] = (array_key_exists('dev', $branches) ? 'dev' : '');
        $report->flags->branches['status'] = '' === $report->flags->branches['develop'] ? null : $this->findReleaseStatus($references, $org, $repository);
        $report->flags->branches['isDefault'] = 'dev' === $mainBranch;
        $report->flags->branches['hasDiffMaster'] = (!empty($report->flags->branches['status']) && $report->flags->branches['status']['ahead'] > 0);
        $report->flags->branches['hasPRRelease'] = $hasPRRelease;

        $report->rates->rating_branch +=
            ($report->flags->branches['develop'] ? 1 : 0)
            + (!$report->flags->branches['hasDiffMaster'] ? 1 : 0)
            + ($report->flags->branches['isDefault'] ? 1 : 0);
    }

    private function findReleaseStatus(BranchesReferencesDTO $references, string $org, string $repository): array
    {
        $devBranchData = $masterBranchData = new BranchesReferenceDTO();
        foreach ($references->branchesReferences as $branchData) {
            $branchName = $branchData->ref;
            if ('refs/heads/dev' === $branchName || 'refs/heads/develop' === $branchName) {
                $devBranchData = $branchData;
            }
            if ('refs/heads/master' === $branchName || 'refs/heads/main' === $branchName) {
                $masterBranchData = $branchData;
            }
        }

        $devLastCommitSha = $devBranchData->object->sha;
        $masterLastCommitSha = $masterBranchData->object->sha ?? '';

        $comparison = $this->githubApiCache->getRepoEndpointCommitsCompare(
            $org,
            $repository,
            $masterLastCommitSha,
            $devLastCommitSha
        );
        $numPRMerged = 0;
        foreach ($comparison->commits as $commit) {
            $numPRMerged += (str_starts_with($commit->commit->message, 'Merge pull request #') ? 1 : 0);
        }

        return [
            'behind' => $comparison->behind_by,
            'ahead' => $comparison->ahead_by,
            'numPRMerged' => $numPRMerged,
        ];
    }

    public function getModules(): array
    {
        $contents = $this->githubApiCache->getRepoEndpointContentsShow(
            'PrestaShop',
            'PrestaShop-modules'
        );
        $modules = [];
        foreach ($contents->contents as $content) {
            if (!empty($content->download_url)) {
                continue;
            }
            $modules[] = $content->name;
        }

        return $modules;
    }

    public function analyzeKpis(RepositoryDTO $repositoryInfo, ModuleFlagsAndRatesDTO $report, string $org, string $repository): void
    {
        $numOpenPR = $this->githubApiCache->getSearchEndpointIssues('repo:'.$org.'/'.$repository.' is:open is:pr');
        $report->flags->numStargazers = $repositoryInfo->stargazers_count;
        $report->flags->numPROpened = $numOpenPR->total_count;
        $report->flags->numFiles = $this->githubApiCache->countRepoFiles($org, $repository);
    }

    public function analyzeIssues(RepositoryDTO $repositoryInfo, ModuleFlagsAndRatesDTO $report, string $org, string $repository): void
    {
        $report->flags->hasIssuesOpened = $repositoryInfo->has_issues;
        $report->flags->numIssuesOpened = $repositoryInfo->open_issues_count;
        if (!$report->flags->hasIssuesOpened) {
            $issues = $this->githubApiCache->getSearchEndpointIssues('repo:'.$org.'/PrestaShop is:open is:issue label:"'.$repository.'"');
            $report->flags->numIssuesOpened = $issues->total_count;
            ++$report->rates->rating_issues;
        }
    }

    public function analyzeDescription(RepositoryDTO $repositoryInfo, ModuleFlagsAndRatesDTO $report): void
    {
        $report->rates->rating_description = (!empty($repositoryInfo->description) ? 1 : 0);
    }

    public function analyzeLicense(RepositoryDTO $repositoryInfo, ModuleFlagsAndRatesDTO $report): void
    {
        $report->flags->license = $repositoryInfo->license->spdx_id ?? null;
        $report->rates->rating_license = !empty($report->flags->license) ? 1 : 0;
    }
}
