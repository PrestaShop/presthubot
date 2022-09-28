<?php

namespace App\Command;

use App\DTO\VersionControlSystemApiResponse\ModuleCheck\ModuleCheckDTO;
use App\Presenter\ModuleCheck\Console\ModuleCheckPresenterConsole;
use App\Service\Command\CheckModule;
use App\Service\PrestaShop\ModuleGlobalStatisticsDTO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class ModuleCheckCommand extends Command
{
    private CheckModule $checkModule;
    private ModuleCheckPresenterConsole $moduleCheckConsolePresenter;
    private SerializerInterface $serializer;

    public function __construct(
        CheckModule $checkModule,
        ModuleCheckPresenterConsole $githubModuleCheckConsolePresenter,
        SerializerInterface $serializer
    ) {
        $this->checkModule = $checkModule;
        $this->moduleCheckConsolePresenter = $githubModuleCheckConsolePresenter;
        $this->serializer = $serializer;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('presthubot:module:check')
            ->setDescription('Check Github Module')
            ->addOption(
                'module',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                null
            )
            ->addOption(
                'branch',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                'master'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                null
            )
            ->addOption(
                'from',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                '1'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('command');

        $module = $input->getOption('module');
        $numberOfItems = $input->getOption('limit');
        $numberOfRepositories = null != $numberOfItems ?: $this->checkModule->getNumberOfRepositories($module);

        $io = new SymfonyStyle($input, $output);
        $repositories = $this->checkModule->getCheckedRepositories(
            $module,
            $input->getOption('branch'),
            $input->getOption('from'),
            $numberOfItems,
        );

        $table = $io->createTable();
        $table->setStyle((new TableStyle())->setVerticalBorderChars('|'));
        $table->setHeaders([
            'Title',
            'Kpis',
            'Issues',
            'Desc. rating',
            'License',
            'Labels',
            'Branch dev',
            'Files',
            'GH Topics',
            '%',
        ]);
        /**
         * @var ModuleCheckDTO $repository
         */
        foreach ($io->progressIterate($repositories, $numberOfRepositories) as $repository) {
            if (null !== $repository) {
                $this->moduleCheckConsolePresenter->present($repository);
                $table->addRow($this->serializer->normalize($this->moduleCheckConsolePresenter->viewModel));
                $table->addRow(new TableSeparator());
            }
        }

        $table->addRow([
            'Total : '.$numberOfRepositories,
            '',
            '✓ '.$this->getFormattedStat($this->checkModule->getStatistics()->issues, ModuleGlobalStatisticsDTO::RATING_ISSUES_MAX, $numberOfRepositories),
            '✓ '.$this->getFormattedStat($this->checkModule->getStatistics()->description, ModuleGlobalStatisticsDTO::RATING_DESCRIPTION_MAX, $numberOfRepositories),
            '✓ '.$this->getFormattedStat($this->checkModule->getStatistics()->license, ModuleGlobalStatisticsDTO::RATING_LICENSE_MAX, $numberOfRepositories),
            '✓ '.$this->getFormattedStat($this->checkModule->getStatistics()->labels, ModuleGlobalStatisticsDTO::RATING_LABELS_MAX, $numberOfRepositories),
            '✓ '.$this->getFormattedStat($this->checkModule->getStatistics()->branch, ModuleGlobalStatisticsDTO::RATING_BRANCH_MAX, $numberOfRepositories),
            '✓ '.$this->getFormattedStat($this->checkModule->getStatistics()->files, ModuleGlobalStatisticsDTO::RATING_FILES_MAX, $numberOfRepositories),
            '✓ '.$this->getFormattedStat($this->checkModule->getStatistics()->topics, ModuleGlobalStatisticsDTO::RATING_TOPICS_MAX, $numberOfRepositories),
            '✓ '.$this->getFormattedStat($this->checkModule->getStatistics()->all, ModuleGlobalStatisticsDTO::RATING_GLOBAL_MAX, $numberOfRepositories),
        ]);

        $table->render();
        $event = $stopwatch->stop('command');
        $output->writeLn(['', 'Output generated in '.(int) ($event->getDuration() / 1000).'s.']);

        return 0;
    }

    private function getFormattedStat(int $value, string $maximum, int $numberOfRepositories): string
    {
        return number_format((($value / $maximum) / $numberOfRepositories) * 100, 2).'%';
    }
}
