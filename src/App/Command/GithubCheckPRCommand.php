<?php
namespace Console\App\Command;

use DateInterval;
use DateTime;
use Github\Client;
use Github\ResultPager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
 
class GithubCheckPRCommand extends Command
{
    const LABEL_QA_OK = 'label:"QA ✔️"';
    const LABEL_WAITING_FOR_AUTHOR = 'label:"waiting for author"';
    const LABEL_WAITING_FOR_PM = 'label:"waiting for PM"';
    const LABEL_WAITING_FOR_QA = 'label:"waiting for QA"';
    const LABEL_WAITING_FOR_REBASE = 'label:"waiting for rebase"';
    const LABEL_WAITING_FOR_UX = 'label:"waiting for UX"';
    const LABEL_WAITING_FOR_WORDING = 'label:"waiting for Wording"';
    const LABEL_WIP = 'label:WIP';

    /**
     * @var Client;
     */
    protected $client;

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

        $date = new DateTime();
        $date->sub(new DateInterval('P1D'));
        $arrayRequests = [
            // Check Merged PR (Milestone, Issue & Milestone)
            'Merged PR' => 'is:merged merged:>'.$date->format('Y-m-d'),
            // Check PR waiting for merge
            'PR Waiting for Merge' => 'is:open ' . self::LABEL_QA_OK
                .' -'.self::LABEL_WAITING_FOR_REBASE,
            // Check PR waiting for QA
            'PR Waiting for QA' => 'is:open ' . self::LABEL_WAITING_FOR_QA
                .' -'.self::LABEL_WAITING_FOR_AUTHOR,
            // Check PR waiting for Rebase
            'PR Waiting for Rebase' => 'is:open ' . self::LABEL_WAITING_FOR_REBASE,
            // Check PR waiting for PM
            'PR Waiting for PM' => 'is:open ' . self::LABEL_WAITING_FOR_PM .' -'.self::LABEL_WAITING_FOR_AUTHOR,
            // Check PR waiting for UX
            'PR Waiting for UX' => 'is:open ' . self::LABEL_WAITING_FOR_UX .' -'.self::LABEL_WAITING_FOR_AUTHOR,
            // Check PR waiting for Wording
            'PR Waiting for Wording' => 'is:open ' . self::LABEL_WAITING_FOR_WORDING .' -'.self::LABEL_WAITING_FOR_AUTHOR,
            // Check PR waiting for Author
            'PR Waiting for Author' => 'is:open ' . self::LABEL_WAITING_FOR_AUTHOR,
            // Check PR waiting for Review 
            'PR Waiting for Review' => 'is:open review:required '
                .' -'.self::LABEL_WAITING_FOR_AUTHOR
                .' -'.self::LABEL_WAITING_FOR_PM
                .' -'.self::LABEL_WAITING_FOR_QA
                .' -'.self::LABEL_WAITING_FOR_REBASE
                .' -'.self::LABEL_WAITING_FOR_UX
                .' -'.self::LABEL_WAITING_FOR_WORDING
                .' -'.self::LABEL_WAITING_FOR_QA
                .' -'.self::LABEL_WIP,
        ];
        $requestCommon = 'org:PrestaShop is:pr ';

        $table = new Table($output);
        $table->setStyle('box');
        $searchApi = $this->client->api('search');
        $paginator  = new ResultPager($this->client);
        foreach($arrayRequests as $title => $request) {
            $result = $paginator->fetchAll($searchApi, 'issues', [$requestCommon . $request]);
            $hasRows = $this->checkPR($title, $result, $output, $table, $hasRows ?? false);
        }

        $table->render();
        $output->writeLn(['', 'Ouput generated in ' . (time() - $time) . 's.']);
    }

    private function checkPR(string $title, array $returnSearch, OutputInterface $output, Table $table, bool $hasRows)
    {
        $rows = [];
        uasort($returnSearch, function($row1, $row2) {
            $repoName1 = strtolower(str_replace('https://api.github.com/repos/PrestaShop/', '', $row1['repository_url']));
            $repoName2 = strtolower(str_replace('https://api.github.com/repos/PrestaShop/', '', $row2['repository_url']));
            $key = 0;
            if ($repoName1 == $repoName2) {
                if ($row1['number'] == $row2['number']) {
                    return 0;
                }
                return $row1['number'] < $row2['number'] ? -1 : 1;
            }
            return $repoName1 < $repoName2 ? -1 : 1;
        });
        foreach($returnSearch as $pullRequest) {
            $linkedIssue = $this->getIssue($output, $pullRequest);
            $repoName = str_replace('https://api.github.com/repos/PrestaShop/', '', $pullRequest['repository_url']);
            $pullRequestTitle = str_split($pullRequest['title'], 80);
            $pullRequestTitle = implode(PHP_EOL, $pullRequestTitle);
            
            $rows[] = [
                '<href=https://github.com/PrestaShop/'.$repoName.'>'.$repoName.'</>',
                '<href='.$pullRequest['html_url'].'>#'.$pullRequest['number'].'</>',
                $pullRequest['created_at'],
                $pullRequestTitle,
                '<href='.$pullRequest['user']['html_url'].'>'.$pullRequest['user']['login'].'</>',
                !empty($pullRequest['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>',
                !is_null($linkedIssue) && $repoName == 'PrestaShop'
                    ? (!empty($linkedIssue['milestone']) ? '<info>✓ </info>' : '<error>✗ </error>') .' <href='.$linkedIssue['html_url'].'>#'.$linkedIssue['number'].'</>'
                    : '',
            ];
        }
        if (empty($rows)) {
            return $hasRows;
        }
        if ($hasRows) {
            $table->addRows([new TableSeparator()]);
        }
        $table->addRows([
            [new TableCell('<fg=black;bg=white;options=bold> ' . $title . ' ('.count($rows).') </>', ['colspan' => 7])],
            new TableSeparator(),
            ['<info>Project</info>', '<info>#</info>', '<info>Created At</info>','<info>Title</info>', '<info>Author</info>', '<info>Milestone</info>', '<info>Issue</info>'],
            new TableSeparator(),
        ]);
        $table->addRows($rows);
        return true;
    }

    private function getIssue(OutputInterface $output, array $pullRequest)
    {
        // Linked Issue
        preg_match('#Fixes\s\#([0-9]{1,5})#', $pullRequest['body'], $matches);
        $issueId = !empty($matches) && !empty($matches[1]) ? $matches[1] : null;
        if (empty($issueId)) {
            preg_match('#Fixes\sissue\s\#([0-9]{1,5})#', $pullRequest['body'], $matches);
            $issueId = !empty($matches) && !empty($matches[1]) ? $matches[1] : null;
        }
        if (empty($issueId)) {
            preg_match('#Fixes\shttps:\/\/github.com\/PrestaShop\/PrestaShop\/issues\/([0-9]{1,5})#', $pullRequest['body'], $matches);
            $issueId = !empty($matches) && !empty($matches[1]) ? $matches[1] : null;
        }
        $issue = is_null($issueId) ? null : $this->client->api('issue')->show('PrestaShop', 'PrestaShop', $issueId);

        // API Alert
        if (isset($pullRequest['_links'])) {
            $output->writeln('PR #'.$pullRequest['number'].' has _links in its API');
        }

        return $issue;
    }
}