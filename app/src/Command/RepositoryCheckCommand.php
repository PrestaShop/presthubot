<?php

namespace App\Command;

use App\Presenter\RepositoryCheck\Console\RepositoryCheckPresenterConsole;
use App\Service\Command\CheckRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class RepositoryCheckCommand extends Command
{
    private CheckRepository $checkRepository;
    private RepositoryCheckPresenterConsole $repositoryCheckPresenterConsole;
    private SerializerInterface $serializer;

    public function __construct(
        CheckRepository $checkRepository,
        RepositoryCheckPresenterConsole $repositoryCheckPresenterConsole,
        SerializerInterface $serializer
    ) {
        parent::__construct();
        $this->checkRepository = $checkRepository;
        $this->repositoryCheckPresenterConsole = $repositoryCheckPresenterConsole;
        $this->serializer = $serializer;
    }

    public function getType(?string $onlyPublic, ?string $onlyPrivate): ?string
    {
        $type = null;
        if (($onlyPublic && $onlyPrivate) || (!$onlyPublic && !$onlyPrivate)) {
            $type = 'all';
        } elseif ($onlyPrivate) {
            $type = 'private';
        } elseif ($onlyPublic) {
            $type = 'public';
        }

        return $type;
    }

    protected function configure()
    {
        $this->setName('github:repository:check')
            ->setDescription('Check Github Repositories')
            ->addOption(
                'public',
                null,
                InputOption::VALUE_NONE,
                'Only public repositories'
            )
            ->addOption(
                'private',
                null,
                InputOption::VALUE_NONE,
                'Only private repositories'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $onlyPublic = $input->getOption('public');
        $onlyPrivate = $input->getOption('private');
        $stopwatch = new Stopwatch();
        $io = new SymfonyStyle($input, $output);
        $table = $io->createTable();
        $table->setStyle((new TableStyle())->setVerticalBorderChars('|'));
        $table->setHeaders([
            'Title',
            '# Stars',
            'Description',
            'Issues Opened',
            'License',
        ]);
        $type = $this->getType($onlyPublic, $onlyPrivate);
        if (empty($type)) {
            return 1;
        }
        $repositories = $this->checkRepository->getCheckedRepositories('PrestaShop', $type);

        foreach ($io->progressIterate($repositories, $this->checkRepository->getNumberOfRepositories()) as $repository) {
            $this->repositoryCheckPresenterConsole->present($repository);
            $table->addRow($this->serializer->normalize($this->repositoryCheckPresenterConsole->viewModel));
            $table->addRow(new TableSeparator());
        }

        $table->render();
        $event = $stopwatch->stop('command');
        $output->writeLn(['', 'Output generated in '.(int) ($event->getDuration() / 1000).'s.']);

        return 0;
    }
}
