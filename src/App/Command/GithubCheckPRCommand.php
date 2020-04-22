<?php
namespace Console\App\Command;

use DateInterval;
use DateTime;
use Console\App\Service\Github;
use Github\ResultPager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
 
class GithubCheckPRCommand extends Command
{
    const LABEL_QA_OK = 'label:\"QA ✔️\"';
    const LABEL_WAITING_FOR_AUTHOR = 'label:\"waiting for author\"';
    const LABEL_WAITING_FOR_PM = 'label:\"waiting for PM\"';
    const LABEL_WAITING_FOR_QA = 'label:\"waiting for QA\"';
    const LABEL_WAITING_FOR_REBASE = 'label:\"waiting for rebase\"';
    const LABEL_WAITING_FOR_UX = 'label:\"waiting for UX\"';
    const LABEL_WAITING_FOR_WORDING = 'label:\"waiting for Wording\"';
    const LABEL_WIP = 'label:WIP';

    const GRAPHQL_REQUEST = '{
        search(query: "%queryString%", type: ISSUE, last: 100%pageAfter%) {
          issueCount
          pageInfo {
            endCursor
            hasNextPage
          }
          edges {
            node {
              ... on PullRequest {
                number
                author {
                  login
                  url
                }
                url
                title
                body
                createdAt
                files(last: 100) {
                  nodes {
                    path
                  }
                }
                milestone {
                  title
                }
                repository {
                  name
                  url
                }
                reviews(last: 100) {
                  totalCount
                  nodes {
                    author {
                      login
                    }
                    state
                    createdAt
                  }              
                }
              }
            }
          }
        }
      }';

    private const MAINTAINER_MEMBERS = [
        'atomiix',
        'eternoendless',
        'jolelievre',
        'matks',
        'matthieu-rolland',
        'mickaelandrieu',
        'NeOMakinG',
        'PierreRambaud',
        'Progi1984',
        'Quetzacoalt91',
        'rokaszygmantas',
        'sowbiba',
        'tomlev',
    ];
    
      /**
     * @var Github;
     */
     protected $github;

    protected function configure()
    {
        $this->setName('github:check:pr')
            ->setDescription('Check Github PR')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN']
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
            );
        
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->github = new Github($input->getOption('ghtoken'));
        $time = time();

        $date = new DateTime();
        $date->sub(new DateInterval('P1D'));
        $requests = [
            // Check Merged PR (Milestone, Issue & Milestone)
            'Merged PR' => 'is:merged merged:>'.$date->format('Y-m-d'),
            // Check PR waiting for merge
            'PR Waiting for Merge' => 'is:open archived:false ' . self::LABEL_QA_OK
                .' -'.self::LABEL_WAITING_FOR_REBASE
                .' -'.self::LABEL_WAITING_FOR_AUTHOR,
            // Check PR waiting for QA
            'PR Waiting for QA' => 'is:open archived:false ' . self::LABEL_WAITING_FOR_QA
                .' -'.self::LABEL_WAITING_FOR_AUTHOR,
            // Check PR waiting for Rebase
            'PR Waiting for Rebase' => 'is:open archived:false ' . self::LABEL_WAITING_FOR_REBASE,
            // Check PR waiting for PM
            'PR Waiting for PM' => 'is:open archived:false ' . self::LABEL_WAITING_FOR_PM .' -'.self::LABEL_WAITING_FOR_AUTHOR,
            // Check PR waiting for UX
            'PR Waiting for UX' => 'is:open archived:false ' . self::LABEL_WAITING_FOR_UX .' -'.self::LABEL_WAITING_FOR_AUTHOR,
            // Check PR waiting for Wording
            'PR Waiting for Wording' => 'is:open archived:false ' . self::LABEL_WAITING_FOR_WORDING .' -'.self::LABEL_WAITING_FOR_AUTHOR,
            // Check PR waiting for Author
            'PR Waiting for Author' => 'is:open archived:false ' . self::LABEL_WAITING_FOR_AUTHOR,
            // Check PR waiting for Review 
            'PR Waiting for Review' => 'is:open archived:false'
                .' -' . self::LABEL_QA_OK
                .' -' . self::LABEL_WAITING_FOR_AUTHOR
                .' -' . self::LABEL_WAITING_FOR_PM
                .' -' . self::LABEL_WAITING_FOR_QA
                .' -' . self::LABEL_WAITING_FOR_REBASE
                .' -' . self::LABEL_WAITING_FOR_UX
                .' -' . self::LABEL_WAITING_FOR_WORDING
                .' -' . self::LABEL_WAITING_FOR_QA
                .' -' . self::LABEL_WIP,
        ];

        $request = $input->getOption('request');
        if ($request) {
            if (array_key_exists($request, $requests)) {
                $requests = [
                    $request => $requests[$request]
                ];
            } else {
                $requests = [
                    $request => $request
                ];
            }
        }
        $filterFile = $input->getOption('filter:file');
        $filterFile = empty($filterFile) ? null : explode(',', $filterFile);
        $filterNumApproved = $input->getOption('filter:numapproved');
        $filterNumApproved = empty($filterNumApproved) ? null : explode(',', $filterNumApproved);
        $filterExcludeAuthor = $input->getOption('exclude:author');
        $filterExcludeReviewer = $input->getOption('exclude:reviewer');
        $table = new Table($output);
        $table->setStyle('box');
        foreach($requests as $title => $request) {
            $result = $this->github->search(self::GRAPHQL_REQUEST, [
                '%queryString%' => 'org:PrestaShop is:pr ' . $request
            ]);
            $hasRows = $this->checkPR(
                $title,
                $result,
                $output,
                $table,
                $hasRows ?? false,
                empty($filterFile) ? false : ($title == 'PR Waiting for Review' || count($requests) == 1 ? true : false),
                $filterFile,
                $filterNumApproved,
                $filterExcludeAuthor,
                $filterExcludeReviewer
            );
        }

        $table->render();
        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);
    }

    private function checkPR(
        string $title,
        array $returnSearch,
        OutputInterface $output,
        Table $table,
        bool $hasRows,
        bool $needCountFilesType,
        ?array $fileTypeAuth,
        ?array $filterNumApproved,
        ?string $filterExcludeAuthor,
        ?string $filterExcludeReviewer
    ) {
        $rows = [];
        uasort($returnSearch, function($row1, $row2) {
            if ($row1['node']['repository']['name'] == $row2['node']['repository']['name']) {
                if ($row1['node']['number'] == $row2['node']['number']) {
                    return 0;
                }
                return $row1['node']['number'] < $row2['node']['number'] ? -1 : 1;
            }
            return $row1['node']['repository']['name'] < $row2['node']['repository']['name'] ? -1 : 1;
        });
        $countPR = 0;
        foreach($returnSearch as $pullRequest) {
            $pullRequest = $pullRequest['node'];
            $linkedIssue = $this->github->getLinkedIssue($pullRequest);

            $pullRequestApproved = $this->getApproved($pullRequest);
            $pullRequestTitle = str_split($pullRequest['title'], 70);
            $pullRequestTitle = implode(PHP_EOL, $pullRequestTitle);
            $pullRequestTitle = '('. count($pullRequestApproved) .'✓) '. $pullRequestTitle;
            
            // Filter File Type
            $countFilesTypeTitle = '';
            if ($needCountFilesType) {
                $authFilterFileType = empty($fileTypeAuth);
                $countFilesType = $this->countFileType($pullRequest);
                foreach ($countFilesType as $fileType => $count) {
                    $countFilesTypeTitle .= $fileType . ' (' . $count . ')' . PHP_EOL;
                    if (!empty($fileTypeAuth) && in_array($fileType, $fileTypeAuth)) {
                        $authFilterFileType = true;
                    }
                }
                $countFilesTypeTitle = substr($countFilesTypeTitle, 0, -1);
            } else {
                $authFilterFileType = true;
            }
            if ($authFilterFileType === false) {
                continue;
            }
            // Filter Num Approved
            if ($filterNumApproved) {
                if (!in_array(count($pullRequestApproved), $filterNumApproved)) {
                    continue;
                }
            }
            // Filter Author
            if ($filterExcludeAuthor) {
                if ($pullRequest['author']['login'] == $filterExcludeAuthor) {
                    continue;
                }
            }
            // Filter Reviewer
            if ($filterExcludeReviewer) {
                if (in_array($filterExcludeReviewer, $pullRequestApproved)) {
                    continue;
                }
            }
            
            $currentRow = [
                '<href='.$pullRequest['repository']['url'].'>'.$pullRequest['repository']['name'].'</>',
                '<href='.$pullRequest['url'].'>#'.$pullRequest['number'].'</>',
                $pullRequest['createdAt'],
                $pullRequestTitle,
                '<href='.$pullRequest['author']['url'].'>'.$pullRequest['author']['login'].'</>',
                !empty($pullRequest['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>',
                !is_null($linkedIssue) && $pullRequest['repository']['name'] == 'PrestaShop'
                    ? (!empty($linkedIssue['milestone']) ? '<info>✓ </info>' : '<error>✗ </error>') .' <href='.$linkedIssue['html_url'].'>#'.$linkedIssue['number'].'</>'
                    : '',
            ];
            if ($needCountFilesType) {
                array_push($currentRow, $countFilesTypeTitle);
            }

            if (!isset($rows[count($pullRequestApproved)])) {
                $rows[count($pullRequestApproved)] = [];
            }
            $rows[count($pullRequestApproved)][] = $currentRow;
            $countPR++;
        }
        krsort($rows);
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
        if ($needCountFilesType) {
            array_push($headers, '<info>Files</info>');
        }
        $table->addRows([
            [new TableCell('<fg=black;bg=white;options=bold> ' . $title . ' ('.$countPR.') </>', ['colspan' => 7])],
            new TableSeparator(),
            new TableSeparator(),
            $headers
        ]);
        foreach($rows as $key => $rowsNumApproved) {
            $table->addRows($rowsNumApproved);
            if ($key !== array_key_last($rows)) {
                $table->addRows([new TableSeparator()]);
            }
        }
        return true;
    }

    protected function countFileType(array $pullRequest): array
    {
        $types = [];

        foreach($pullRequest['files']['nodes'] as $file) {
            $extension = pathinfo($file['path'], PATHINFO_EXTENSION);
            if (!array_key_exists($extension, $types)) {
                $types[$extension] = 0;
            }
            $types[$extension]++;
        }
        ksort($types);
        
        return $types;
    }

    protected function getApproved(array $pullRequest): array
    {
        $approved = [];
        foreach($pullRequest['reviews']['nodes'] as $node) {
            $login = $node['author']['login'];
            if (!in_array($login, self::MAINTAINER_MEMBERS)) {
                continue;
            }
            if ($node['state'] == 'APPROVED') {
                if (!in_array($login, $approved)) {
                    $approved[] = $login;
                }
            } else {
                if (in_array($login, $approved)) {
                    $pos = array_search($login, $approved);
                    unset($approved[$pos]);
                }
            }
        }
        return $approved;
    }
}
