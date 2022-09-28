<?php

declare(strict_types=1);

namespace App\Command;

use App\Presenter\CqrsEndpoints\Console\CqrsEndpointsPresenterConsole;
use App\Service\Command\CqrsEndpoints;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class CqrsEndpointsCommand extends Command
{
    private CqrsEndpoints $cqrsEndpoints;
    private SerializerInterface $serializer;
    private CqrsEndpointsPresenterConsole $cqrsEndpointsPresenterConsole;

    public function __construct(
        CqrsEndpoints $cqrsEndpoints,
        SerializerInterface $serializer,
        CqrsEndpointsPresenterConsole $cqrsEndpointsPresenterConsole
    ) {
        parent::__construct();
        $this->cqrsEndpoints = $cqrsEndpoints;
        $this->serializer = $serializer;
        $this->cqrsEndpointsPresenterConsole = $cqrsEndpointsPresenterConsole;
    }

    protected function configure()
    {
        $this->setName('presthubot:cqrs:endpoints')
            ->setDescription('Get list of cqrs endpoints')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN'] ?? null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('command');
        $io = new SymfonyStyle($input, $output);
        $table = $io->createTable();
        $table->setStyle((new TableStyle())->setVerticalBorderChars('|'));
        $rows[] = [new TableCell('<info> CQRS Endpoints</info>', ['colspan' => 3])];
        $rows[] = new TableSeparator();

        foreach ($this->cqrsEndpoints->getEndpoints() as $row) {
            $this->cqrsEndpointsPresenterConsole->present($row);
            $rows[] = $this->serializer->normalize($this->cqrsEndpointsPresenterConsole->viewModel);
            $rows[] = new TableSeparator();
        }

        $table->setRows($rows);

        $table->setStyle('box-double');
        $table->render();

        $table->render();
        $event = $stopwatch->stop('command');
        $output->writeLn(['', 'Output generated in '.(int) ($event->getDuration() / 1000).'s.']);

        return 0;
    }
}
