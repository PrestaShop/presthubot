<?php

namespace Console\App\Command;

use Console\App\Service\Branch\BranchManager;
use Console\App\Service\Github;
use Console\App\Service\PrestaShop\ModuleChecker;
use Console\App\Service\PrestaShop\ModuleFetcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubModuleMonitorCommand extends Command
{
    /**
     * @var Github;
     */
    protected $github;

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
        $template = file_get_contents(__DIR__.'/../Resources/Template/module_monitor.tpl');

        $tableRows = [];
        $i = 1;


        foreach ($modulesToProcess as $moduleToProcess) {
            $repositoryName = $moduleToProcess;
            $data = $branchManager->getReleaseData($repositoryName);
            $nbCommitsAhead = $data['ahead'];

            $trClass = $this->getClassByNbCommitsAhead($nbCommitsAhead);

            $link = $assignee = '';

            if ($data['pullRequest']) {
                $link = '<a href="'.$data['pullRequest']['link'].'">#PR' . $data['pullRequest']['number'] . '</a>';
                $assignee = $data['pullRequest']['assignee'];
            }

            if ($nbCommitsAhead == 0) {
                $tableRows[] = [
                    'html' => '<tr class="table-success">
              <th scope="row">'.$i.'</th>
              <td><a href="https://github.com/prestashop/'.$repositoryName.'">'.$repositoryName.'</a></td>
              <td>NO</td>
              <td>0</td>
              <td>' . $data['releaseDate'] . '</td>
              <td>' . $link . ' ' . $assignee . '</td>
            </tr>',
                    'ahead' => 0,
                ];
            } else {
                $tableRows[] = [
                    'html' =>'<tr class="'.$trClass.'">
              <th scope="row">'.$i.'</th>
              <td><a href="https://github.com/prestashop/'.$repositoryName.'">'.$repositoryName.'</a></td>
              <td>YES</td>
              <td>' . $data['ahead'] . '</td>
              <td>' . $data['releaseDate'] . '</td>
              <td>' . $link . ' ' . $assignee . '</td>
            </tr>',
                    'ahead' => $data['ahead'],
                ];
            }

            uasort($tableRows, function ($a, $b) {
                if ($a['ahead'] == $b['ahead']) {
                    return 0;
                }

                return ($a['ahead'] > $b['ahead']) ? -1 : 1;
            });

            $tableContent = array_map(function ($row) {
                return $row['html'];
            }, $tableRows);

            $i++;
        }
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

        die('Generated in ' . (microtime(true) - $timeStart) . ' seconds');
    }



    function getModules(): array
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

    function getClassByNbCommitsAhead(int $nbCommitsAhead): string
    {
        switch ($nbCommitsAhead) {
            case ($nbCommitsAhead > 0 && $nbCommitsAhead <= 25):
                $trClass = 'light';
                break;
            case ($nbCommitsAhead > 25 && $nbCommitsAhead <= 100):
                $trClass = 'warning';
                break;
            case ($nbCommitsAhead > 100):
                $trClass = 'danger';
                break;
            default:
                $trClass = 'default';
                break;
        }

        return 'table-' . $trClass;
    }

}
