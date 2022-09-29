<?php

namespace App\Command;

use App\DTO\VersionControlSystemApiResponse\Common\LabelDTO;
use App\Presenter\Contributors\Console\ContributorsPresenterConsole;
use App\Service\Command\Contributors;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class ContributorsExportCommand extends Command
{
    private Contributors $contributors;
    private SerializerInterface $serializer;
    private ContributorsPresenterConsole $contributorsPresenterConsole;

    public function __construct(
        Contributors $contributors,
        ContributorsPresenterConsole $contributorsPresenterConsole,
        SerializerInterface $serializer
    ) {
        parent::__construct();

        $this->contributors = $contributors;
        $this->serializer = $serializer;
        $this->contributorsPresenterConsole = $contributorsPresenterConsole;
    }

    protected function configure()
    {
        $this->setName('github:contributors:export')
            ->setDescription('Export Contributors PR');
    }



    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('command');
        $io = new SymfonyStyle($input, $output);
        $table = $io->createTable();
        $table->setStyle((new TableStyle())->setVerticalBorderChars('|'));

        $contributors = $this->contributors->getContributors();
        // contributors
        foreach ($this->contributors->getDetails($contributors) as $contributorsIssues) {
            $rows[] = ['<info>Contributors</info>', '<info>Nr of PR</info>','','','','','','','','','','','','',''];
            $rows[] = new TableSeparator();
            $rows[] = [
                $contributorsIssues->author->login,
                $contributorsIssues->numberOfIssues
            ];
            $rows[] = new TableSeparator();
            $rows[] = [
                '<info>Repository</info>',
                '<info>PR number</info>',
                '<info>PR link</info>',
                '<info>Bug VS Imp.</info>',
                '<info>Status</info>',
                '<info>Branch</info>',
                '<info>Created At</info>',
                '<info>Closed At</info>',
                '<info>Merged</info>',
                '<info>Author</info>',
                '<info>Issue Nr</info>',
                '<info>Issue link</info>',
                '<info>Severity</info>',
                '<info>Issue com.</info>',
                '<info>PR com.</info>',
            ];
            $rows[] = new TableSeparator();
            // issues
            foreach ($this->contributors->getIssue($contributorsIssues->issues, $contributorsIssues->author) as $issue) {
                $this->contributorsPresenterConsole->present($issue);
                $rows[] = $this->serializer->normalize($this->contributorsPresenterConsole->viewModel);
                $rows[] = new TableSeparator();
            }
        }

        $table->setRows($rows);
        $table->render();
        $event = $stopwatch->stop('command');
        $output->writeLn(['', 'Output generated in '.(int) ($event->getDuration() / 1000).'s.']);

         return 0;
    }


}
