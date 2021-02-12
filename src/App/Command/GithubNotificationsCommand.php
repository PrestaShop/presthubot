<?php

namespace Console\App\Command;

use Console\App\Service\Github;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubNotificationsCommand extends Command
{
    /**
     * @var Github;
     */
    protected $github;

    protected function configure()
    {
        $this->setName('github:notifications')
            ->setDescription('Notifications Github')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN'] ?? null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->github = new Github($input->getOption('ghtoken'));

        $time = time();

        $this->processNotifications($output, $this->github->getClient()->notifications()->all());

        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);
    }

    protected function processNotifications(OutputInterface $output, array $notifications)
    {
        $rows = [];
        $rows[] = ['Repository', 'ID', 'Title', 'Reason'];
        $rows[] = new TableSeparator();
        foreach ($notifications as $notification) {
            $id = str_replace('https://api.github.com/repos/PrestaShop/PrestaShop/issues/', '', $notification['subject']['url']);
            $id = str_replace('https://api.github.com/repos/PrestaShop/PrestaShop/pulls/', '', $id);

            $type = $notification['subject']['type'] == 'PullRequest' ? 'PR' : 'Issue';

            $rows[] = [
                '<href=' . $notification['repository']['html_url'] . '>' . $notification['repository']['name'] . '</>',
                '<href=' . $notification['subject']['url'] . '>#' . $id . '</> (' . $type . ')',
                $notification['subject']['title'],
                $notification['reason'],
            ];
        }

        $table = new Table($output);
        $table->setRows($rows);
        $table->setStyle('box-double');
        $table->render();
    }
}
