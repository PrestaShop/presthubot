<?php

namespace Console\App\Service\PrestaShop;

use Console\App\Service\Github;
use JsonSchema\Validator;
use Symfony\Component\Yaml\Yaml;

class ModuleChecker
{
    public const RATING_BRANCH = 'rating_branch';
    public const RATING_BRANCH_MAX = 3;
    public const RATING_DESCRIPTION = 'rating_description';
    public const RATING_DESCRIPTION_MAX = 1;
    public const RATING_FILES = 'rating_files';
    public const RATING_FILES_MAX = 27;
    public const RATING_GLOBAL = 'rating_global';
    public const RATING_GLOBAL_MAX = 43;
    public const RATING_ISSUES = 'rating_issues';
    public const RATING_ISSUES_MAX = 1;
    public const RATING_LABELS = 'rating_labels';
    public const RATING_LABELS_MAX = 8;
    public const RATING_LICENSE = 'rating_license';
    public const RATING_LICENSE_MAX = 1;
    public const RATING_TOPICS = 'rating_topics';
    public const RATING_TOPICS_MAX = 2;

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

    protected const RATING_DEFAULT = [
        self::RATING_BRANCH => 0,
        self::RATING_DESCRIPTION => 0,
        self::RATING_FILES => 0,
        self::RATING_ISSUES => 0,
        self::RATING_LABELS => 0,
        self::RATING_LICENSE => 0,
        self::RATING_TOPICS => 0,
    ];

    /**
     * @var Github
     */
    protected $github;

    /**
     * @var array
     */
    protected $rating = self::RATING_DEFAULT;

    /**
     * @var array
     */
    protected $report = [];

    public function __construct(Github $github)
    {
        $this->github = $github;
    }

    public function resetChecker(): self
    {
        $this->rating = self::RATING_DEFAULT;
        $this->report = [
            'archived' => null,
            'moved' => null,
            'url' => null,
            'numStargazers' => null,
            'numPROpened' => null,
            'numFiles' => null,
            'hasIssuesOpened' => null,
            'numIssuesOpened' => null,
            'license' => null,
        ];

        return $this;
    }

    public function checkRepository(string $org, string $repository, string $branch = 'master')
    {
        $repositoryInfo = $this->github->getClient()->api('repo')->show($org, $repository);
        $this->report['archived'] = $repositoryInfo['archived'];
        if ($this->report['archived']) {
            return;
        }
        $this->report['moved'] = ($repositoryInfo['owner']['login'] !== $org);
        if ($this->report['moved']) {
            return;
        }
        $this->report['url'] = $repositoryInfo['html_url'];

        // Title
        //...

        // #
        $numOpenPR = $this->github->getClient()->api('search')->issues('repo:' . $org . '/' . $repository . ' is:open is:pr');
        $this->report['numStargazers'] = $repositoryInfo['stargazers_count'];
        $this->report['numPROpened'] = $numOpenPR['total_count'];
        $this->report['numFiles'] = $this->github->countRepoFiles($org, $repository);

        // Issues
        $this->report['hasIssuesOpened'] = $repositoryInfo['has_issues'];
        $this->report['numIssuesOpened'] = $repositoryInfo['open_issues_count'];
        if (!$this->report['hasIssuesOpened']) {
            $this->report['numIssuesOpened'] = $this->github->getClient()->api('search')->issues(
                'repo:' . $org . '/PrestaShop is:open is:issue label:"' . $repository . '"'
            );
            $this->report['numIssuesOpened'] = $this->report['numIssuesOpened']['total_count'];
            ++$this->rating[self::RATING_ISSUES];
        }

        // Description
        $this->rating[self::RATING_DESCRIPTION] = (!empty($repositoryInfo['description']) ? 1 : 0);

        // License
        $this->report['license'] = $repositoryInfo['license']['spdx_id'];
        $this->rating[self::RATING_LICENSE] = (!empty($this->report['license']) ? 1 : 0);

        // Labels
        $this->checkLabels($org, $repository);

        // Branch
        $this->checkBranches($org, $repository);

        // Files
        $this->checkFiles($org, $repository, $branch);

        // GH Topics
        $this->checkTopics($org, $repository);
    }

