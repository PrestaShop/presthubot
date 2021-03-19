<?php

namespace Console\App\Command;

use Console\App\Service\Github;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubContributorsStatsCommand extends Command
{
    /**
     * @var Github;
     */
    protected $github;

    protected function configure()
    {
        $this->setName('github:contributors:stats')
            ->setDescription('Stats Contributors PR')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN'] ?? null
            )
            ->addOption(
                'contributorsFile',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                'contributors.csv' ?? null
            )
            ->addOption(
                'outputFile',
                null,
                InputOption::VALUE_REQUIRED,
                ''
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->github = new Github($input->getOption('ghtoken'));

        // Get Stats
        $time = time();
        $this->process($input, $output);
        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);

        return 0;
    }

    private function process(InputInterface $input, OutputInterface $output): void
    {
        $contributorsFile = $input->getOption('contributorsFile');
        if (!is_file($contributorsFile)) {
            $output->writeLn(['<error>File "' . $contributorsFile . '" not found</error>']);

            return;
        }
        $outputFile = $input->getOption('outputFile');
        if (is_file($outputFile)) {
            $output->writeLn(['<error>Remove file "' . $outputFile . '"</error>']);

            return;
        }

        // Fetch List of contributors
        $hFile = fopen($contributorsFile, 'r');
        $contributors = [];
        if ($hFile !== false) {
            while (($data = fgetcsv($hFile, 1000, ',')) !== false) {
                // Column 0 : Agency Name
                // Column 1 : Contributor Github Nickname
                $contributors[$data[1]] = $data[0];
            }
            fclose($hFile);
        }

        if (empty($contributors)) {
            return;
        }

        $data = [];
        foreach ($contributors as $author => $agency) {
            if (!isset($data[$agency])) {
                $data[$agency] = [
                    'created_day' => [],
                    'created_branch' => [],
                    'merged_day' => [],
                    'merged_branch' => [],
                ];
            }
            $pullRequests = $this->github->getClient()->api('search')->issues('org:PrestaShop is:pr author:' . $author);
            $output->writeLn(['[' . $agency . '] ' . $author . ' (' . count($pullRequests['items']) . ')']);

            foreach ($pullRequests['items'] as $pullRequest) {
                $repository = str_replace('https://api.github.com/repos/PrestaShop/', '', $pullRequest['repository_url']);
                $createdKey = substr($pullRequest['created_at'], 0, 10);
                $branch = array_reduce($pullRequest['labels'], function (bool $carry, array $item) {
                    if (!empty($carry)) {
                        return $carry;
                    }
                    if ($item['name'] === 'develop') {
                        return $item['name'];
                    }

                    return '';
                }, '');

                if (!isset($data[$agency]['created_day'][$createdKey])) {
                    $data[$agency]['created_day'][$createdKey] = 0;
                }
                if (!isset($data[$agency]['created_branch'][$branch])) {
                    $data[$agency]['created_branch'][$branch] = 0;
                }

                // The number of PRs created by the company by day
                ++$data[$agency]['created_day'][$createdKey];
                // The number of PRs created by the company by branch
                ++$data[$agency]['created_branch'][$branch];

                $apiPullRequest = $this->github->getClient()->api('pr')->show('PrestaShop', $repository, $pullRequest['number']);
                if (!$apiPullRequest['merged']) {
                    continue;
                }
                $mergedKey = substr($apiPullRequest['merged_at'], 0, 10);
                if (!isset($data[$agency]['merged_day'][$mergedKey])) {
                    $data[$agency]['merged_day'][$mergedKey] = 0;
                }
                if (!isset($data[$agency]['merged_branch'][$branch])) {
                    $data[$agency]['merged_branch'][$branch] = 0;
                }

                // The number of PRs merged by the company by day
                ++$data[$agency]['merged_day'][$mergedKey];
                // The number of PRs merged by the company by branch
                ++$data[$agency]['merged_branch'][$branch];
            }
        }

        // Export CSV
        $hFile = fopen($outputFile, 'w');
        foreach ($data as $agency => $data1) {
            foreach ($data1 as $type => $data2) {
                foreach ($data2 as $category => $num) {
                    $fields = [
                        $agency,
                        $type,
                        $category,
                        $num,
                    ];
                    fputcsv($hFile, $fields);
                }
            }
        }
        fclose($hFile);

        return;
    }
}
