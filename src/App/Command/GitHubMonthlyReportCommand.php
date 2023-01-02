<?php

namespace Console\App\Command;

use Console\App\Service\Github;
use Console\App\Service\Github\Query;
use Console\App\Service\PrestaShop\ModuleFetcher;
use Console\App\Service\PrestaShop\RepositoryNameConverter;
use Github\Exception\RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GitHubMonthlyReportCommand extends Command
{
    private const CATEGORIES = [
        'CO' => 'Core',
        'BO' => 'Back office',
        'FO' => 'Front office',
        'IN' => 'Installer',
        'WS' => 'Web services',
        'TE' => 'Tests',
        'ME' => 'Merge',
        'Misc' => 'Misc',
    ];

    private const CATEGORIES_REJECT_LIST = [
        'ME',
        'PM',
    ];

    private const CORE_BRANCHES = [
        'develop',
        '8.0.x',
        '1.7.8.x',
    ];

    private const IGNORED_REPOSITORIES = '-repo:prestashop/prestashop.github.io';

    /**
     * @var Github
     */
    protected $github;

    /**
     * @var string
     */
    protected $dateStart;

    /**
     * @var string
     */
    protected $dateEnd;

    /**
     * @var array{'issues_opened': int, 'issues_closed': int, 'issues_fixed': int, 'prs_opened': int, 'prs_closed': int, 'prs_merged': int, 'releases': string, 'core_prs': string, 'other_prs': string, 'contributors': string}
     */
    protected $results = [
        'issues_opened' => 0,
        'issues_closed' => 0,
        'issues_fixed' => 0,
        'prs_opened' => 0,
        'prs_closed' => 0,
        'prs_merged' => 0,
        'releases' => '',
        'core_prs' => '',
        'other_prs' => '',
        'contributors' => '',
    ];

    protected function configure()
    {
        $this->setName('github:monthly:report')
            ->setDescription('Track all issues created in the last month (4 weeks) for the PrestaShop project')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN'] ?? null
            )
            ->addOption(
                'outputDir',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                'var/report/monthly'
            )
            ->addOption(
                'month',
                null,
                InputOption::VALUE_OPTIONAL,
                'last'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->github = new Github($input->getOption('ghtoken'));

        // Get Stats
        $time = time();
        $result = $this->assertInput($input, $output);
        if ($result) {
            $this->generateReport($input, $output);
        }
        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);

        return 0;
    }

    private function assertInput(InputInterface $input, OutputInterface $output): bool
    {
        $month = $input->getOption('month') ?? 'last';
        if (!in_array($month, ['last', 'this', 'current'])) {
            $output->writeLn(['', 'Please provide a value for a "month" argument (last or this) to generate the report.']);

            return false;
        }

        switch ($month) {
            case 'last':
                $this->dateStart = date('Y-m-d', strtotime('first day of last month'));
                $this->dateEnd = date('Y-m-d', strtotime('last day of last month'));
                break;
            default:
                $this->dateStart = date('Y-m-d', strtotime('first day of this month'));
                $this->dateEnd = date('Y-m-d', strtotime('last day of this month'));
                break;
        }

        return true;
    }

    private function generateReport(InputInterface $input, OutputInterface $output): void
    {
        $reportFilename = 'core-monthly-' . $this->dateStart . '-' . $this->dateEnd . '.md';
        $reportPath = $input->getOption('outputDir') . DIRECTORY_SEPARATOR . $reportFilename;

        $this->searchForReleases();
        $this->searchForIssuesData();
        $this->searchForPullRequests();

        $output->writeln('Writing monthly report...');
        $template = file_get_contents('var/data/templates/core-monthly.md');

        $template = str_replace(
            [
                '{date_start}',
                '{date_end}',
                '{month_name}',
                '{issues_opened}',
                '{issues_closed}',
                '{issues_fixed}',
                '{prs_opened}',
                '{prs_closed}',
                '{prs_merged}',
                '{releases}',
                '{core_prs}',
                '{other_prs}',
                '{contributors}',
            ],
            [
                $this->dateStart,
                $this->dateEnd,
                date('F', strtotime($this->dateStart)),
                $this->results['issues_opened'],
                $this->results['issues_closed'],
                $this->results['issues_fixed'],
                $this->results['prs_opened'],
                $this->results['prs_closed'],
                $this->results['prs_merged'],
                $this->results['releases'],
                $this->results['core_prs'],
                $this->results['other_prs'],
                $this->results['contributors'],
            ],
            $template
        );

        file_put_contents($reportPath, $template);

        $output->writeln("Core monthly report $reportFilename has been written.");
    }

    private function searchForIssuesData(): void
    {
        $graphQLQuery = new Query();
        $graphQLQuery->setQuery('type:issue created:' . $this->dateStart . '..' . $this->dateEnd . ' repo:PrestaShop/PrestaShop');
        $createdIssues = $this->github->search($graphQLQuery);

        $this->results['issues_opened'] = count($createdIssues);

        $graphQLQuery = new Query();
        $graphQLQuery->setQuery('type:issue closed:' . $this->dateStart . '..' . $this->dateEnd . ' repo:PrestaShop/PrestaShop');
        $closedIssues = $this->github->search($graphQLQuery);

        $this->results['issues_closed'] = count($closedIssues);

        $graphQLQuery = new Query();
        $graphQLQuery->setQuery('type:issue label:fixed closed:' . $this->dateStart . '..' . $this->dateEnd . ' repo:PrestaShop/PrestaShop');
        $fixedIssues = $this->github->search($graphQLQuery);

        $this->results['issues_fixed'] = count($fixedIssues);
    }

    private function searchForPullRequests(): void
    {
        $graphQLQuery = new Query();
        $graphQLQuery->setQuery('org:PrestaShop is:pr is:public created:' . $this->dateStart . '..' . $this->dateEnd . ' sort:created-desc ' . self::IGNORED_REPOSITORIES);
        $pullRequestsOpened = $this->github->search($graphQLQuery);
        $this->results['prs_opened'] = count($pullRequestsOpened);

        $graphQLQuery = new Query();
        $graphQLQuery->setQuery('org:PrestaShop is:pr is:public closed:' . $this->dateStart . '..' . $this->dateEnd . ' sort:created-desc ' . self::IGNORED_REPOSITORIES);
        $pullRequestsClosed = $this->github->search($graphQLQuery);
        $this->results['prs_closed'] = count($pullRequestsClosed);

        $graphQLQuery = new Query();
        $graphQLQuery->setQuery('org:PrestaShop is:pr is:public merged:' . $this->dateStart . '..' . $this->dateEnd . ' sort:created-desc ' . self::IGNORED_REPOSITORIES);
        $pullRequestsMerged = $this->github->search($graphQLQuery);
        $this->results['prs_merged'] = count($pullRequestsMerged);

        $corePullRequests = [];
        $otherPullRequests = [];
        $contributors = [];

        foreach ($pullRequestsMerged as $pullRequest) {
            $pullRequest = $pullRequest['node'];
            $body = $pullRequest['body'];

            $branchName = $this->extractInformationFromBody($body, 'Branch');
            $category = $this->extractInformationFromBody($body, 'Category');

            if (in_array($category, self::CATEGORIES_REJECT_LIST)) {
                continue;
            }

            $pullRequestData = [
                'number' => $pullRequest['number'],
                'title' => $pullRequest['title'],
                'url' => $pullRequest['url'],
                'author' => $pullRequest['author']['login'],
                'repository' => $pullRequest['repository']['name'],
            ];

            $contributors[] = $pullRequest['author']['login'];

            if ($pullRequest['repository']['name'] == 'PrestaShop') {
                // Dependabot PRs are always for the develop branch and category = TE
                if ($pullRequest['author']['login'] == 'dependabot') {
                    $branchName = 'develop';
                    $category = 'TE';
                }

                if (!in_array($branchName, self::CORE_BRANCHES)) {
                    continue;
                }

                $corePullRequests[$branchName][$this->convertCategoryName($category)][] = $pullRequestData;
            } else {
                $otherPullRequests[$pullRequest['repository']['name']][] = $pullRequestData;
            }
        }

        // Core PRs
        $corePullRequestsContent = '';

        foreach ($corePullRequests as $branchName => $categories) {
            ksort($categories);

            $corePullRequestsContent .= '## Code changes in the ‘' . $branchName . '’ branch ' . PHP_EOL;

            foreach ($categories as $categoryName => $pullRequests) {
                $corePullRequestsContent .= '### ' . $categoryName . PHP_EOL;

                foreach ($pullRequests as $pullRequest) {
                    $corePullRequestsContent .= $this->renderPullRequestLine($pullRequest);
                }
            }
        }

        // Modules, tools, etc.
        $otherPullRequestsContent = '';
        $lastRepositoryName = '';
        $toolsPullRequests = [];

        // Change keys to repository names
        foreach ($otherPullRequests as $repositoryName => $pullRequest) {
            $toolsPullRequests[ucfirst(RepositoryNameConverter::getName($repositoryName))] = $pullRequest;
        }

        ksort($toolsPullRequests, SORT_NATURAL);

        foreach ($toolsPullRequests as $repositoryName => $pullRequests) {
            if ($lastRepositoryName != $repositoryName) {
                $lastRepositoryName = $repositoryName;
                $otherPullRequestsContent .= '### ' . $repositoryName . PHP_EOL;
            }

            foreach ($pullRequests as $pullRequest) {
                $otherPullRequestsContent .= $this->renderPullRequestLine($pullRequest);
            }
        }

        // Contributors
        $contributors = array_unique($contributors);
        asort($contributors);
        $contributorsFormatted = [];
        foreach ($contributors as $contributor) {
            $contributorsFormatted[] = '[@' . $contributor . '](https://github.com/' . $contributor . ')';
        }

        $this->results['contributors'] = implode(', ', $contributorsFormatted);
        $this->results['core_prs'] = $corePullRequestsContent;
        $this->results['other_prs'] = $otherPullRequestsContent;
    }

    private function renderPullRequestLine($pullRequest): string
    {
        return '* [#' . $pullRequest['number'] . '](' . $pullRequest['url'] . '): ' . $pullRequest['title'] . '. Thank you, [@' . $pullRequest['author'] . '](https://github.com/' . $pullRequest['author'] . ')' . PHP_EOL;
    }

    private function convertCategoryName(string $category): string
    {
        $category = trim($category);

        if (isset(self::CATEGORIES[$category])) {
            return self::CATEGORIES[$category];
        }

        return $category;
    }

    private function searchForReleases(): void
    {
        $moduleFetcher = new ModuleFetcher($this->github);
        $repositories = ['PrestaShop'] + $moduleFetcher->getModules();
        $releasesData = [];

        foreach ($repositories as $repository) {
            try {
                $release = $this->github->getClient()->api('repo')->releases()->latest(
                    'prestashop',
                    $repository
                );

                $publishedAt = \DateTime::createFromFormat(
                    \DateTime::RFC3339,
                    $release['published_at']
                );

                if (new \DateTime($this->dateEnd) < $publishedAt || new \DateTime($this->dateStart) > $publishedAt) {
                    continue;
                }

                $releasesData[$repository] = [
                    'repository' => $repository,
                    'url' => $release['html_url'],
                    'version' => $release['name'],
                    'published_at' => $publishedAt->format('Y-m-d'),
                ];
            } catch (RuntimeException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
            }
        }

        if (empty($releasesData)) {
            $this->results['releases'] = '';

            return;
        }

        $releasesContent = '## Project releases' . PHP_EOL . PHP_EOL;
        foreach ($releasesData as $release) {
            $releasesContent .= sprintf(
                '* [%s](%s), [%s](%s) released on %s.' . PHP_EOL,
                RepositoryNameConverter::getName($release['repository']),
                'https://github.com/PrestaShop/' . $release['repository'],
                $release['version'],
                $release['url'],
                $release['published_at']
            );
            $releasesContent .= PHP_EOL;
        }

        $this->results['releases'] = $releasesContent;
    }

    private function extractInformationFromBody(string $body, string $information): ?string
    {
        preg_match_all('/(.*)\|(.*)/mi', $body, $matches);

        if (empty($matches)) {
            return 'other';
        }

        foreach ($matches[1] as $key => $match) {
            if (strpos($match, $information) !== false) {
                return trim($matches[2][$key]);
            }
        }

        return null;
    }
}
