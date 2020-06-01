<?php

namespace Console\App\Service;

use Console\App\Service\Github\Filters;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Github\Client;

class Github
{
    /**
     * @var Client;
     */
    protected $client;

    private const MAINTAINER_MEMBERS = [
        'atomiix',
        'eternoendless',
        'jolelievre',
        'matks',
        'matthieu-rolland',
        'NeOMakinG',
        'PierreRambaud',
        'Progi1984',
        'Quetzacoalt91',
        'rokaszygmantas',
        'sowbiba',
    ];

    public function __construct(string $ghToken = null)
    {
        $filesystemAdapter = new Local(__DIR__ . '/../../../var/');
        $filesystem = new Filesystem($filesystemAdapter);
        $pool = new FilesystemCachePool($filesystem);

        $this->client = new Client();
        $this->client->addCache($pool);

        if (!empty($ghToken)) {
            $this->client->authenticate($ghToken, null, Client::AUTH_URL_TOKEN);
        }
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function search(Github\Query $graphQLQuery): array
    {
        $result = [];
        do {
            $resultPage = $this->client->api('graphql')->execute((string) $graphQLQuery, []);
            $result = array_merge($result, $resultPage['data']['search']['edges']);
            if (!empty($resultPage['data']['search']['pageInfo']['endCursor'])) {
                $graphQLQuery->setPageAfter($resultPage['data']['search']['pageInfo']['endCursor']); 
            }
        } while($resultPage['data']['search']['pageInfo']['hasNextPage']);
        return $result;
    }

    public function countRepoFiles(string $org, string $repository, string $path = null): int
    {
        $numFiles = 0;

        $arrayPath = $this->client->api('repo')->contents()->show($org, $repository, $path);
        foreach($arrayPath as $itemPath) {
            if ($itemPath['type'] == 'file') {
                $numFiles += 1;
                continue;
            }
            if ($itemPath['type'] == 'dir') {
                $numFiles += $this->countRepoFiles($org, $repository, $itemPath['path']);
                continue;
            }
        }
        return $numFiles;
    }

    public function getLinkedIssue(array $pullRequest)
    {
        // Linked Issue
        preg_match('#Fixes\s\#([0-9]{1,5})#', $pullRequest['body'], $matches);
        $issueId = !empty($matches) && !empty($matches[1]) ? $matches[1] : null;
        if (empty($issueId)) {
            preg_match('#Fixes\sissue\s\#([0-9]{1,5})#', $pullRequest['body'], $matches);
            $issueId = !empty($matches) && !empty($matches[1]) ? $matches[1] : null;
        }
        if (empty($issueId)) {
            preg_match('#Fixes\shttps:\/\/github.com\/PrestaShop\/PrestaShop\/issues\/([0-9]{1,5})#', $pullRequest['body'], $matches);
            $issueId = !empty($matches) && !empty($matches[1]) ? $matches[1] : null;
        }
        $issue = is_null($issueId) ? null : $this->client->api('issue')->show('PrestaShop', 'PrestaShop', $issueId);

        // API Alert
        if (isset($pullRequest['_links'])) {
            var_dump('PR #'.$pullRequest['number'].' has _links in its API');
        }

        return $issue;
    }

    public function getRepoTopics(string $org, string $repository): array
    {
        $query = '{
            repository(owner: "'.$org.'", name: "'.$repository.'") {
              repositoryTopics(first: 10) {
                edges {
                  node {
                    topic {
                      name
                    }
                  }
                }
              }
            }
          }';

        $repositoryInfoGraphQL = $this->client->api('graphql')->execute($query, []);
        $topics = [];
        foreach($repositoryInfoGraphQL['data']['repository']['repositoryTopics']['edges'] as $edge) {
            $topics[] = $edge['node']['topic']['name'];
        }
        return $topics;
    }

    public function isPRValid(array $pullRequest, Filters $filters): bool
    {
        // FIX : Some merged PR are displayed in open search
        if ($pullRequest['merged']) {
            return false;
        }

        // Filter Author
        if ($filters->hasFilter(Filters::FILTER_AUTHOR)) {
            if ($filters->isFilterIncluded(Filters::FILTER_AUTHOR) 
                && !in_array($pullRequest['author']['login'], $filters->getFilterData(Filters::FILTER_AUTHOR))) {
                return false;
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_AUTHOR) 
                && in_array($pullRequest['author']['login'], $filters->getFilterData(Filters::FILTER_AUTHOR))) {
                return false;
            }
        }

        // Filter File Extensions
        if ($filters->hasFilter(Filters::FILTER_FILE_EXTENSION)) {
            $countFilesType = $this->countFileType($pullRequest);
            if ($filters->isFilterIncluded(Filters::FILTER_FILE_EXTENSION)) {
                foreach ($filters->getFilterData(Filters::FILTER_FILE_EXTENSION) as $fileExt) {
                    if (!in_array($fileExt, array_keys($countFilesType))) {
                        return false;
                    }
                }
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_FILE_EXTENSION)) {
                foreach ($filters->getFilterData(Filters::FILTER_FILE_EXTENSION) as $fileExt) {
                    if (in_array($fileExt, array_keys($countFilesType))) {
                        return false;
                    }
                }
            }
        }

        // Filter Num Approved
        if ($filters->hasFilter(Filters::FILTER_NUM_APPROVED)) {
            if ($filters->isFilterIncluded(Filters::FILTER_NUM_APPROVED) 
                && !in_array(count($pullRequest['approved']), $filters->getFilterData(Filters::FILTER_NUM_APPROVED), true)) {
                return false;
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_NUM_APPROVED) 
                && in_array(count($pullRequest['approved']), $filters->getFilterData(Filters::FILTER_NUM_APPROVED), true)) {
                return false;
            }
        }

        // Filter Reviewer
        if ($filters->hasFilter(Filters::FILTER_REVIEWER)) {
            if ($filters->isFilterIncluded(Filters::FILTER_REVIEWER)) {
                foreach ($filters->getFilterData(Filters::FILTER_REVIEWER) as $reviewer) {
                    if (!in_array($reviewer, $pullRequest['approved'])) {
                        return false;
                    }
                }
            }
            if (!$filters->isFilterIncluded(Filters::FILTER_REVIEWER)) {
                foreach ($filters->getFilterData(Filters::FILTER_REVIEWER) as $reviewer) {
                    if (in_array($reviewer, $pullRequest['approved'])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function extractApproved(array $pullRequest): array
    {
        $approved = [];
        foreach($pullRequest['reviews']['nodes'] as $node) {
            $login = $node['author']['login'];
            if (!in_array($login, self::MAINTAINER_MEMBERS)) {
                continue;
            }
            if ($node['state'] == 'APPROVED') {
                if (!in_array($login, $approved)) {
                    $approved[] = $login;
                }
            } else {
                if (in_array($login, $approved)) {
                    $pos = array_search($login, $approved);
                    unset($approved[$pos]);
                }
            }
        }
        return $approved;
    }
}
