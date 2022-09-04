<?php

namespace Console\App\Command;

use Console\App\Service\Github\Github;
use Console\App\Service\Github\GithubTypedEndpointProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubCheckRepositoryCommand extends Command
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
        $this->setName('github:check:repository')
            ->setDescription('Check Github Repositories')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN'] ?? null
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $onlyPublic = $input->getOption('public');
        $onlyPrivate = $input->getOption('private');

        $this->github = new Github($input->getOption('ghtoken'));
        $time = time();

        $type = '';
        if (($onlyPublic && $onlyPrivate) || (!$onlyPublic && !$onlyPrivate)) {
            $type = 'all';
        } elseif ($onlyPrivate) {
            $type = 'private';
        } elseif ($onlyPublic) {
            $type = 'public';
        }
        if (empty($type)) {
            return 1;
        }

        $page = 1;
        $results = [];
        do {
            $repos = $this->githubTypedEndpointProvider->getOrganizationEndpoint($this->github->getClient())->repositories('PrestaShop', $type, $page);
            ++$page;
            $results = array_merge($results, $repos);
        } while (!empty($repos));
        uasort($results, function ($row1, $row2) {
            if (strtolower($row1['name']) == strtolower($row2['name'])) {
                return 0;
            }

            return strtolower($row1['name']) < strtolower($row2['name']) ? -1 : 1;
        });

        $countStars = $countWDescription = $countIssuesOpened = 0;
        $countWLicense = [];

        $table = new Table($output);
        $table
            ->setStyle('box')
            ->setHeaders([
                'Title',
                '# Stars',
                'Description',
                'Issues Opened',
                'License',
            ]);
        foreach ($results as $key => $result) {
            $table->addRows([[
                '<href=' . $result['html_url'] . '>' . $result['name'] . '</>',
                $result['stargazers_count'],
                !empty($result['description']) ? '<info>✓ </info>' : '<error>✗ </error>',
                $result['has_issues'] ? '<info>✓ </info>' : '<error>✗ </error>',
                $result['license']['spdx_id'],
            ]]);

            $countStars += $result['stargazers_count'];
            $countIssuesOpened += ($result['has_issues'] ? 1 : 0);
            $countWDescription += (!empty($result['description']) ? 1 : 0);
            if (!empty($result['license']['spdx_id'])) {
                if (!array_key_exists($result['license']['spdx_id'], $countWLicense)) {
                    $countWLicense[$result['license']['spdx_id']] = 0;
                }
                ++$countWLicense[$result['license']['spdx_id']];
            }
            $table->addRows([new TableSeparator()]);
        }

        $licenseCell = '';
        ksort($countWLicense);
        foreach ($countWLicense as $license => $count) {
            $licenseCell .= $license . ' : ' . $count;
            if ($license !== array_key_last($countWLicense)) {
                $licenseCell .= PHP_EOL;
            }
        }

        $table->addRows([[
            'Total : ' . count($results),
            'Avg : ' . number_format($countStars / count($results), 2),
            'Opened : ' . $countIssuesOpened . PHP_EOL . 'Closed : ' . (count($results) - $countIssuesOpened),
            'Num : ' . $countWDescription,
            $licenseCell,
        ]]);
        $table->render();
        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);

        return 0;
    }
}
