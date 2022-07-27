<?php

declare(strict_types=1);

namespace Console\App\Command;

use Console\App\Service\Github;
use Github\Api\Search;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubCQRSEndpointsCommand extends Command
{
    /**
     * @var Github;
     */
    protected $github;

    private const NUMBER_OF_ITEMS_PER_PAGE = 100;
    private const NUMBER_OF_SECONDS_TO_SLEEP_BETWEEN_TWO_REQUESTS = 10;
    private const ADAPTER_REPOSITORY_PATH = 'src/Adapter/';
    private const COMMAND_HANDLER = 'CommandHandler';
    private const QUERY_HANDLER = 'QueryHandler';
    private const HANDLER_SUFFIX = 'Handler';

    protected function configure()
    {
        $this->setName('github:cqrs:endpoints')
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
        $this->github = new Github($input->getOption('ghtoken'));
        $time = time();
        $this->getCQRSEndpoints($input, $output);
        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);

        return 0;
    }

    private function getCQRSEndpoints(InputInterface $input, OutputInterface $output)
    {
        $rows[] = [new TableCell('<info> CQRS Endpoints</info>', ['colspan' => 2])];
        $rows[] = new TableSeparator();
        $files = [];
        $query = 'filename:*Handler.php+repo:Prestashop/Prestashop';

        $api = new Search($this->github->getClient());
        $api->setPerPage(self::NUMBER_OF_ITEMS_PER_PAGE);
        $handlers = $api->code($query);
        $currentItem = 0;
        $selectedItems = 0;
        $numberOfPages = $handlers['total_count'] / self::NUMBER_OF_ITEMS_PER_PAGE;
        for ($i = 0; $i < $numberOfPages; ++$i) {
            foreach ($handlers['items'] as $handler) {
                ++$currentItem;
                $pathName = pathinfo(substr($handler['path'], strlen(self::ADAPTER_REPOSITORY_PATH)))['dirname'];
                if (strpos($handler['path'], self::ADAPTER_REPOSITORY_PATH) === 0) {
                    if (!isset($files[$pathName])) {
                        $files[$pathName] = [];
                    }
                    ++$selectedItems;
                    $files[$pathName][$handler['name']] = [
                        'id' => $currentItem,
                        'name' => $handler['name'],
                    ];
                }
            }
            // it stinks, but needed to not exceed quota on github API (https://docs.github.com/en/rest/rate-limit)
            sleep(self::NUMBER_OF_SECONDS_TO_SLEEP_BETWEEN_TWO_REQUESTS);
            $api->setPage($api->getPage() + 1);
            $handlers = $api->code($query);
        }

        ksort($files);
        foreach ($files as $pathName => $path) {
            $rows[] = [
                    new TableCell(
                        sprintf(
                            '<question> %s </question>',
                            str_replace([self::COMMAND_HANDLER, self::QUERY_HANDLER], [''], pathinfo($pathName)['dirname'])
                        ),
                        ['colspan' => 2]
                    ),
                ];
            ksort($path);
            foreach ($path as $file) {
                $fileName = pathinfo($file['name'])['filename'] ?? $file['name'];
                $rows[] = [
                    str_contains($pathName, self::COMMAND_HANDLER) ? '<comment> Command </comment>' : '<comment> Query </comment>',
                    substr($fileName, 0, strpos($fileName, self::HANDLER_SUFFIX)),
                ];
            }
        }
        $rows[] = [new TableCell(sprintf('<info> %s handlers found </info>', $selectedItems), ['colspan' => 2])];
        $table = new Table($output);
        $table->setRows($rows);
        $table->setStyle('box-double');
        $table->render();
    }
}
