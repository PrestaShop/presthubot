<?php

declare(strict_types=1);

namespace Console\App\Command;

use Console\App\Service\Github\Github;
use Console\App\Service\Github\Query;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GithubReleaseNoteCommand extends Command
{
    /**
     * @var Github;
     */
    protected $github;

    /**
     * @var OutputInterface;
     */
    protected $output;

    /**
     * @var int;
     */
    protected $countRows = 0;

    /**
     * @var string
     */
    private $tag;

    /**
     * @var SymfonyStyle
     */
    private $io;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function configure()
    {
        $this->setName('github:release:note')
            ->setDescription('Get release notes from github for a specific milestone')
            ->addArgument('milestone', InputOption::VALUE_REQUIRED, 'Milestone to be checked')
            ->addOption(
                'output',
                null,
                InputOption::VALUE_OPTIONAL,
                'output type (table|markdown)',
                'table'
            )
            ->addOption(
                'repository',
                null,
                InputOption::VALUE_OPTIONAL,
                'repository (PrestaShop by default)',
                'PrestaShop'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->github = new Github($_ENV['GH_TOKEN']);
        $this->output = $output;
        $milestone = $input->getArgument('milestone');
        if (!$milestone) {
            $milestone = $this->io->ask('Please provide a milestone');
        }
        $time = time();

        $graphQLQuery = new Query();
        $graphQLQuery->setQuery(
            sprintf(
                'type:issue repo:PrestaShop/%s is:issue is:closed milestone:%s',
                $input->getOption('repository'),
                $milestone
            )
        );
        $issues = $this->formatIssues(
            $this->github->search($graphQLQuery)
        );
        $this->displayOutput($input, $milestone, $issues, $time);

        return 0;
    }

    private function formatIssues(
        array $resultAPI
    ): array {
        $rows = [];
        foreach ($resultAPI as $key => $pullRequest) {
            $rows[$key]['number'] = $pullRequest['node']['number'];
            $rows[$key]['url'] = $pullRequest['node']['url'];
            $rows[$key]['title'] = $pullRequest['node']['title'];
        }

        return $rows;
    }

    /**
     * @param InputInterface $input
     * @param string $milestone
     * @param array $issues
     * @param int $time
     *
     * @return void
     */
    private function displayOutput(InputInterface $input, string $milestone, array $issues, int $time): void
    {
        if ($input->getOption('output') === 'table') {
            $table = new Table($this->output);
            $table->setStyle('box');
            $table->addRows([
                [new TableCell(
                    sprintf(
                        '<bg=blue;options=bold> Issues involved in the milestone %s (%s)</>',
                        $milestone,
                        count($issues)),
                    ['colspan' => 3]
                )],
                new TableSeparator(),
                [
                    '<info>Issue N°</info>',
                    '<info>url</info>',
                    '<info>Title</info>',
                ],
            ]);
            $table->addRows($issues);
            $table->render();
        } else {
            $this->io->title('Issues involved in the milestone ' . $milestone);
            foreach ($issues as $issue) {
                $this->output->writeLn([
                    sprintf('- [%s](%s)', $issue['title'], $issue['url']),
                ]);
            }
        }

        $this->output->writeLn(['', 'Output generated in ' . (time() - $time) . 's for ' . $this->countRows . ' rows.']);
    }
}
