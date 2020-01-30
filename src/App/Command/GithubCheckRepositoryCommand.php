<?php

namespace Console\App\Command;

use Console\App\Service\Github;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubCheckRepositoryCommand extends Command
{
    /**
     * @var Github;
     */
    protected $github;

    protected function configure()
    {
        $this->setName('github:check:repository')
            ->setDescription('Check Github Repositories')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN']
            )
            ->addOption(
                'public',
                null,
                InputOption::VALUE_NONE,
                'Only public repositories'
            )
            ->addOption(
                'private',
                null,
                InputOption::VALUE_NONE,
                'Only private repositories'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $onlyPublic = $input->getOption('public');
        $onlyPrivate = $input->getOption('private');

        $this->github = new Github($input->getOption('ghtoken'));

        $time = time();

        $githubRawData = $this->fetchGithubData($onlyPublic, $onlyPrivate);
        $dataset = $this->buildDataSet($githubRawData);

        $tableDrawer = new TableDrawer();
        $tableDrawer->drawResultsAsTable($dataset, $output, $time);
    }

    /**
     * @param bool $onlyPublic
     * @param bool $onlyPrivate
     *
     * @return array
     */
    private function fetchGithubData($onlyPublic, $onlyPrivate)
    {
        if (($onlyPublic && $onlyPrivate) || (!$onlyPublic && !$onlyPrivate)) {
            $type = 'all';
        } elseif ($onlyPrivate) {
            $type = 'private';
        } elseif ($onlyPublic) {
            $type = 'public';
        }

        $page = 1;
        $results = [];
        do {
            $repos = $this->github->getClient()->api('organization')->repositories('PrestaShop', $type, $page);

            $page++;
            $results = array_merge($results, $repos);
        } while (!empty($repos));
        uasort($results, function ($row1, $row2) {
            if (strtolower($row1['name']) == strtolower($row2['name'])) {
                return 0;
            }
            return strtolower($row1['name']) < strtolower($row2['name']) ? -1 : 1;
        });

        return $results;
    }

    /**
     * @param array $rawData
     *
     * @return RepositoryModel[]
     */
    private function buildDataSet(array $rawData)
    {
        $collection = [];

        foreach ($rawData as $item) {

            $model = new RepositoryModel();
            $model->name = $item['name'];
            $model->description = !empty($item['description']) ? $item['description'] : '';
            $model->html_url = $item['html_url'];
            $model->has_issues = $item['has_issues'];
            $model->license = $item['license']['spdx_id'];
            $model->stargazers_count = $item['stargazers_count'];

            $collection[] = $model;
        }

        return $collection;
    }
}
