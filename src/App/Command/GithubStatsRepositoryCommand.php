<?php
namespace Console\App\Command;

use Console\App\Service\Github;
use Github\ResultPager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
 
class GithubStatsRepositoryCommand extends Command
{
    /**
     * @var Github;
     */
    protected $github;

    protected function configure()
    {
        $this->setName('github:stats:repository')
            ->setDescription('Stats Github Repositories')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN'] ?? null
            )
            ->addOption(
                'repository',
                null,
                InputOption::VALUE_OPTIONAL,
                'Name of the repository'
            )
            ->addOption(
                'pr:date:merged',
                null,
                InputOption::VALUE_OPTIONAL
            )
            ->addOption(
                'pr:date:created',
                null,
                InputOption::VALUE_OPTIONAL
            );   
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->github = new Github($input->getOption('ghtoken'));
        $time = time();

        $repository = $input->getOption('repository');
        if ($repository) {
            $query = 'repo:PrestaShop/' . $repository;
        } else {
            $query = 'org:PrestaShop';
        }
        $dateMerged = $input->getOption('pr:date:merged');
        if ($dateMerged) {
            $query .= ' merged:' . $dateMerged;
        }
        $dateCreated = $input->getOption('pr:date:created');
        if ($dateCreated) {
            $query .= ' created:' . $dateCreated;
        }

        $query .= ' is:pr is:merged';

        $searchApi = $this->github->getClient()->api('search');
        $paginator  = new ResultPager($this->github->getClient());
        $result = $paginator->fetchAll($searchApi, 'issues', [$query]);

        $totalSeconds = 0;
        $arrSeconds = [];
        foreach($result as $item) {
            $closed_at = new \DateTime($item['closed_at']);
            $diff = $closed_at->diff(new \DateTime($item['created_at']));
            $diff = $this->getNumberSecond($diff);
            $totalSeconds += $diff;
            $arrSeconds[] = $diff;
        }
        $totalSeconds /= count($result);
        $totalSeconds = round($totalSeconds, 0, PHP_ROUND_HALF_UP);
        $totalSecondsFormat = $this->formatSeconds($totalSeconds);
        $medianeSeconde = $this->mediane($arrSeconds);
        $medianeSecondeFormat = $this->formatSeconds($medianeSeconde);
        
        $output->writeLn([
            'Num PR Merged : '. count($result),
            'Avg PR Time (Created -> Merged) : '. $totalSecondsFormat,
            'Avg PR Time (Created -> Merged) Mediane : '. $medianeSecondeFormat,
        ]);

        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);
    }

    protected function getNumberSecond(\DateInterval $diff): int
    {
        return $diff->s
            + $diff->i * 60
            + $diff->h * 360
            + $diff->h * 3600
            + $diff->days * 86400;
    }

    protected function formatSeconds(int $seconds) {
        $format = '';
        foreach ([
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
            's' => 1,
        ] as $k => $v) {
            if ($seconds >= $v) {
                $diff = floor($seconds / $v);
                $format .= " $diff " . ($diff > 1 ? $k : substr($k, 0, -1));
                $seconds -= $v * $diff;
            }
        }
        return $format;
    }

    protected function mediane($arr)
    {
        sort($arr);
        $count = count($arr); // total numbers in array
        $middleval = floor(($count-1)/2); // find the middle value, or the lowest middle value
        if ($count % 2) { // odd number, middle is the median
            $median = $arr[$middleval];
        } else { // even number, calculate avg of 2 medians
            $low = $arr[$middleval];
            $high = $arr[$middleval+1];
            $median = (($low+$high)/2);
        }
        return $median;
    }
}
