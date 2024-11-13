<?php

namespace Console\App\Command;

use Console\App\Service\Github;
use Console\App\Service\PrestaShop\ModuleChecker;
use Console\App\Service\PrestaShop\ModuleFetcher;
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
     * @var Github
     */
    protected $github;
    /**
     * @var ModuleChecker
     */
    protected $moduleChecker;

    /**
     * @var array<string>
     */
    public const REPOSITORIES = [
        'blockreassurance',
        'contactform',
        'dashactivity',
        'dashgoals',
        'dashproducts',
        'dashtrends',
        'gamification',
        'graphnvd3',
        'gridhtml',
        'gsitemap',
        'pagesnotfound',
        'productcomments',
        'ps_banner',
        'ps_cashondelivery',
        'ps_categorytree',
        'ps_categoryproducts',
        'ps_checkpayment',
        'ps_contactinfo',
        'ps_crossselling',
        'ps_currencyselector',
        'ps_customeraccountlinks',
        'ps_customersignin',
        'ps_customtext',
        'ps_dataprivacy',
        'ps_emailalerts',
        'ps_emailsubscription',
        'ps_facetedsearch',
        'ps_faviconnotificationbo',
        'ps_featuredproducts',
        'ps_imageslider',
        'ps_languageselector',
        'ps_linklist',
        'ps_mainmenu',
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
    ];

    public const COL_ALL = 'all';
    public const COL_BRANCH = 'branch';
    public const COL_DESCRIPTION = 'description';
    public const COL_FILES = 'files';
    public const COL_ISSUES = 'issues';
    public const COL_LABELS = 'labels';
    public const COL_LICENSE = 'license';
    public const COL_TOPICS = 'topics';

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
                $_ENV['GH_TOKEN'] ?? null
            )
            ->addOption(
                'module',
                null,
                InputOption::VALUE_OPTIONAL
            )
            ->addOption(
                'branch',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                'master'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                '1'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->github = new Github($input->getOption('ghtoken'));
        $this->moduleChecker = new ModuleChecker($this->github);
        $moduleFetcher = new ModuleFetcher($this->github);

        $module = $input->getOption('module');
        $branch = $input->getOption('branch');
        $limit = $input->getOption('limit');
        $limit = explode(',', $limit);
        $time = time();

        $sectionProgressBar = $output->section();
        $sectionTable = $output->section();

        $arrayRepositories = $module ? [$module] : $moduleFetcher->getModules();
        $numRepositories = count($arrayRepositories);
        if ($numRepositories > 1) {
            $arrayRepositories = array_slice($arrayRepositories, (int) $limit[0], $limit[1] ? (int) $limit[1] : null);
            $numRepositories = count($arrayRepositories);
        }

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

        foreach ($arrayRepositories as $key => $repository) {
            $this->checkRepository('PrestaShop', $repository, $table, $branch);
            if ($numRepositories > 1) {
                $table->addRows([new TableSeparator()]);
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $sectionProgressBar->clear();

        $table->addRows([[
            'Total : ' . $numRepositories,
            '',
            '✓ ' . number_format((($this->stats[self::COL_ISSUES] / ModuleChecker::RATING_ISSUES_MAX) / $numRepositories) * 100, 2) . '%',
            '✓ ' . number_format((($this->stats[self::COL_DESCRIPTION] / ModuleChecker::RATING_DESCRIPTION_MAX) / $numRepositories) * 100, 2) . '%',
            '✓ ' . number_format((($this->stats[self::COL_LICENSE] / ModuleChecker::RATING_LICENSE_MAX) / $numRepositories) * 100, 2) . '%',
            '✓ ' . number_format((($this->stats[self::COL_LABELS] / ModuleChecker::RATING_LABELS_MAX) / $numRepositories) * 100, 2) . '%',
            '✓ ' . number_format((($this->stats[self::COL_BRANCH] / ModuleChecker::RATING_BRANCH_MAX) / $numRepositories) * 100, 2) . '%',
            '✓ ' . number_format((($this->stats[self::COL_FILES] / ModuleChecker::RATING_FILES_MAX) / $numRepositories) * 100, 2) . '%',
            '✓ ' . number_format((($this->stats[self::COL_TOPICS] / ModuleChecker::RATING_TOPICS_MAX) / $numRepositories) * 100, 2) . '%',
            '✓ ' . number_format((($this->stats[self::COL_ALL] / ModuleChecker::RATING_GLOBAL_MAX) / $numRepositories) * 100, 2) . '%',
        ]]);
        $table->render();
        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);

        return 0;
    }

    private function checkRepository(string $org, string $repository, Table $table, string $branch)
    {
        $this->moduleChecker->resetChecker();
        $this->moduleChecker->checkRepository($org, $repository, $branch);
        $report = $this->moduleChecker->getReport();
        if ($report['archived'] || $report['moved']) {
            echo 'Please remove `' . $org . '/' . $repository . '`for the Presthubot analysis' . PHP_EOL;

            return;
        }

        $nums = 'Stars : ' . $report['numStargazers'] . PHP_EOL
            . 'PR : ' . $report['numPROpened'] . PHP_EOL
            . 'Files : ' . $report['numFiles'];

        $checkLabels = '';
        foreach ($report['labels'] as $key => $value) {
            $checkLabels .= ($value['name'] ? '<info>✓ </info>' : '<error>✗ </error>') . ' '
                . ($value['color'] ? '<info>✓ </info>' : '<error>✗ </error>')
                . ' ' . str_replace('✔️', '✓', $key) . PHP_EOL;
        }

        $checkBranch = 'Branch : ';
        $checkBranch .= $report['branch']['develop'] ? '<info>✓ </info>' . ' (' . $report['branch']['develop'] . ')' : '<error>✗ </error>';
        $checkBranch .= PHP_EOL . 'Default (dev) : ' . (!$report['branch']['isDefault'] ? '<error>✗ </error>' : '<info>✓ </info>');
        $checkBranch .= $report['branch']['develop'] ? PHP_EOL . 'Status : ' . ($report['branch']['hasDiffMaster'] ? '<error>✗ </error>' : '<info>✓ </info>') : '';
        if (!empty($report['branch']['status']) && $report['branch']['status']['ahead'] > 0) {
            $checkBranch .= PHP_EOL . sprintf('- dev < master by %d commits', $report['branch']['status']['ahead']) . PHP_EOL;
            $checkBranch .= sprintf('THIS MODULE NEEDS A RELEASE');
        }
        if (!empty($report['branch']['status']) && $report['branch']['status']['behind'] > 0) {
            $checkBranch .= PHP_EOL . sprintf('- master > dev by %d commits', $report['branch']['status']['behind']) . PHP_EOL;
        }

        $checkFiles = '';
        foreach ($report['files'] as $path => $check) {
            $status = $check[ModuleChecker::CHECK_FILES_EXIST];
            if (isset($check[ModuleChecker::CHECK_COMPOSER_VALID])) {
                $status = $status && $check[ModuleChecker::CHECK_COMPOSER_VALID];
            }
            if (isset($check[ModuleChecker::CHECK_FILES_CONTAIN])) {
                foreach ($check as $key => $value) {
                    if ($check == ModuleChecker::CHECK_FILES_EXIST) {
                        continue;
                    }
                    $status = $status && $value;
                }
            }
            if (isset($check[ModuleChecker::CHECK_FILES_TEMPLATE])) {
                $status = $status && $check[ModuleChecker::CHECK_FILES_TEMPLATE];
            }
            $checkFiles .= ((bool) $status ? '<info>✓ </info>' : '<error>✗ </error>') . ' ' . $path . PHP_EOL;
        }
        $checkTopics = '';
        foreach ($report['githubTopics'] as $topicName => $hasTopic) {
            $checkTopics .= ($hasTopic ? '<info>✓ </info>' : '<error>✗ </error>') . ' ' . $topicName . PHP_EOL;
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
            '<href=' . $report['url'] . '>' . $repository . '</>',
            $nums,
            'Closed : ' . (!$report['hasIssuesOpened'] ? '<info>✓ </info>' : '<error>✗ </error>') . PHP_EOL . 'Number : ' . $report['numIssuesOpened'],
            $this->moduleChecker->getRating(ModuleChecker::RATING_DESCRIPTION) ? '<info>✓ </info>' : '<error>✗ </error>',
            $report['license'],
            $checkLabels,
            $checkBranch,
            $checkFiles,
            $checkTopics,
            number_format(
                ($this->moduleChecker->getRating(ModuleChecker::RATING_GLOBAL) / ModuleChecker::RATING_GLOBAL_MAX) * 100,
                2
            ) . '%',
        ]]);
    }
}
