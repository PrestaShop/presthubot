<?php

namespace Console\App\Command;

use Console\App\Service\Github\Github;
use Console\App\Service\Github\GithubTypedEndpointProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubStatsCommand extends Command
{
    /**
     * @var Github;
     */
    protected $github;

    /**
     * @var GithubTypedEndpointProvider
     */
    private $githubTypedEndpointProvider;

    public function __construct(string $name = null)
    {
        $this->githubTypedEndpointProvider = new GithubTypedEndpointProvider();
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('github:stats')
            ->setDescription('Stats Github Personal')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN'] ?? null
            )
            ->addOption(
                'username',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_USERNAME'] ?? null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->github = new Github($input->getOption('ghtoken'));

        // Get Stats
        $time = time();
        $this->getStats($input, $output);
        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);

        return 0;
    }

    private function getStats(InputInterface $input, OutputInterface $output)
    {
        $rows[] = [new TableCell('<info> Stats PrestaShop/PrestaShop</info>', ['colspan' => 2])];
        $rows[] = new TableSeparator();
        $repository = $this->githubTypedEndpointProvider->getRepoEndpoint($this->github->getClient())->show('PrestaShop', 'PrestaShop');

        $rows[] = ['# Stars', $repository['stargazers_count']];
        $rows[] = ['# Issues', $repository['open_issues_count']];

        $openPullRequests = $this->githubTypedEndpointProvider->getSearchEndpoint($this->github->getClient())->issues('repo:PrestaShop/PrestaShop is:pr is:open');
        $rows[] = ['# PR', $openPullRequests['total_count']];

        if ($input->getOption('username')) {
            $rows[] = new TableSeparator();
            $rows[] = [new TableCell('<info> Stats @' . $input->getOption('username') . '</info>', ['colspan' => 2])];
            $rows[] = new TableSeparator();

            $ranking = $this->getRankingContributors($input->getOption('username'));
            if (!empty($ranking)) {
                $rows[] = ['# Ranking Contributors', $ranking];
                $rows[] = new TableSeparator();
            }

            $openPullRequests = $this->githubTypedEndpointProvider->getSearchEndpoint($this->github->getClient())->issues('repo:PrestaShop/PrestaShop is:pr is:open author:' . $input->getOption('username'));
            $rows[] = ['# PR Open', $openPullRequests['total_count']];
            $mergedPullRequests = $this->githubTypedEndpointProvider->getSearchEndpoint($this->github->getClient())->issues('repo:PrestaShop/PrestaShop is:pr is:merged author:' . $input->getOption('username'));
            $rows[] = ['# PR Merged', $mergedPullRequests['total_count']];
            $closedPullRequests = $this->githubTypedEndpointProvider->getSearchEndpoint($this->github->getClient())->issues('repo:PrestaShop/PrestaShop is:pr is:closed is:unmerged author:' . $input->getOption('username'));
            $rows[] = ['# PR Closed', $closedPullRequests['total_count']];
            $rows[] = new TableSeparator();

            $openIssues = $this->githubTypedEndpointProvider->getSearchEndpoint($this->github->getClient())->issues('repo:PrestaShop/PrestaShop is:issue is:open author:' . $input->getOption('username'));
            $rows[] = ['# Issue Author Open', $openIssues['total_count']];
            $closedIssues = $this->githubTypedEndpointProvider->getSearchEndpoint($this->github->getClient())->issues('repo:PrestaShop/PrestaShop is:issue is:closed author:' . $input->getOption('username'));
            $rows[] = ['# Issue Author Closed', $closedIssues['total_count']];
            $rows[] = new TableSeparator();

            $openIssues = $this->githubTypedEndpointProvider->getSearchEndpoint($this->github->getClient())->issues('repo:PrestaShop/PrestaShop is:issue is:open assignee:' . $input->getOption('username'));
            $rows[] = ['# Issue Assignee Open', $openIssues['total_count']];
            $closedIssues = $this->githubTypedEndpointProvider->getSearchEndpoint($this->github->getClient())->issues('repo:PrestaShop/PrestaShop is:issue is:closed assignee:' . $input->getOption('username'));
            $rows[] = ['# Issue Assignee Closed', $closedIssues['total_count']];
        }

        $table = new Table($output);
        $table->setRows($rows);
        $table->setStyle('box-double');
        $table->render();
    }

    protected function getRankingContributors($username): ?int
    {
        $contributors = \file_get_contents('http://contributors.prestashop.com/static/contributors.js');
        if (empty($contributors)) {
            return null;
        }
        $contributors = \json_decode($contributors, true);
        if (empty($contributors)) {
            return null;
        }
        foreach ($contributors as $ranking => $data) {
            if ($data['login'] == $username) {
                return $ranking + 1;
            }
        }

        return null;
    }
}
