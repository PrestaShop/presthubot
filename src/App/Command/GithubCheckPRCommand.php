<?php

namespace Console\App\Command;

use Console\App\Service\Github;
use Console\App\Service\Github\Filters;
use Console\App\Service\Github\Query;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubCheckPRCommand extends Command
{
    public const ORDERBY_PROJECT = 'projectName';
    public const ORDERBY_ID = 'id';
    public const ORDERBY_CREATEDDATE = 'createdAt';
    public const ORDERBY_NUMAPPROVED = 'numApproved';

    private const DEFAULT_ORDERBY = [
        self::ORDERBY_PROJECT,
        self::ORDERBY_NUMAPPROVED,
        self::ORDERBY_ID,
    ];

    /**
     * @var Filters
     */
    protected $filters;
    /**
     * @var Github
     */
    protected $github;
    /**
     * @var array
     */
    protected $orderBy;
    /**
     * @var OutputInterface
     */
    protected $output;
    /**
     * @var int
     */
    protected $countRows = 0;

    protected function configure()
    {
        $this->setName('github:check:pr')
            ->setDescription('Check Github PR')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN'] ?? null
            )
            ->addOption(
                'request',
                null,
                InputOption::VALUE_OPTIONAL
            )
            ->addOption(
                'filter:file',
                null,
                InputOption::VALUE_OPTIONAL
            )
            ->addOption(
                'filter:numapproved',
                null,
                InputOption::VALUE_OPTIONAL
            )
            ->addOption(
                'exclude:author',
                null,
                InputOption::VALUE_OPTIONAL
            )
            ->addOption(
                'exclude:reviewer',
                null,
                InputOption::VALUE_OPTIONAL
            )
            ->addOption(
                'orderBy',
                null,
                InputOption::VALUE_OPTIONAL,
                'Order By Column (' . implode(',', self::DEFAULT_ORDERBY) . ')',
                implode(',', self::DEFAULT_ORDERBY)
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->github = new Github($input->getOption('ghtoken'));
        $this->output = $output;
        $this->orderBy = explode(',', $input->getOption('orderBy'));

        $time = time();

        $request = $input->getOption('request');
        if ($request) {
            if (array_key_exists($request, Query::getRequests())) {
                $requests = [
                    $request => Query::getRequests()[$request],
                ];
            } else {
                $requests = [
                    $request => $request,
                ];
            }
        } else {
            $requests = Query::getRequests();
        }

        $this->filters = new Filters();

        $filterFile = $input->getOption('filter:file');
        $filterNumApproved = $input->getOption('filter:numapproved');
        $filterExcludeAuthor = $input->getOption('exclude:author');
        $filterExcludeReviewer = $input->getOption('exclude:reviewer');
        if (!is_null($filterFile)) {
            $this->filters->addFilter(Filters::FILTER_FILE_EXTENSION, explode(',', $filterFile), true);
        }
        if (!is_null($filterNumApproved)) {
            $filterNumApproved = explode(',', $filterNumApproved);
            $filterNumApproved = array_map('intval', $filterNumApproved);
            $this->filters->addFilter(Filters::FILTER_NUM_APPROVED, $filterNumApproved, true);
        }
        if (!is_null($filterExcludeAuthor)) {
            $this->filters->addFilter(Filters::FILTER_AUTHOR, explode(',', $filterExcludeAuthor), false);
        }
        if (!is_null($filterExcludeReviewer)) {
            $this->filters->addFilter(Filters::FILTER_REVIEWER, explode(',', $filterExcludeReviewer), false);
        }
        $table = new Table($this->output);
        $table->setStyle('box');
        foreach ($requests as $title => $request) {
            $graphQLQuery = new Query();
            $graphQLQuery->setQuery('org:PrestaShop is:pr ' . $request);
            $hasRows = $this->checkPR(
                $table,
                $title,
                $this->github->search($graphQLQuery),
                $hasRows ?? false
            );
        }

        $table->render();
        $this->output->writeLn(['', 'Output generated in ' . (time() - $time) . 's for ' . $this->countRows . ' rows.']);

        return 0;
    }

    private function checkPR(
        Table $table,
        string $title,
        array $resultAPI,
        bool $hasRows
    ) {
        $rows = [];
        foreach ($resultAPI as $key => $pullRequest) {
            $resultAPI[$key] = $pullRequest['node'];
            $resultAPI[$key]['approved'] = $this->github->extractPullRequestState($resultAPI[$key], Github::PULL_REQUEST_STATE_APPROVED);
            $resultAPI[$key]['request_changes'] = $this->github->extractPullRequestState($resultAPI[$key], Github::PULL_REQUEST_STATE_REQUEST_CHANGES);
            $resultAPI[$key]['comment'] = $this->github->extractPullRequestState($resultAPI[$key], Github::PULL_REQUEST_STATE_COMMENT);
        }
        uasort($resultAPI, function ($row1, $row2) {
            $projectName1 = $row1['repository']['name'];
            $projectName2 = $row2['repository']['name'];
            $id1 = $row1['number'];
            $id2 = $row2['number'];
            $createdAt1 = $row1['createdAt'];
            $createdAt2 = $row2['createdAt'];
            $numApproved1 = count($row1['approved']);
            $numApproved2 = count($row2['approved']);

            $return = 0;
            foreach ($this->orderBy as $orderKey) {
                if (isset(${$orderKey . '1'}, ${$orderKey . '2'})) {
                    if (${$orderKey . '1'} == ${$orderKey . '2'}) {
                        continue;
                    }

                    return ${$orderKey . '1'} < ${$orderKey . '2'} ? -1 : 1;
                }
            }

            return $return;
        });
        $countPR = 0;
        foreach ($resultAPI as $pullRequest) {
            if (!$this->github->isPRValid($pullRequest, $this->filters)) {
                continue;
            }

            $pullRequestTitle = str_split($pullRequest['title'], 70);
            $pullRequestTitle = implode(PHP_EOL, $pullRequestTitle);
            $pullRequestTitle = '(' . count($pullRequest['approved']) . '✓) ' . $pullRequestTitle;

            $linkedIssue = $this->github->getLinkedIssue($pullRequest);
            $currentRow = [
                '<href=' . $pullRequest['repository']['url'] . '>' . $pullRequest['repository']['name'] . '</>',
                '<href=' . $pullRequest['url'] . '>#' . $pullRequest['number'] . '</>',
                $pullRequest['createdAt'],
                $pullRequestTitle,
                '<href=' . $pullRequest['author']['url'] . '>' . $pullRequest['author']['login'] . '</>',
                !empty($pullRequest['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>',
                !is_null($linkedIssue) && $pullRequest['repository']['name'] == 'PrestaShop'
                    ? (!empty($linkedIssue['milestone']) ? '<info>✓ </info>' : '<error>✗ </error>') . ' <href=' . $linkedIssue['html_url'] . '>#' . $linkedIssue['number'] . '</>'
                    : '',
            ];

            $rows[] = $currentRow;
            ++$countPR;
        }
        if (empty($rows)) {
            return $hasRows;
        }
        if ($hasRows) {
            $table->addRows([new TableSeparator()]);
        }

        $headers = [
            '<info>Project</info>',
            '<info>#</info>',
            '<info>Created At</info>',
            '<info>Title</info>',
            '<info>Author</info>',
            '<info>Milestone</info>',
            '<info>Issue</info>',
        ];
        $table->addRows([
            [new TableCell('<fg=black;bg=white;options=bold> ' . $title . ' (' . $countPR . ') </>', ['colspan' => 7])],
            new TableSeparator(),
            $headers,
        ]);
        $table->addRows($rows);

        $this->countRows += $countPR;

        return true;
    }
}
