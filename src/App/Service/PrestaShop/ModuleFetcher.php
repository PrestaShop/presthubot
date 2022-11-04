<?php

namespace Console\App\Service\PrestaShop;

use Console\App\Service\Github\Github;
use Github\Api\Repo;

class ModuleFetcher
{
    /**
     * @var Github
     */
    protected $github;

    public function __construct(Github $github)
    {
        $this->github = $github;
    }

    public function getModules(): array
    {
        /**
         * @var Repo $repository
         */
        $repository = $this->github->getClient()->api('repo');
        $contents = $repository->contents()->show('PrestaShop', 'PrestaShop-modules');

        $modules = [];
        foreach ($contents as $content) {
            if (!empty($content['download_url'])) {
                continue;
            }
            $modules[] = $content['name'];
        }

        return $modules;
    }
}
