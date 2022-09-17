<?php

namespace App\Command;

use App\Service\Github\Branch;
use App\Service\Github\Github;
use App\Service\Github\GithubApiCache;

use function PHPUnit\Framework\throwException;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubModuleMonitorCommand extends Command
{
    public const LEVEL_LIGHT = 'light';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_DANGER = 'danger';
    public const LEVEL_DEFAULT = 'default';
    public const LEVEL_SUCCESS = 'success';
    private string $projectDirectory;

    public function __construct(
        GithubApiCache $githubApiCache,
        string $projectDirectory,
        string $name = null
    ) {
        $this->githubApiCache = $githubApiCache;
        parent::__construct($name);
        $this->projectDirectory = $projectDirectory;
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

    public function getHTMLContent(int $i, $repositoryName, $numberOfCommitsAhead, $releaseDate, string $link, $assignee): string
    {
        $trClass = 'table-'.$this->getLevelByNumberOfCommitsAhead($numberOfCommitsAhead);
        $needReleaseText = 0 === $numberOfCommitsAhead ? 'NO' : 'YES';

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
        $template = file_get_contents($this->projectDirectory.'/resources/Template/module_monitor.tpl');

        file_put_contents(
            $this->projectDirectory.'/public/docs/module_monitor.html',
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeStart = microtime(true);
        $this->githubApiCache->setGithub(new Github($input->getOption('ghtoken')));

        /*        try { */
        $branchManager = new Branch($this->githubApiCache);
        $modulesToProcess = $this->getModules();
        $tableRows = [];
        $i = 1;
        $tableContent = [];
        foreach ($modulesToProcess as $moduleToProcess) {
            $repositoryName = $moduleToProcess;
            $releaseData = $branchManager->getReleaseData($repositoryName);
            $numberOfCommitsAhead = $releaseData['ahead'];
            $link = '';
            $assignee = '';
            if (null !== $releaseData['pullRequest']) {
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

        /*} catch (\Exception $exception) {
            dd('test');
            throwException($exception);
            //$output->writeLn(['', '<error>' . $exception->getMessage() . '</error>'], );
        }*/
        $output->writeLn(['', 'Output generated in '.(microtime(true) - $timeStart).'s.']);

        return 0;
    }

    public function getModules(): array
    {
        $contents = $this->githubApiCache->getRepoEndpointContentsShow(
            Branch::PRESTASHOP_USERNAME,
            'PrestaShop-modules'
        );

        $modules = [];
        foreach ($contents->contents as $content) {
            if (!empty($content->download_url)) {
                continue;
            }
            $modules[] = $content->name;
        }

        return $modules;
    }

    public function getLevelByNumberOfCommitsAhead(int $nbCommitsAhead): string
    {
        switch ($nbCommitsAhead) {
            case 0 === $nbCommitsAhead:
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