    /*
     * @param string $type
     * @return int
     */
    public function getRating(string $type): int
    {
        if ($type == self::RATING_GLOBAL) {
            return \array_sum($this->rating);
        }

        return isset($this->rating[$type]) ? $this->rating[$type] : 0;
    }

    public function getReport()
    {
        return $this->report;
    }

    protected function checkFiles(string $org, string $repository, string $branch)
    {
        $this->report['files'] = [];
        foreach (self::CHECK_FILES as $path => $checks) {
            $dirPHPStan = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, 'tests/php/phpstan', 'refs/heads/' . $branch)
                ? 'tests/php/phpstan' : 'tests/phpstan';
            $path = str_replace('%dirPHPStan%', $dirPHPStan, $path);
            $this->report['files'][$path] = [];
            foreach ($checks as $checkType => $checkData) {
                switch ($checkType) {
                    case self::CHECK_COMPOSER_VALID:
                        $status = false;
                        if ($this->report['files'][$path][self::CHECK_FILES_EXIST]) {
                            $data = $this->github->getClient()->api('repo')->contents()->download($org, $repository, 'composer.json', 'refs/heads/' . $branch);
                            $data = json_decode($data);
                            $validator = new Validator();
                            $validator->validate($data, (object) ['$ref' => 'https://getcomposer.org/schema.json']);
                            $status = $validator->isValid();
                        }
                        $this->report['files'][$path][$checkType] = $status;
                        $this->rating[self::RATING_FILES] += $status ? 1 : 0;
                        break;
                    case self::CHECK_FILES_EXIST:
                        $isExist = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, $path, 'refs/heads/' . $branch);
                        $this->report['files'][$path][$checkType] = ($isExist == $checkData);
                        $this->rating[self::RATING_FILES] += ($isExist == $checkData) ? 1 : 0;
                        break;
                    case self::CHECK_FILES_CONTAIN:
                        $contents = $this->report['files'][$path][self::CHECK_FILES_EXIST]
                            ? $this->github->getClient()->api('repo')->contents()->download($org, $repository, $path, 'refs/heads/' . $branch)
                            : '';
                        $allContains = true;
                        foreach ($checkData as $value) {
                            $allContains = (strpos($contents, $value) !== false);
                            if (!$allContains) {
                                break;
                            }
                        }
                        $this->report['files'][$path][$checkType] = $allContains;
                        $this->rating[self::RATING_FILES] += $allContains ? 1 : 0;
                        break;
                    case self::CHECK_FILES_TEMPLATE:
                        $status = false;
                        if ($this->report['files'][$path][self::CHECK_FILES_EXIST]) {
                            // File available on the repository
                            $contents = $this->github->getClient()->api('repo')->contents()->download($org, $repository, $path, 'refs/heads/' . $branch);
                            // Template
                            $checkData = \is_array($checkData) ? $checkData : [$checkData];
                            foreach ($checkData as $checkDataPath) {
                                $template = file_get_contents($checkDataPath);

                                if (\in_array(pathinfo($checkDataPath, PATHINFO_EXTENSION), ['yml', 'sh'])) {
                                    $yaml = Yaml::parse($contents);
                                    $prestaVersions = $yaml['jobs']['phpstan']['strategy']['matrix']['presta-versions'] ?? [];
                                    $prestaVersions = array_map(function ($value) {
                                        return "'" . $value . "'";
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

                        $this->report['files'][$path][$checkType] = $status;
                        $this->rating[self::RATING_FILES] += $status ? 1 : 0;
                        break;
                }
            }
        }
    }

    protected function checkTopics(string $org, string $repository)
    {
        $topics = $this->github->getRepoTopics($org, $repository);
        $this->report['githubTopics'] = [];
        foreach (self::CHECK_TOPICS as $ghTopic) {
            $this->report['githubTopics'][$ghTopic] = in_array($ghTopic, $topics);
            $this->rating[self::RATING_TOPICS] += ($this->report['githubTopics'][$ghTopic] ? 1 : 0);
        }
    }

    protected function checkLabels(string $org, string $repository)
    {
        $labelsInfo = $this->github->getClient()->api('issue')->labels()->all($org, $repository);
        $labels = [];
        foreach ($labelsInfo as $info) {
            $labels[$info['name']] = $info['color'];
        }
        $this->report['labels'] = [];
        foreach (self::CHECK_LABELS as $name => $color) {
            $this->report['labels'][$name] = [
                'name' => (in_array($name, array_keys($labels))),
                'color' => (in_array($name, array_keys($labels)) && $labels[$name] == $color),
            ];
            $this->rating[self::RATING_LABELS] += ($this->report['labels'][$name]['name'] ? 1 : 0) + ($this->report['labels'][$name]['color'] ? 1 : 0);
        }
    }

    public function checkBranches(string $org, string $repository): array
    {
        // Fetch main branch from the repository
        $repositoryInfo = $this->github->getClient()->api('repo')->show($org, $repository);
        $mainBranch = $repositoryInfo['default_branch'];

        // Fetch branches from Github
        $references = $this->github->getClient()->api('gitData')->references()->branches($org, $repository);
        $branches = [];
        foreach ($references as $info) {
            $branches[str_replace('refs/heads/', '', $info['ref'])] = $info['object']['sha'];
        }

        // Fetch pulls requests
        $pullRequests = $this->github->getClient()->api('pull_request')->all($org, $repository);
        $hasPRRelease = array_reduce(
            $pullRequests,
            function (bool $carry, array $item) {
                return $carry ?: strpos($item['title'], 'Release ') === 0;
            },
            false
        );

        // Name of develop branch
        $this->report['branch'] = [];
        $this->report['branch']['develop'] = (array_key_exists('dev', $branches) ? 'dev' : '');
        $this->report['branch']['status'] = $this->report['branch']['develop'] === '' ? null : $this->findReleaseStatus($references, $org, $repository);
        $this->report['branch']['isDefault'] = $mainBranch === 'dev';
        $this->report['branch']['hasDiffMaster'] = (!empty($this->report['branch']['status']) && $this->report['branch']['status']['ahead'] > 0);
        $this->report['branch']['hasPRRelease'] = $hasPRRelease;

        $this->rating[self::RATING_BRANCH] +=
            ($this->report['branch']['develop'] ? 1 : 0)
            + (!$this->report['branch']['hasDiffMaster'] ? 1 : 0)
            + ($this->report['branch']['isDefault'] ? 1 : 0);

        return $this->report['branch'];
    }

    /**
     * @param array[] $references branch github data
     * @param string $repository repository name
     *
     * @return array
     */
    private function findReleaseStatus(array $references, string $org, string $repository)
    {
        $devBranchData = $masterBranchData = [];
        foreach ($references as $branchID => $branchData) {
            $branchName = $branchData['ref'];

            if ($branchName === 'refs/heads/dev') {
                $devBranchData = $branchData;
            }
            if ($branchName === 'refs/heads/develop') {
                $devBranchData = $branchData;
            }
            if ($branchName === 'refs/heads/master') {
                $masterBranchData = $branchData;
            }
        }

        $devLastCommitSha = $devBranchData['object']['sha'];
        $masterLastCommitSha = $masterBranchData['object']['sha'];

        $comparison = $this->github->getClient()->api('repo')->commits()->compare(
            $org,
            $repository,
            $masterLastCommitSha,
            $devLastCommitSha
        );
        $numPRMerged = 0;
        foreach ($comparison['commits'] as $commit) {
            $numPRMerged += (strpos($commit['commit']['message'], 'Merge pull request #') === 0 ? 1 : 0);
        }

        return [
            'behind' => $comparison['behind_by'],
            'ahead' => $comparison['ahead_by'],
            'numPRMerged' => $numPRMerged,
        ];
    }
}
