<?php
namespace Console\App\Command;

use Console\App\Service\Github;
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
        'ps_healthcheck',
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

    protected $labels = [
        'waiting for QA' => 'fbca04',
        'QA ✔️' => 'b8ed50',
        'waiting for author' => 'fbca04',
        'waiting for PM' => 'fbca04',
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
        $module = $input->getOption('module');
        $time = time();

        $sectionProgressBar = $output->section();
        $sectionTable = $output->section();

        $arrayRepositories = $module ? [$module] : $this->repositories;
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
            '✓ ' . number_format((($this->stats[self::COL_ISSUES] / 1) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_DESCRIPTION] / 1) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_LICENSE] / 1) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_LABELS] / 8) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_BRANCH] / 2) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_FILES] / 13) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_TOPICS] / 2) / $numRepositories) * 100, 2). '%',
            '✓ ' . number_format((($this->stats[self::COL_ALL] / 28) / $numRepositories) * 100, 2). '%',
        ]]);
        $table->render();
        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);
    }

    private function checkRepository(string $org, string $repository, Table $table)
    {
        $repositoryInfo = $this->github->getClient()->api('repo')->show($org, $repository);

        $rating = $ratingBranch = $ratingDescription = $ratingIssues = $ratingLabels = $ratingLicense = $ratingTopics = 0;
        $ratingMax = 24;

        // Title
        //...

        // #
        $numOpenPR = $this->github->getClient()->api('search')->issues('repo:'.$org.'/'.$repository.' is:open is:pr');
        $nums = 'Stars : '. $repositoryInfo['stargazers_count'] . PHP_EOL
            . 'PR : ' . $numOpenPR['total_count']  . PHP_EOL
            . 'Files : ' . $this->github->countRepoFiles($org, $repository);

        // Issues
        $hasIssuesOpened = $repositoryInfo['has_issues'];
        $numIssues = $repositoryInfo['open_issues_count'];
        if (!$hasIssuesOpened) {
            $numIssues = $this->github->getClient()->api('search')->issues('repo:'.$org.'/PrestaShop is:open is:issue label:"'.$repository.'"');
            $numIssues = $numIssues['total_count'];
            $ratingIssues++;
        }

        // Description
        $ratingDescription += (!empty($repositoryInfo['description']) ? 1 : 0);

        // License
        $ratingLicense += (!empty($repositoryInfo['license']['spdx_id']) ? 1 : 0);

        // Labels
        $labelsInfo = $this->github->getClient()->api('issue')->labels()->all($org, $repository);
        $labels = [];
        foreach($labelsInfo as $info) {
            $labels[$info['name']] = $info['color'];
        }
        $checkLabels = '';
        foreach ($this->labels as $name => $color) {
            $checkLabels .= (in_array($name, array_keys($labels)) ? '<info>✓ </info>' : '<error>✗ </error>') . ' '
                . (in_array($name, array_keys($labels)) && $labels[$name] == $color ? '<info>✓ </info>' : '<error>✗ </error>') 
                . ' ' . str_replace('✔️', '✓', $name) . PHP_EOL;
            $ratingLabels += (in_array($name, array_keys($labels)) ? 1 : 0);
            $ratingLabels += (in_array($name, array_keys($labels)) && $labels[$name] == $color) ? 1 : 0;
        }
            
        // Branch
        $references = $this->github->getClient()->api('gitData')->references()->branches($org, $repository);
        $branches = [];
        foreach($references as $info) {
            $branches[str_replace('refs/heads/', '', $info['ref'])] = $info['object']['sha'];
        }
        $branchDevelop = (array_key_exists('dev', $branches) ? 'dev' : (array_key_exists('develop', $branches) ? 'develop' : ''));

        if ($branchDevelop !== '') {
            $releaseStatus = $this->findReleaseStatus($references, $repository);
        }

        $labelBranch = 'Branch : ';
        $labelBranch .= $branchDevelop ? '<info>✓ </info>' . ' (' . $branchDevelop . ')' : '<error>✗ </error>';
        $labelBranch .= $branchDevelop ? PHP_EOL . 'Status : ' . (isset($branches[$branchDevelop]) && $branches[$branchDevelop] == $branches['master'] ? '<info>✓ </info>' : '<error>✗ </error>') : '';

        if ($releaseStatus['ahead'] > 0) {
            $labelBranch .= PHP_EOL . sprintf('- master > dev by %d commits', $releaseStatus['ahead']) . PHP_EOL;
        }
        if ($releaseStatus['behind'] > 0) {
            $labelBranch .= sprintf('- dev < master by %d commits', $releaseStatus['behind']) . PHP_EOL;
            $labelBranch .= sprintf('THIS MODULE NEEDS A RELEASE');
        }

        $ratingBranch += ($branchDevelop ? 1 : 0);
        $ratingBranch += ((isset($branches[$branchDevelop]) && $branches[$branchDevelop] == $branches['master']) ? 1 : 0);

        // Files
        $hasReadme = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, 'README.md', 'refs/heads/master');
        $hasContributors = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, 'CONTRIBUTORS.md', 'refs/heads/master');
        $hasChangelog = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, 'CHANGELOG.txt', 'refs/heads/master');
        $hasComposerJson = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, 'composer.json', 'refs/heads/master');
        $hasComposerLock = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, 'composer.lock', 'refs/heads/master');
        $hasConfigXML = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, 'config.xml', 'refs/heads/master');
        $hasLogoPNG = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, 'logo.png', 'refs/heads/master');
        $hasGitIgnore = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, '.gitignore', 'refs/heads/master');
        $fileGitIgnore = $hasGitIgnore ? $this->github->getClient()->api('repo')->contents()->download($org, $repository, '.gitignore', 'refs/heads/master') : '';
        $checkGitIgnore = $hasGitIgnore ? strpos($fileGitIgnore, 'vendor') !== false : false;
        $hasTravis = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, '.travis.yml', 'refs/heads/master');
        $fileTravis = $hasTravis ? $this->github->getClient()->api('repo')->contents()->download($org, $repository, '.travis.yml', 'refs/heads/master') : '';
        $checkTravis = $hasTravis ? strpos($fileTravis, 'before_deploy:') !== false : false;
        $checkTravis = $checkTravis ? strpos($fileTravis, 'deploy:') !== false : false;
        $hasReleaseDrafter = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, '.github/release-drafter.yml', 'refs/heads/master');
        $hasPRTemplate = $this->github->getClient()->api('repo')->contents()->exists($org, $repository, '.github/PULL_REQUEST_TEMPLATE.md', 'refs/heads/master');
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
            ($hasTravis ? '<info>✓ </info>' : '<error>✗ </error>') . ' .travis.yml' .
            ($checkTravis ? '(<info>✓ </info>)' : '(<error>✗ </error>)');
        $ratingFiles = ($hasReadme ? 1 : 0) +
            ($hasContributors ? 1 : 0) +
            ($hasChangelog ? 1 : 0) +
            ($hasComposerJson ? 1 : 0) +
            ($hasComposerLock ? 1 : 0) +
            ($hasConfigXML ? 1 : 0) +
            ($hasLogoPNG ? 1 : 0) +
            ($hasReleaseDrafter ? 1 : 0) +
            ($hasPRTemplate ? 1 : 0) +
            ($hasGitIgnore ? 1 : 0) + 
            ($checkGitIgnore ? 1 : 0) +
            ($hasTravis ? 1 : 0) +
            ($checkTravis ? 1 : 0);

        // GH Topics
        $topics = $this->github->getRepoTopics($org, $repository);
        $checkTopics = (in_array('prestashop', $topics) ? '<info>✓ </info>' : '<error>✗ </error>') . ' prestashop' . PHP_EOL .
            (in_array('prestashop-module', $topics) ? '<info>✓ </info>' : '<error>✗ </error>') . ' prestashop-module';
        $ratingTopics = (in_array('prestashop', $topics) ? 1 : 0) + (in_array('prestashop-module', $topics) ? 1 : 0);

        // %
        $this->stats[self::COL_BRANCH] += $ratingBranch;
        $this->stats[self::COL_DESCRIPTION] += $ratingDescription;
        $this->stats[self::COL_FILES] += $ratingFiles;
        $this->stats[self::COL_ISSUES] += $ratingIssues;
        $this->stats[self::COL_LABELS] += $ratingLabels;
        $this->stats[self::COL_LICENSE] += $ratingLicense;
        $this->stats[self::COL_TOPICS] += $ratingTopics;
        $rating = $ratingIssues + $ratingDescription + $ratingLicense + $ratingLabels + $ratingBranch + $ratingFiles + $ratingTopics;
        $this->stats[self::COL_ALL] += $rating;

        $table->addRows([[
            '<href='.$repositoryInfo['html_url'].'>'.$repository.'</>',
            $nums,
            'Closed : ' . (!$hasIssuesOpened ? '<info>✓ </info>' : '<error>✗ </error>') . PHP_EOL . 'Number : ' . $numIssues,
            !empty($repositoryInfo['description']) ? '<info>✓ </info>' : '<error>✗ </error>',
            $repositoryInfo['license']['spdx_id'],
            $checkLabels,
            $labelBranch,
            $checkFiles,
            $checkTopics,
            number_format(($rating / $ratingMax) * 100, 2) . '%'
        ]]);
    }

    /**
     * @param array[] $references branch github data
     * @param string $repository repository name
     *
     * @return array
     */
    private function findReleaseStatus(array $references, string $repository)
    {
        $devBranchName = null;

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

        $masterLastCommitSha = $masterBranchData['object']['sha'];
        $devLastCommitSha = $devBranchData['object']['sha'];


        $comparison = $this->github->getClient()->api('repo')->commits()->compare(
            'prestashop',
            $repository,
            $masterLastCommitSha,
            $devLastCommitSha
        );

        $behindBy = $comparison['behind_by'];
        $aheadBy = $comparison['ahead_by'];

        return [
            'behind' => $behindBy,
            'ahead' => $aheadBy,
        ];
    }
}
