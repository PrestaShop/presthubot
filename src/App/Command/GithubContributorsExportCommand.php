<?php

namespace Console\App\Command;

use Console\App\Service\Github;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubContributorsExportCommand extends Command
{
    /**
     * @var Github;
     */
    protected $github;

    protected function configure()
    {
        $this->setName('github:contributors:export')
            ->setDescription('Export Contributors PR')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN'] ?? null
            )
            ->addOption(
                'contributorsFile',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                'contributors.csv' ?? null
            )
            ->addOption(
                'outputFile',
                null,
                InputOption::VALUE_REQUIRED,
                ''
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->github = new Github($input->getOption('ghtoken'));

        // Get Stats
        $time = time();
        $this->export($input, $output);
        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);

        return 0;
    }

    private function export(InputInterface $input, OutputInterface $output): void
    {
        $contributorsFile = $input->getOption('contributorsFile');
        if (!is_file($contributorsFile)) {
            $output->writeLn(['<error>File "' . $contributorsFile . '" not found</error>']);

            return;
        }
        $outputFile = $input->getOption('outputFile');
        if (is_file($outputFile)) {
            $output->writeLn(['<error>Remove file "' . $outputFile . '"</error>']);

            return;
        }

        // Fetch List of contributors
        $hFile = fopen($contributorsFile, 'r');
        $contributors = [];
        if ($hFile !== false) {
            while (($data = fgetcsv($hFile, 1000, ',')) !== false) {
                // Column 0 : Agency Name
                // Column 1 : Contributor Github Nickname
                $contributors[$data[1]] = $data[0];
            }
            fclose($hFile);
        }

        if (empty($contributors)) {
            return;
        }

        $export = [];
        foreach ($contributors as $author => $agency) {
            $pullRequests = $this->github->getClient()->api('search')->issues('org:PrestaShop is:pr author:' . $author);
            $output->writeLn(['[' . $agency . '] ' . $author . ' (' . count($pullRequests['items']) . ')']);

            foreach ($pullRequests['items'] as $pullRequest) {
                $repository = str_replace('https://api.github.com/repos/PrestaShop/', '', $pullRequest['repository_url']);
                $isBug = array_reduce($pullRequest['labels'], function (bool $carry, array $item) {
                    if ($carry) {
                        return $carry;
                    }

                    return $item['name'] === 'Bug';
                }, false);
                $isImprovement = array_reduce($pullRequest['labels'], function (bool $carry, array $item) {
                    if ($carry) {
                        return $carry;
                    }

                    return $item['name'] === 'Improvement';
                }, false);
                $status = array_reduce($pullRequest['labels'], function (string $carry, array $item) {
                    if (!empty($carry)) {
                        return $carry;
                    }
                    if (in_array($item['name'], [
                        'Waiting for UX',
                        'Waiting for PM',
                        'Waiting for wording',
                        'Waiting for author',
                        'Waiting for dev',
                    ])) {
                        return $item['name'];
                    }

                    return '';
                }, '');
                $branch = array_reduce($pullRequest['labels'], function (bool $carry, array $item) {
                    if (!empty($carry)) {
                        return $carry;
                    }
                    if ($item['name'] === 'develop') {
                        return $item['name'];
                    }

                    return '';
                }, '');
                $apiPullRequest = $this->github->getClient()->api('pr')->show('PrestaShop', $repository, $pullRequest['number']);

                $severityIssue = '';
                $linkedIssue = $this->github->getLinkedIssue($pullRequest);
                if ($linkedIssue) {
                    $severityIssue = array_reduce($linkedIssue['labels'], function (string $carry, array $item) {
                        if (!empty($carry)) {
                            return $carry;
                        }
                        if (strpos($item['description'], 'Severity') === 0) {
                            return $item['name'];
                        }

                        return '';
                    }, '');
                }

                $export[] = [
                    // Repository
                    $repository,
                    // PR number
                    $pullRequest['number'],
                    // PR URL link
                    $pullRequest['html_url'],
                    // PR label (bug or improvement)
                    ($isBug ? 'bug' : ($isImprovement ? 'improvement' : '')),
                    // PR status (WIP, reviewed, To be tested, Closed) if not merged
                    $status,
                    // PR branch
                    $branch,
                    // Date of creation of the PR,
                    substr($pullRequest['created_at'], 0, 10),
                    // Date of close of the PR
                    !empty($pullRequest['closed_at']) ? substr($pullRequest['closed_at'], 0, 10) : '',
                    // Date of merge of the PR if there is one
                    $apiPullRequest['merged'] ? substr($apiPullRequest['merged_at'], 0, 10) : '',
                    // PR author
                    $author,
                    // Company
                    $agency,
                    // Issues related
                    $linkedIssue ? $linkedIssue['number'] : '',
                    // Issues URLs
                    $linkedIssue ? $linkedIssue['html_url'] : '',
                    // Severity labels of the issues if they are bugs
                    $severityIssue,
                    // Number of comments on the issues
                    $linkedIssue ? $linkedIssue['comments'] : '',
                    // Number of comments on the PR
                    $pullRequest['comments'],
                ];
            }
        }

        // Export CSV
        $hFile = fopen($outputFile, 'w');
        foreach ($export as $fields) {
            fputcsv($hFile, $fields);
        }
        fclose($hFile);

        return;
    }
}
