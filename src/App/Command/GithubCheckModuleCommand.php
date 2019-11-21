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
 
class GithubCheckModuleCommand extends Command
{
    /**
     * @var Client;
     */
    protected $client;

    /**
     * @var array<string>
     */
    protected $repositories = [
        'bankwire',
        'blockreassurance',
        'contactform',
        'cronjobs',
        'dashactivity',
        'dashgoals',
        'dashproducts',
        'dashtrends',
        'dateofdelivery',
        'gadwords',
        'gamification',
        'graphnvd3',
        'gridhtml',
        'gsitemap',
        'pagesnotfound',
        'prestafraud',
        'productcomments',
        'ps_banner',
        'ps_categorytree',
        'ps_checkpayment',
        'ps_contactinfo',
        'ps_crossselling',
        'ps_currencyselector',
        'ps_customeraccountlinks',
        'ps_customersignin',
        'ps_customtext',
        'ps_dataprivacy',
        'ps_emailalerts',
        'ps_emailsmanager',
        'ps_emailsubscription',
        'ps_facetedsearch',
        'ps_faviconnotificationbo',
        'ps_featuredproducts',
        'ps_googleanalytics',
        'ps_imageslider',
        'ps_languageselector',
        'ps_linklist',
        'ps_mainmenu',
        'ps_mbo',
        'ps_newproducts',
        'ps_searchbar',
        'ps_sharebuttons',
        'ps_shoppingcart',
        'ps_socialfollow',
        'ps_themecusto',
        'ps_wirepayment',
        'pscleaner',
        'psgdpr',
        'sekeywords',
        'statsbestcategories',
        'statsbestcustomers',
        'statsbestmanufacturers',
        'statsbestproducts',
        'statsbestsuppliers',
        'statsbestvouchers',
        'statscarrier',
        'statscatalog',
        'statscheckup',
        'statsdata',
        'statsequipment',
        'statsforecast',
        'statslive',
        'statsnewsletter',
        'statsorigin',
        'statspersonalinfos',
        'statsproduct',
        'statsregistrations',
        'statssales',
        'statssearch',
        'statsstock',
        'statsvisits',
        'welcome',
        'watermark',
    ];    

    protected function configure()
    {
        $this->setName('github:check:module')
            ->setDescription('Check Github Module')
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
        $time = time();

        $table = new Table($output);
        $table
            ->setStyle('box')
            ->setHeaders([
                'Title',
                '# Stars',
                '# PR',
                'PS Issues',
                '# Issues',
                'Description',
                'License',
                'Labels',
                'Branch dev',
                'Files',
                'GH Topics',
            ]);
        foreach($this->repositories as $key => $repository) {
            $this->checkRepository('PrestaShop', $repository, $table);

            if ($key !== array_key_last($this->repositories)) {
                $table->addRows([new TableSeparator()]);
            }
        }
        $table->render();
        $output->writeLn(['', 'Ouput generated in ' . (time() - $time) . 's.']);
    }

