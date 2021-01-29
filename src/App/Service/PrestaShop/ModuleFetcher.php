<?php

namespace Console\App\Service\PrestaShop;

use Console\App\Service\Github;

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
        $contents = $this->github->getClient()->api('repo')->contents()->show('PrestaShop', 'PrestaShop-modules');

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
