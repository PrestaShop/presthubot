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
 
class GithubCheckPRCommand extends Command
{
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
                InputOption::VALUE_OPTIONAL
            )
            ->addOption(
                'username',
                null,
                InputOption::VALUE_REQUIRED
            );
        
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->client = new Client();
        $ghToken = $input->getOption('ghtoken');
        if (!empty($ghToken)) {
            $this->client->authenticate($ghToken, null, Client::AUTH_URL_TOKEN);
        }

        $table = new Table($output);
        $table->setStyle('box');

        // Check Merged PR (Milestone, Issue & Milestone)
        $hasRows = $this->checkMergedPR($input, $output, $table, false);
        // Check PR waiting for merge
        $hasRows = $this->checkPRWaitingForMerge($input, $output, $table, $hasRows);
        // Check PR waiting for QA
        $hasRows = $this->checkPRWaitingForQA($input, $output, $table, $hasRows);
        // Check PR waiting for PM
        $hasRows = $this->checkPRWaitingForPM($input, $output, $table, $hasRows);
        // Check PR waiting for UX
        $hasRows = $this->checkPRWaitingForUX($input, $output, $table, $hasRows);
        // Check PR waiting for Wording
        $hasRows = $this->checkPRWaitingForWording($input, $output, $table, $hasRows);

        $table->render();
    }

    private function checkMergedPR(InputInterface $input, OutputInterface $output, Table $table, bool $hasRows)
    {
        $date = new DateTime();
        $date->sub(new DateInterval('P1D'));

        $mergedPullRequests = $this->client->api('search')->issues('repo:PrestaShop/PrestaShop is:pr is:merged merged:>'.$date->format('Y-m-d'));

        $rows = [];
        foreach($mergedPullRequests['items'] as $pullRequest) {
            $linkedIssue = $this->getIssue($output, $pullRequest);
            
            $rows[] = [
                '<href='.$pullRequest['html_url'].'>#'.$pullRequest['number'].'</>',
                $pullRequest['created_at'],
                $pullRequest['title'],
                '<href='.$pullRequest['user']['html_url'].'>'.$pullRequest['user']['login'].'</>',
                !empty($pullRequest['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>',
                !is_null($linkedIssue) ? '<href='.$linkedIssue['html_url'].'>#'.$linkedIssue['number'].'</>' : '',
                !is_null($linkedIssue) ? (!empty($linkedIssue['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>') : '',
            ];
        }
        if (empty($rows)) {
            return false;
        }
        if ($hasRows) {
            $table->addRows([new TableSeparator()]);
        }
        $table->addRows([
            [new TableCell('<fg=black;bg=white;options=bold> PR Merged </>', ['colspan' => 7])],
            new TableSeparator(),
            ['<info>#</info>', '<info>Created At</info>', '<info>Title</info>', '<info>Author</info>', '<info>Milestone</info>', '<info>Issue</info>', '<info>Milestone</info>'],
            new TableSeparator(),
        ]);
        $table->addRows($rows);
        return true;
    }

    private function checkPRWaitingForMerge(InputInterface $input, OutputInterface $output, Table $table, bool $hasRows)
    {
        $mergedPullRequests = $this->client->api('search')->issues('repo:PrestaShop/PrestaShop is:open is:pr label:"QA ✔️"');

        $rows = [];
        foreach($mergedPullRequests['items'] as $pullRequest) {
            $linkedIssue = $this->getIssue($output, $pullRequest);
            
            $rows[] = [
                '<href='.$pullRequest['html_url'].'>#'.$pullRequest['number'].'</>',
                $pullRequest['created_at'],
                $pullRequest['title'],
                '<href='.$pullRequest['user']['html_url'].'>'.$pullRequest['user']['login'].'</>',
                !empty($pullRequest['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>',
                !is_null($linkedIssue) ? '<href='.$linkedIssue['html_url'].'>#'.$linkedIssue['number'].'</>' : '',
                !is_null($linkedIssue) ? (!empty($linkedIssue['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>') : '',
            ];
        }
        if (empty($rows)) {
            return false;
        }
        if ($hasRows) {
            $table->addRows([new TableSeparator()]);
        }
        $table->addRows([
            [new TableCell('<fg=black;bg=white;options=bold> PR Waiting for merge </>', ['colspan' => 7])],
            new TableSeparator(),
            ['<info>#</info>', '<info>Created At</info>','<info>Title</info>', '<info>Author</info>', '<info>Milestone</info>', '<info>Issue</info>', '<info>Milestone</info>'],
            new TableSeparator(),
        ]);
        $table->addRows($rows);
        return true;
    }

    private function checkPRWaitingForQA(InputInterface $input, OutputInterface $output, Table $table, bool $hasRows)
    {
        $mergedPullRequests = $this->client->api('search')->issues('repo:PrestaShop/PrestaShop is:open is:pr label:"waiting for QA"');

        $rows = [];
        foreach($mergedPullRequests['items'] as $pullRequest) {
            $linkedIssue = $this->getIssue($output, $pullRequest);
            
            $rows[] = [
                '<href='.$pullRequest['html_url'].'>#'.$pullRequest['number'].'</>',
                $pullRequest['created_at'],
                $pullRequest['title'],
                '<href='.$pullRequest['user']['html_url'].'>'.$pullRequest['user']['login'].'</>',
                !empty($pullRequest['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>',
                !is_null($linkedIssue) ? '<href='.$linkedIssue['html_url'].'>#'.$linkedIssue['number'].'</>' : '',
                !is_null($linkedIssue) ? (!empty($linkedIssue['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>') : '',
            ];
        }
        if (empty($rows)) {
            return false;
        }
        if ($hasRows) {
            $table->addRows([new TableSeparator()]);
        }
        $table->addRows([
            [new TableCell('<fg=black;bg=white;options=bold> PR Waiting for QA </>', ['colspan' => 7])],
            new TableSeparator(),
            ['<info>#</info>', '<info>Created At</info>','<info>Title</info>', '<info>Author</info>', '<info>Milestone</info>', '<info>Issue</info>', '<info>Milestone</info>'],
            new TableSeparator(),
        ]);
        $table->addRows($rows);
        return true;
    }

    private function checkPRWaitingForPM(InputInterface $input, OutputInterface $output, Table $table, bool $hasRows)
    {
        $mergedPullRequests = $this->client->api('search')->issues('repo:PrestaShop/PrestaShop is:open is:pr label:"waiting for PM"');

        $rows = [];
        foreach($mergedPullRequests['items'] as $pullRequest) {
            $linkedIssue = $this->getIssue($output, $pullRequest);
            
            $rows[] = [
                '<href='.$pullRequest['html_url'].'>#'.$pullRequest['number'].'</>',
                $pullRequest['created_at'],
                $pullRequest['title'],
                '<href='.$pullRequest['user']['html_url'].'>'.$pullRequest['user']['login'].'</>',
                !empty($pullRequest['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>',
                !is_null($linkedIssue) ? '<href='.$linkedIssue['html_url'].'>#'.$linkedIssue['number'].'</>' : '',
                !is_null($linkedIssue) ? (!empty($linkedIssue['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>') : '',
            ];
        }
        if (empty($rows)) {
            return false;
        }
        if ($hasRows) {
            $table->addRows([new TableSeparator()]);
        }
        $table->addRows([
            [new TableCell('<fg=black;bg=white;options=bold> PR Waiting for PM </>', ['colspan' => 7])],
            new TableSeparator(),
            ['<info>#</info>', '<info>Created At</info>','<info>Title</info>', '<info>Author</info>', '<info>Milestone</info>', '<info>Issue</info>', '<info>Milestone</info>'],
            new TableSeparator(),
        ]);
        $table->addRows($rows);
        return true;
    }

    private function checkPRWaitingForUX(InputInterface $input, OutputInterface $output, Table $table, bool $hasRows)
    {
        $mergedPullRequests = $this->client->api('search')->issues('repo:PrestaShop/PrestaShop is:open is:pr label:"waiting for UX"');

        $rows = [];
        foreach($mergedPullRequests['items'] as $pullRequest) {
            $linkedIssue = $this->getIssue($output, $pullRequest);
            
            $rows[] = [
                '<href='.$pullRequest['html_url'].'>#'.$pullRequest['number'].'</>',
                $pullRequest['created_at'],
                $pullRequest['title'],
                '<href='.$pullRequest['user']['html_url'].'>'.$pullRequest['user']['login'].'</>',
                !empty($pullRequest['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>',
                !is_null($linkedIssue) ? '<href='.$linkedIssue['html_url'].'>#'.$linkedIssue['number'].'</>' : '',
                !is_null($linkedIssue) ? (!empty($linkedIssue['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>') : '',
            ];
        }
        if (empty($rows)) {
            return false;
        }
        if ($hasRows) {
            $table->addRows([new TableSeparator()]);
        }
        $table->addRows([
            [new TableCell('<fg=black;bg=white;options=bold> PR Waiting for UX </>', ['colspan' => 7])],
            new TableSeparator(),
            ['<info>#</info>', '<info>Created At</info>','<info>Title</info>', '<info>Author</info>', '<info>Milestone</info>', '<info>Issue</info>', '<info>Milestone</info>'],
            new TableSeparator(),
        ]);
        $table->addRows($rows);
        return true;
    }

    private function checkPRWaitingForWording(InputInterface $input, OutputInterface $output, Table $table, bool $hasRows)
    {
        $mergedPullRequests = $this->client->api('search')->issues('repo:PrestaShop/PrestaShop is:open is:pr label:"waiting for Wording"');

        $rows = [];
        foreach($mergedPullRequests['items'] as $pullRequest) {
            $linkedIssue = $this->getIssue($output, $pullRequest);
            
            $rows[] = [
                '<href='.$pullRequest['html_url'].'>#'.$pullRequest['number'].'</>',
                $pullRequest['created_at'],
                $pullRequest['title'],
                '<href='.$pullRequest['user']['html_url'].'>'.$pullRequest['user']['login'].'</>',
                !empty($pullRequest['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>',
                !is_null($linkedIssue) ? '<href='.$linkedIssue['html_url'].'>#'.$linkedIssue['number'].'</>' : '',
                !is_null($linkedIssue) ? (!empty($linkedIssue['milestone']) ? '    <info>✓</info>' : '    <error>✗ </error>') : '',
            ];
        }
        if (empty($rows)) {
            return false;
        }
        if ($hasRows) {
            $table->addRows([new TableSeparator()]);
        }
        $table->addRows([
            [new TableCell('<fg=black;bg=white;options=bold> PR Waiting for Wording </>', ['colspan' => 7])],
            new TableSeparator(),
            ['<info>#</info>', '<info>Created At</info>','<info>Title</info>', '<info>Author</info>', '<info>Milestone</info>', '<info>Issue</info>', '<info>Milestone</info>'],
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
        $issue = is_null($issueId) ? null : $this->client->api('issue')->show('PrestaShop', 'PrestaShop', $issueId);

        // API Alert
        if (isset($pullRequest['_links'])) {
            $output->writeln('PR #'.$pullRequest['number'].' has _links in its API');
        }

        return $issue;
    }
}