    private function checkRepository(string $org, string $repository, Table $table)
    {
        $repositoryInfo = $this->client->api('repo')->show($org, $repository);

        // Num PR 
        $numOpenPR = $this->client->api('search')->issues('repo:'.$org.'/'.$repository.' is:open is:pr');

        // Check Labels “waiting for QA”, “QA approved”, “waiting for author”, “waiting for PM”
        $labelsInfo = $this->client->api('issue')->labels()->all($org, $repository);
        $labels = [];
        foreach($labelsInfo as $info) {
            $labels[] = $info['name'];
        }
        $checkLabels = (in_array('waiting for QA', $labels) ? '<info>✓ </info>' : '<error>✗ </error>') . ' waiting for QA' . PHP_EOL .
            (in_array('QA ✔️', $labels) ? '<info>✓ </info>' : '<error>✗ </error>') . ' QA ✓' . PHP_EOL .
            (in_array('waiting for author', $labels) ? '<info>✓ </info>' : '<error>✗ </error>') . ' waiting for author' . PHP_EOL .
            (in_array('waiting for PM', $labels) ? '<info>✓ </info>' : '<error>✗ </error>') . ' waiting for PM';

        // Check branch dev ou develop
        $references = $this->client->api('gitData')->references()->branches($org, $repository);
        $branches = [];
        foreach($references as $info) {
            $branches[str_replace('refs/heads/', '', $info['ref'])] = $info['object']['sha'];
        }
        $branchDevelop = (array_key_exists('dev', $branches) ? 'dev' : (in_ararray_key_existsray('develop', $branches) ? 'develop' : ''));

        // Check Files 
        $hasReadme = $this->client->api('repo')->contents()->exists($org, $repository, 'README.md', 'refs/heads/master');
        $hasContributors = $this->client->api('repo')->contents()->exists($org, $repository, 'CONTRIBUTORS.md', 'refs/heads/master');
        $hasChangelog = $this->client->api('repo')->contents()->exists($org, $repository, 'CHANGELOG.txt', 'refs/heads/master');
        $hasComposerJson = $this->client->api('repo')->contents()->exists($org, $repository, 'composer.json', 'refs/heads/master');
        $hasComposerLock = $this->client->api('repo')->contents()->exists($org, $repository, 'composer.lock', 'refs/heads/master');
        $hasConfigXML = $this->client->api('repo')->contents()->exists($org, $repository, 'config.xml', 'refs/heads/master');
        $hasLogoPNG = $this->client->api('repo')->contents()->exists($org, $repository, 'logo.png', 'refs/heads/master');
        $hasGitIgnore = $this->client->api('repo')->contents()->exists($org, $repository, '.gitignore', 'refs/heads/master');
        $fileGitIgnore = $hasGitIgnore ? $this->client->api('repo')->contents()->download($org, $repository, '.gitignore', 'refs/heads/master') : '';
        $checkGitIgnore = $hasGitIgnore ? strpos($fileGitIgnore, 'vendor') !== false : false;
        $hasTravis = $this->client->api('repo')->contents()->exists($org, $repository, '.travis.yml', 'refs/heads/master');
        $hasReleaseDrafter = $this->client->api('repo')->contents()->exists($org, $repository, '.github/release-drafter.yml', 'refs/heads/master');
        $hasPRTemplate = $this->client->api('repo')->contents()->exists($org, $repository, '.github/PULL_REQUEST_TEMPLATE.md', 'refs/heads/master');
        $checkFiles = ($hasReadme ? '<info>✓ </info>' : '<error>✗ </error>') . ' README.md' . PHP_EOL .
            ($hasContributors ? '<info>✓ </info>' : '<error>✗ </error>') . ' CONTRIBUTORS.md' . PHP_EOL .
            ($hasChangelog ? '<info>✓ </info>' : '<error>✗ </error>') . ' CHANGELOG.txt' . PHP_EOL .
            ($hasComposerJson ? '<info>✓ </info>' : '<error>✗ </error>') . ' composer.json' . PHP_EOL .
            ($hasComposerLock ? '<info>✓ </info>' : '<error>✗ </error>') . ' composer.lock' . PHP_EOL .
            ($hasConfigXML ? '<info>✓ </info>' : '<error>✗ </error>') . ' config.xml' . PHP_EOL .
            ($hasLogoPNG ? '<info>✓ </info>' : '<error>✗ </error>') . ' logo.png' . PHP_EOL .
            ($hasReleaseDrafter ? '<info>✓ </info>' : '<error>✗ </error>') . ' .github/release-drafter.yml' . PHP_EOL .
            ($hasPRTemplate ? '<info>✓ </info>' : '<error>✗ </error>') . ' .github/PULL_REQUEST_TEMPLATE.md' . PHP_EOL .
            ($hasGitIgnore ? '<info>✓ </info>' : '<error>✗ </error>') . ' .gitignore' . 
            ($checkGitIgnore ? '(<info>✓ </info>)' : '(<error>✗ </error>)') . PHP_EOL .
            ($hasTravis ? '<info>✓ </info>' : '<error>✗ </error>') . ' .travis.yml';

        // Check Issues
        $hasIssuesOpened = $repositoryInfo['has_issues'];
        $numIssues = $repositoryInfo['open_issues_count'];
        if (!$hasIssuesOpened) {
            $numIssues = $this->client->api('search')->issues('repo:'.$org.'/PrestaShop is:open is:issue label:"'.$repository.'"');
            $numIssues = $numIssues['total_count'];
        }

        // Check topics
        $query = '{
            repository(owner: "PrestaShop", name: "'.$repository.'") {
              repositoryTopics(first: 10) {
                edges {
                  node {
                    topic {
                      name
                    }
                  }
                }
              }
            }
          }';

        $repositoryInfoGraphQL = $this->client->api('graphql')->execute($query, []);
        $topics = [];
        foreach($repositoryInfoGraphQL['data']['repository']['repositoryTopics']['edges'] as $edge) {
            $topics[] = $edge['node']['topic']['name'];
        }
        $checkTopics = (in_array('prestashop', $topics) ? '<info>✓ </info>' : '<error>✗ </error>') . ' prestashop' . PHP_EOL .
            (in_array('prestashop-module', $topics) ? '<info>✓ </info>' : '<error>✗ </error>') . ' prestashop-module';

        $labelBranch = 'Branch : ';
        $labelBranch .= $branchDevelop ? '<info>✓ </info>' . ' ('.$branchDevelop.')' : '<error>✗ </error>';
        $labelBranch .= $branchDevelop ? PHP_EOL . 'Status : ' . ($branches[$branchDevelop] == $branches['master'] ? '<info>✓ </info>': '<error>✗ </error>') : ''; 
        $table->addRows([[
            '<href='.$repositoryInfo['html_url'].'>'.$repository.'</>',
            $repositoryInfo['stargazers_count'],
            $numOpenPR['total_count'],
            !$hasIssuesOpened ? '<info>✓ </info>' : '<error>✗ </error>',
            $numIssues,
            !empty($repositoryInfo['description']) ? '<info>✓ </info>' : '<error>✗ </error>',
            $repositoryInfo['license']['spdx_id'],
            $checkLabels,
            $labelBranch,
            $checkFiles,
            $checkTopics
        ]]);
    }
}