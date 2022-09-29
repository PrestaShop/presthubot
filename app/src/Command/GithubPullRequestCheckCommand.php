<?php

namespace App\Command;

use App\Service\Command\GithubCheckPR;
use App\Service\Github\Filters;
use App\Service\Github\GithubApiCache;
use App\Service\Github\Query;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubPullRequestCheckCommand extends Command
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

    protected Filters $filters;
    protected array $orderBy;
    protected OutputInterface $output;
    protected int $countRows = 0;
    private GithubApiCache $githubApiCache;
    private GithubCheckPR $githubCheckPR;

    public function __construct(
        GithubApiCache $githubApiCache,
        GithubCheckPR $githubCheckPR,
        string $name = null
    ) {
        $this->githubApiCache = $githubApiCache;
        $this->githubCheckPR = $githubCheckPR;
        $this->githubCheckPR->setGithubApiCache($this->githubApiCache);
        parent::__construct($name);
    }

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
                'Order By Column ('.implode(',', self::DEFAULT_ORDERBY).')',
                implode(',', self::DEFAULT_ORDERBY)
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $time = time();
        $requests = $this->buildParametersAndRequests($input);
        $table = new Table($output);
        $table->setStyle('box');

        foreach ($requests as $title => $request) {
            $graphQLQuery = new Query();
            $graphQLQuery->setQuery('org:PrestaShop is:pr '.$request);
            $hasRows = $this->githubCheckPR->checkPR(
                $table,
                $title,
                $graphQLQuery,
                $this->orderBy,
                $this->filters,
                $hasRows ?? false
            );
        }

        $table->render();
        $output->writeLn(['', 'Output generated in '.(time() - $time).'s for '.$this->countRows.' rows.']);

        return 0;
    }

    public function buildParametersAndRequests(InputInterface $input): array
    {
        $this->orderBy = explode(',', $input->getOption('orderBy'));

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

        return $requests;
    }
}
