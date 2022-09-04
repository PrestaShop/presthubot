<?php

namespace Console\App\Command;

use Console\App\Service\Branch\BranchManager;
use Console\App\Service\Github;
use Github\Api\Repo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubModuleMonitorCommand extends Command
{
    const LEVEL_LIGHT = 'light';
    const LEVEL_WARNING = 'warning';
    const LEVEL_DANGER = 'danger';
    const LEVEL_DEFAULT = 'default';
    const LEVEL_SUCCESS = 'success';
    /**
     * @var Github;
     */
    protected $github;

    public function getHTMLContent(int $i, $repositoryName, $numberOfCommitsAhead, $releaseDate, string $link, $assignee): string
    {
        $trClass = 'table-' . $this->getLevelByNumberOfCommitsAhead($numberOfCommitsAhead);
        $needReleaseText = $numberOfCommitsAhead === 0 ? 'NO' : 'YES';

        return sprintf(
            '<tr class="%s">
              <th scope="row">%d</th>
              <td><a href="https://github.com/prestashop/%s">%s</a></td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s %s</td>
            </tr>',
            $trClass,
            $i,
            $repositoryName,
            $repositoryName,
            $needReleaseText,
            $numberOfCommitsAhead,
            $releaseDate,
            $link,
            $assignee
        );
    }

    public function writeFile(array $tableContent): void
    {
        $template = file_get_contents(__DIR__ . '/../Resources/Template/module_monitor.tpl');

        file_put_contents(
            __DIR__ . '/../../docs/index.html',
            str_replace(
                [
                    '{%%placeholder%%}',
                    '{%%latestUpdateDate%%}',
                ],
                [
                    implode('', $tableContent),
                    date('l, j F Y H:i'),
                ],
                $template
            )
        );
    }

    public function getPullResquestLink(array $pullRequest): string
    {
        return sprintf(
            '<a href="%s">#PR%s</a>',
            $pullRequest['link'],
            $pullRequest['number']
        );
    }

    protected function configure()
    {
        $this->setName('github:module:monitor')
            ->setDescription('Monitor Github Module')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                'Please pass a GitHub token as argument',
                $_ENV['GH_TOKEN'] ?? null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeStart = microtime(true);
        $this->github = new Github($input->getOption('ghtoken'));
        $branchManager = new BranchManager($this->github->getClient());
        $modulesToProcess = $this->getModules();
        $tableRows = [];
        $i = 1;

        foreach ($modulesToProcess as $moduleToProcess) {
            $repositoryName = $moduleToProcess;
            $releaseData = $branchManager->getReleaseData($repositoryName);
            $numberOfCommitsAhead = $releaseData['ahead'];
            $link = '';
            $assignee = '';
            if ($releaseData['pullRequest'] !== null) {
                $link = $this->getPullResquestLink($releaseData['pullRequest']);
                $assignee = $releaseData['pullRequest']['assignee'];
            }
            $tableRows[] = [
                'html' => $this->getHTMLContent($i, $repositoryName, $numberOfCommitsAhead, $releaseData['releaseDate'], $link, $assignee),
                'ahead' => $numberOfCommitsAhead,
            ];
            uasort($tableRows, function ($a, $b) {
                if ($a['ahead'] == $b['ahead']) {
                    return 0;
                }

                return ($a['ahead'] > $b['ahead']) ? -1 : 1;
            });
            $tableContent = array_map(function ($row) {
                return $row['html'];
            }, $tableRows);
            ++$i;
        }
        $this->writeFile($tableContent);
        $output->writeLn(['', 'Output generated in ' . (microtime(true) - $timeStart) . 's.']);

        return 0;
    }

    public function getModules(): array
    {
        /**
         * @var Repo $repository
         */
        $repository = $this->github->getClient()->api('repo');
        $contents = $repository->contents()->show(
            BranchManager::PRESTASHOP_USERNAME,
            'PrestaShop-modules'
        );

        $modules = [];
        foreach ($contents as $content) {
            if (!empty($content['download_url'])) {
                continue;
            }
            $modules[] = $content['name'];
        }

        return $modules;
    }

    public function getLevelByNumberOfCommitsAhead(int $nbCommitsAhead): string
    {
        switch ($nbCommitsAhead) {
            case $nbCommitsAhead === 0:
                return self::LEVEL_SUCCESS;
            case $nbCommitsAhead > 0 && $nbCommitsAhead <= 25:
                return self::LEVEL_LIGHT;
            case $nbCommitsAhead > 25 && $nbCommitsAhead <= 100:
                return self::LEVEL_WARNING;
            case $nbCommitsAhead > 100:
                return self::LEVEL_DANGER;
            default:
                return self::LEVEL_DEFAULT;
        }
    }
}
