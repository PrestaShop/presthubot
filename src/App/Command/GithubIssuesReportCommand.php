<?php

namespace Console\App\Command;

use Console\App\Service\Github\Github;
use Console\App\Service\Github\GithubTypedEndpointProvider;
use Console\App\Service\Github\Query;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubIssuesReportCommand extends Command
{
    /**
     * @var Github
     */
    protected $github;
    /**
     * @var int
     */
    protected $dateStart;
    /**
     * @var string
     */
    protected $dateStartFormatted;
    /**
     * @var int
     */
    protected $dateEnd;
    /**
     * @var string
     */
    protected $dateEndFormatted;
    /**
     * @var array{'open': int, 'closed': int, 'regressions_TE': int, 'regressions': array<int, array>, 'duplicates': array<int, array>}
     */
    protected $results = [
        'open' => 0,
        'closed' => 0,
        'regressions_TE' => 0,
        'regressions' => [],
        'duplicates' => [],
    ];

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
        $this->setName('github:issues:report')
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
                'var/report'
            )
            ->addOption(
                'dateStart',
                null,
                InputOption::VALUE_REQUIRED,
                ''
            )
            ->addOption(
                'dateEnd',
                null,
                InputOption::VALUE_OPTIONAL,
                ''
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
        if ($input->getOption('dateStart') === null) {
            $output->writeln('<error>Error: Empty parameter dateStart</error>');

            return false;
        }
        $this->dateStart = strtotime($input->getOption('dateStart'));
        if (date('Y-m-d', $this->dateStart) != $input->getOption('dateStart')) {
            $output->writeln('<error>Error: Unrecognizable dateStart format : ' . $input->getOption('dateStart') . '</error>');

            return false;
        }
        if ($input->getOption('dateEnd') !== null) {
            $this->dateEnd = strtotime($input->getOption('dateEnd'));
            if (date('Y-m-d', $this->dateEnd) != $input->getOption('dateEnd')) {
                $output->writeln('<error>Error: Unrecognizable dateEnd format : ' . $input->getOption('dateEnd') . '</error>');

                return false;
            }
        }

        return true;
    }

    private function generateReport(InputInterface $input, OutputInterface $output): void
    {
        if (empty($this->dateEnd)) {
            $this->dateEnd = strtotime('+28 day', $this->dateStart);
        }
        $this->dateStartFormatted = date('Y-m-d', $this->dateStart);
        $this->dateEndFormatted = date('Y-m-d', $this->dateEnd);

        $reportFilename = 'report-' . $this->dateStartFormatted . '-' . $this->dateEndFormatted . '.md';
        $reportPath = $input->getOption('outputDir') . DIRECTORY_SEPARATOR . $reportFilename;

        $graphQLQuery = new Query();
        $graphQLQuery->setQuery('type:issue created:' . $this->dateStartFormatted . '..' . $this->dateEndFormatted . ' sort:created-desc repo:PrestaShop/PrestaShop');
        $issues = $this->github->search($graphQLQuery);

        $output->writeln('Found ' . count($issues) . ' issues');

        foreach ($issues as $issue) {
            $issue = $issue['node'];
            if ($issue['state'] === 'OPEN') {
                ++$this->results['open'];
            } else {
                ++$this->results['closed'];
            }
            foreach ($issue['labels']['nodes'] as $label) {
                if ($label['name'] === 'Regression') {
                    $this->results['regressions'][] = $issue;
                }
                if ($label['name'] === 'Detected by TE') {
                    ++$this->results['regressions_TE'];
                }
                if ($label['name'] === 'Duplicate') {
                    $this->results['duplicates'][] = $issue;
                }
            }
        }

        // Let's get all source regressions
        $output->writeln('Retrieving duplicates original data...');

        $issuesOrigin = [];
        foreach ($this->results['duplicates'] as $duplicate) {
            $comments = $this->githubTypedEndpointProvider->getIssueEndpoint($this->github->getClient())->comments()->all('PrestaShop', 'PrestaShop', $duplicate['number']);
            foreach ($comments as $comment) {
                preg_match('/Duplicates? of #(\d+)/i', $comment['body'], $matches);
                if (isset($matches[1])) {
                    // We got the original issue number
                    $issueNumOriginal = $matches[1];
                    $issueOriginal = $this->githubTypedEndpointProvider->getIssueEndpoint($this->github->getClient())->show('PrestaShop', 'PrestaShop', $issueNumOriginal);

                    if (!isset($issuesOrigin[$issueNumOriginal])) {
                        $issuesOrigin[$issueNumOriginal] = $issueOriginal;
                    }
                    $issuesOrigin[$issueNumOriginal]['duplicates'][] = $duplicate;
                    break;
                }
            }
        }
        $this->results['duplicates'] = $issuesOrigin;

        // Let's get all source regressions
        $output->writeln('Writing report...');
        $template = file_get_contents('var/data/templates/report.md');

        $data = [
            'start-date' => $this->dateStartFormatted,
            'end-date' => $this->dateEndFormatted,
            'period' => ceil(($this->dateEnd - $this->dateStart) / (24 * 3600)),
            'issues-created' => count($issues),
            'issues-open' => $this->results['open'],
            'issues-closed' => $this->results['closed'],
            'issues-duplicates' => count($this->results['duplicates']),
            'issues-duplicates-percentage' => round((count($this->results['duplicates']) / count($issues) * 100), 1),
            'issues-regressions' => count($this->results['regressions']),
            'issues-regressions-percentage' => round((count($this->results['regressions']) / count($issues) * 100), 1),
            'issues-detected-by-te' => $this->results['regressions_TE'],
            'issues-detected-by-te-percentage' => round(($this->results['regressions_TE'] / count($issues) * 100), 1),
            'creation-date' => date('Y-m-d H:i'),
            'duplicate-table' => $this->getDuplicatesTable($this->results['duplicates']),
        ];
        foreach ($data as $k => $v) {
            $template = str_replace("%$k%", $v, $template);
        }

        $this->writeReport($reportPath, $template);
        $output->writeln("Report $reportFilename written.");
    }

    private function excerpt(string $string, int $length = 50): string
    {
        if (mb_strlen($string) > $length) {
            return mb_substr($string, 0, $length) . '...';
        }

        return $string;
    }

    private function getDuplicatesTable(array $duplicates): string
    {
        $table = '';

        foreach ($duplicates as $duplicate) {
            $priority = $isBOorFO = $state = '-';
            foreach ($duplicate['labels'] as $label) {
                if (in_array($label['name'], ['Trivial', 'Minor', 'Major', 'Critical'])) {
                    $priority = $label['name'];
                }
                if ($label['name'] == 'Improvement') {
                    $priority = 'Improvement';
                }
                if ($label['name'] == 'FO') {
                    $isBOorFO = 'FO';
                }
                if ($label['name'] == 'BO') {
                    $isBOorFO = 'BO';
                }
                if (in_array($label['name'], ['Refactoring', 'TBS', 'TBR'])) {
                    $state = $label['name'];
                }
            }
            if ($duplicate['state'] == 'closed') {
                $state = 'Closed';
            }
            if ($state == '') {
                $state = '**To do**';
            }
            foreach ($duplicate['duplicates'] as $duplicateItem) {
                $table .= implode('|', [
                    '[' . $duplicateItem['number'] . '](' . $duplicateItem['url'] . ')',
                    $this->excerpt($duplicateItem['title']),
                    date('Y-m-d', strtotime($duplicateItem['createdAt'])),
                    $isBOorFO,
                    '[' . $duplicate['number'] . '](' . $duplicate['html_url'] . ')',
                    $state,
                    $priority,
                ]);
                $table .= PHP_EOL;
            }
        }

        return $table;
    }

    private function writeReport(string $file, string $content): void
    {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        $hFile = fopen($file, 'w');
        fwrite($hFile, $content);
        fclose($hFile);
    }
}
