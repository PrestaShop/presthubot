<?php

namespace App\Service\Command;

use App\DTO\VersionControlSystemApiResponse\CqrsEndpoints\CqrsEndpointDTO;
use App\Service\Github\GithubApiCache;

class CqrsEndpoints
{
    private const NUMBER_OF_ITEMS_PER_PAGE = 50;
    private const ADAPTER_REPOSITORY_PATH = 'src/Adapter/';
    private const COMMAND_HANDLER = 'CommandHandler';
    private const QUERY_HANDLER = 'QueryHandler';
    private const HANDLER_SUFFIX = 'Handler';
    private GithubApiCache $githubApiCache;

    public function __construct(
        GithubApiCache $githubApiCache
    ) {
        $this->githubApiCache = $githubApiCache;
    }

    public function getEndpoints(): iterable
    {
        $files = [];
        $query = 'filename:*Handler.php+repo:Prestashop/Prestashop';
        $handlers = $this->githubApiCache->getSearchCode($query, self::NUMBER_OF_ITEMS_PER_PAGE, 1);
        $currentItem = 0;
        $selectedItems = 0;
        $numberOfPages = $handlers->total_count / self::NUMBER_OF_ITEMS_PER_PAGE;
        for ($i = 1; $i <= $numberOfPages; ++$i) {
            $handlers = $this->githubApiCache->getSearchCode($query, self::NUMBER_OF_ITEMS_PER_PAGE, $i);
            foreach ($handlers->items as $handler) {
                ++$currentItem;
                $pathName = pathinfo(substr($handler->path, strlen(self::ADAPTER_REPOSITORY_PATH)))['dirname'];
                if (0 === strpos($handler->path, self::ADAPTER_REPOSITORY_PATH)) {
                    if (!isset($files[$pathName])) {
                        $files[$pathName] = [];
                    }
                    ++$selectedItems;
                    $files[$pathName][$handler->name] = [
                        'id' => $currentItem,
                        'name' => $handler->name,
                    ];
                }
            }
        }
        $rows= [];
        ksort($files);
        foreach ($files as $pathName => $path) {
            ksort($path);
            foreach ($path as $file) {
                $fileName = pathinfo($file['name'])['filename'] ?? $file['name'];
                $rows[] = new CqrsEndpointDTO(
                    str_replace([self::COMMAND_HANDLER, self::QUERY_HANDLER], [''], pathinfo($pathName)['dirname']),
                    str_contains($pathName, self::COMMAND_HANDLER) ? 'Command' : 'Query',
                    substr($fileName, 0, strpos($fileName, self::HANDLER_SUFFIX))
                );
            }
        }

        foreach ($rows as $row) {
            yield $row;
        }
    }
}
