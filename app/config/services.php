<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('github.maintainers', [
                'atomiix',
                'eternoendless',
                'jolelievre',
                'kpodemski',
                'matks',
                'matthieu-rolland',
                'NeOMakinG',
                'PululuK',
                'sowbiba',
            ]
        )
        ->set('github.ignored_repositories', [
            'PrestaShop-1.6',
        ]);
    $services = $container->services()
        ->defaults()
        ->autowire(true)
        ->autoconfigure(true)
        ->bind('$projectDirectory', param('kernel.project_dir'))
        ->bind('$numberOfHourCacheValidity', env('CACHE_TIME_VALIDITY'))
        ->bind('$githubMaintainers', param('github.maintainers'))
        ->bind('$githubIgnoredRepositories', param('github.ignored_repositories'))
        ->bind('$githubToken', env('GH_TOKEN'));
    $services->load('App\\', '../src/*')
        ->exclude([
                '../src/DependencyInjection',
                '../src/Entity',
                '../src/Migrations',
                '../src/Tests',
                '../src/Kernel.php',
            ]
        );
};
