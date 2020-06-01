<?php
namespace Console\App\Command;

use Console\App\Service\Github;
use Console\App\Service\PrestaShop\ModuleChecker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
 
class GithubCheckModuleCommand extends Command
{
    /**
     * @var Github;
     */
    protected $github;
    /**
     * @var ModuleChecker;
     */
    protected $moduleChecker;

    /**
     * @var array<string>
     */
    public const REPOSITORIES = [
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
        'ps_healthcheck',
        'ps_imageslider',
        'ps_languageselector',
        'ps_linklist',
        'ps_livetranslation',
        'ps_mainmenu',
        'ps_newproducts',
        'ps_searchbar',
        'ps_sharebuttons',
        'ps_shoppingcart',
        'ps_socialfollow',
        'ps_themecusto',
        'ps_wirepayment',
        'pscleaner',
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
    
    const COL_ALL = 'all';
    const COL_BRANCH = 'branch';
    const COL_DESCRIPTION = 'description';
    const COL_FILES = 'files';
    const COL_ISSUES = 'issues';
    const COL_LABELS = 'labels';
    const COL_LICENSE = 'license';
    const COL_TOPICS = 'topics';

    protected $stats = [
        self::COL_ALL => 0,
        self::COL_BRANCH => 0,
        self::COL_DESCRIPTION => 0,
        self::COL_FILES => 0,
        self::COL_ISSUES => 0,
        self::COL_LABELS => 0,
        self::COL_LICENSE => 0,
        self::COL_TOPICS => 0,
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
            )
            ->addOption(
                'module',
                null,
                InputOption::VALUE_OPTIONAL
            );   
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->github = new Github($input->getOption('ghtoken'));
        $this->moduleChecker = new ModuleChecker($this->github);
        $module = $input->getOption('module');
        $time = time();

        $sectionProgressBar = $output->section();
        $sectionTable = $output->section();

        $arrayRepositories = $module ? [$module] : self::REPOSITORIES;
        $numRepositories = count($arrayRepositories);

        // Table
        $table = new Table($sectionTable);
        $table
            ->setStyle('box')
            ->setHeaders([
                'Title',
                '#',
                'Issues',
                'Description',
                'License',
                'Labels',
                'Branch dev',
                'Files',
                'GH Topics',
                '%',
            ]);
        // Progress Bar
        $progressBar = new ProgressBar($sectionProgressBar, $numRepositories);
        $progressBar->start();

        foreach($arrayRepositories as $key => $repository) {
            $this->checkRepository('PrestaShop', $repository, $table);
            if (count($arrayRepositories) > 1) {
                $table->addRows([new TableSeparator()]);
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $sectionProgressBar->clear();

        $table->addRows([[
            'Total : ' . $numRepositories,
            '',
            '✓ ' . number_format((($this->stats[self::COL_ISSUES] / ModuleChecker::RATING_ISSUES_MAX) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_DESCRIPTION] / ModuleChecker::RATING_DESCRIPTION_MAX) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_LICENSE] / ModuleChecker::RATING_LICENSE_MAX) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_LABELS] / ModuleChecker::RATING_LABELS_MAX) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_BRANCH] / ModuleChecker::RATING_BRANCH_MAX) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_FILES] / ModuleChecker::RATING_FILES_MAX) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_TOPICS] / ModuleChecker::RATING_TOPICS_MAX) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_ALL] / ModuleChecker::RATING_GLOBAL_MAX) / $numRepositories) * 100, 2). '%',
        ]]);
        $table->render();
        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);
    }

    private function checkRepository(string $org, string $repository, Table $table)
    {
        $this->moduleChecker->resetChecker();
        $this->moduleChecker->checkRepository($org, $repository);
        $report = $this->moduleChecker->getReport();

        $nums = 'Stars : '. $report['numStargazers'] . PHP_EOL
            . 'PR : ' . $report['numPROpened']  . PHP_EOL
            . 'Files : ' . $report['numFiles'];

        $checkLabels = '';
        foreach ($report['labels'] as $key => $value) {
            $checkLabels .= ($value['name'] ? '<info>✓ </info>' : '<error>✗ </error>') . ' '
                . ($value['color'] ? '<info>✓ </info>' : '<error>✗ </error>') 
                . ' ' . str_replace('✔️', '✓', $key) . PHP_EOL;
        }

        $checkBranch = 'Branch : ';
        $checkBranch .= $report['branch']['develop'] ? '<info>✓ </info>' . ' (' . $report['branch']['develop'] . ')' : '<error>✗ </error>';
        $checkBranch .= $report['branch']['develop'] ? PHP_EOL . 'Status : ' . ($report['branch']['hasDiffMaster'] ? '<info>✓ </info>' : '<error>✗ </error>') : ''; 
        if (!empty($report['branch']['status']) && $report['branch']['status']['ahead'] > 0) {
            $checkBranch .= PHP_EOL . sprintf('- master > dev by %d commits', $report['branch']['status']['ahead']) . PHP_EOL;
        }
        if (!empty($report['branch']['status']) && $report['branch']['status']['behind'] > 0) {
            $checkBranch .= sprintf('- dev < master by %d commits', $report['branch']['status']['behind']) . PHP_EOL;
            $checkBranch .= sprintf('THIS MODULE NEEDS A RELEASE');
        }

        $checkFiles = '';
        foreach ($report['files'] as $path => $check) {
            $checkFiles .= ($check[ModuleChecker::CHECK_FILES_EXIST] ? '<info>✓ </info>' : '<error>✗ </error>') . ' ' . $path;
            if (isset($check[ModuleChecker::CHECK_FILES_CONTAIN])) {
                $remainingCheck = true;
                foreach ($check as $key => $value) {
                    if ($check == ModuleChecker::CHECK_FILES_EXIST) {
                        continue;
                    }
                    $remainingCheck = $remainingCheck && $value;
                }
                $checkFiles .= '('.($remainingCheck ? '<info>✓ </info>' : '<error>✗ </error>') . ')';
            }
            $checkFiles .= PHP_EOL;
        }
        $checkTopics = '';
        foreach ($report['githubTopics'] as $topicName => $hasTopic) {
            $checkTopics .= ($hasTopic  ? '<info>✓ </info>' : '<error>✗ </error>') . ' ' . $topicName . PHP_EOL;
        }

        // %
        $this->stats[self::COL_BRANCH] += $this->moduleChecker->getRating(ModuleChecker::RATING_BRANCH);
        $this->stats[self::COL_DESCRIPTION] += $this->moduleChecker->getRating(ModuleChecker::RATING_DESCRIPTION);
        $this->stats[self::COL_FILES] += $this->moduleChecker->getRating(ModuleChecker::RATING_FILES);
        $this->stats[self::COL_ISSUES] += $this->moduleChecker->getRating(ModuleChecker::RATING_ISSUES);
        $this->stats[self::COL_LABELS] += $this->moduleChecker->getRating(ModuleChecker::RATING_LABELS);
        $this->stats[self::COL_LICENSE] += $this->moduleChecker->getRating(ModuleChecker::RATING_LICENSE);
        $this->stats[self::COL_TOPICS] += $this->moduleChecker->getRating(ModuleChecker::RATING_TOPICS);
        $this->stats[self::COL_ALL] += $this->moduleChecker->getRating(ModuleChecker::RATING_GLOBAL);

        $table->addRows([[
            '<href='.$report['url'].'>'.$repository.'</>',
            $nums,
            'Closed : ' . (!$report['hasIssuesOpened'] ? '<info>✓ </info>' : '<error>✗ </error>') . PHP_EOL . 'Number : ' . $report['numIssuesOpened'],
            $this->moduleChecker->getRating(ModuleChecker::RATING_DESCRIPTION) ? '<info>✓ </info>' : '<error>✗ </error>',
            $report['license'],
            $checkLabels,
            $checkBranch,
            $checkFiles,
            $checkTopics,
            number_format(
                ($this->moduleChecker->getRating(ModuleChecker::RATING_GLOBAL) / $this->moduleChecker->getRating(ModuleChecker::RATING_GLOBAL_MAX)) * 100,
                2
            ) . '%'
        ]]);
    }
}
