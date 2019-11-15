<?php
namespace Console\App\Command;

use DateInterval;
use DateTime;
use Github\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
 
class GithubCheckRepositoryCommand extends Command
{
    /**
     * @var Client;
     */
    protected $client;
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
            );   
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->client = new Client();
        $ghToken = $input->getOption('ghtoken');
        if (!empty($ghToken)) {
            $this->client->authenticate($ghToken, null, Client::AUTH_URL_TOKEN);
        }
        $page = 1;
        $results = [];
        do {
            $repos = $this->client->api('organization')->repositories('PrestaShop', 'public', $page);
            $page++;
            $results = array_merge($results, $repos);
        } while (!empty($repos));
        uasort($results, function($row1, $row2) {
            if (strtolower($row1['name']) == strtolower($row2['name'])) {
                return 0;
            }
            return strtolower($row1['name']) < strtolower($row2['name']) ? -1 : 1;
        });

        $countStars = $countWDescription = 0;
        $countWLicense = [];

        $table = new Table($output);
        $table
            ->setStyle('box')
            ->setHeaders([
                'Title',
                '# Stars',
                'Description',
                'License',
            ]);
        foreach($results as $key => $result) {
            $table->addRows([[
                '<href='.$result['html_url'].'>'.$result['name'].'</>',
                $result['stargazers_count'],
                !empty($result['description']) ? '<info>✓ </info>' : '<error>✗ </error>',
                $result['license']['spdx_id'],
            ]]);

            $countStars += $result['stargazers_count'];
            $countWDescription += (!empty($result['description']) ? 1 : 0);
            if (!empty($result['license']['spdx_id'])) {
                if (!array_key_exists($result['license']['spdx_id'], $countWLicense)) {
                    $countWLicense[$result['license']['spdx_id']] = 0;
                }
                $countWLicense[$result['license']['spdx_id']]++;
            }
            $table->addRows([new TableSeparator()]);
        }

        $licenseCell = '';
        ksort($countWLicense);
        foreach($countWLicense as $license => $count) {
            $licenseCell .= $license . ' : ' . $count;
            if ($license !== array_key_last($countWLicense)) {
                $licenseCell .= PHP_EOL;
            }
        }

        $table->addRows([[
            'Total : ' . count($results),
            'Avg : ' . number_format($countStars / count($results), 2),
            'Num : ' . $countWDescription,
            $licenseCell,
        ]]);
        $table->render();
    }
}