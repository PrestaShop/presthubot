<?php

namespace Console\App\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;

class TableDrawer
{
    /**
     * @param RepositoryModel[] $dataset
     * @param OutputInterface $output
     * @param int $timestamp
     */
    public function drawResultsAsTable(array $dataset, OutputInterface $output, $timestamp)
    {
        $countStars = $countWDescription = $countIssuesOpened = 0;
        $countWLicense = [];

        $table = new Table($output);
        $table
            ->setStyle('box')
            ->setHeaders([
                'Title',
                '# Stars',
                'Description',
                'Issues Opened',
                'License',
            ]);

        foreach ($dataset as $model) {

            $table->addRows([[
                sprintf(
                    '<href=%s>%s</>',
                    $model->html_url,
                    $model->name
                ),
                $model->stargazers_count,
                !empty($model->description) ? '<info>✓ </info>' : '<error>✗ </error>',
                $model->has_issues ? '<info>✓ </info>' : '<error>✗ </error>',
                $model->license,
            ]]);

            $countStars += $model->stargazers_count;
            $countIssuesOpened += ($model->has_issues ? 1 : 0);
            $countWDescription += (!empty($model->description) ? 1 : 0);

            if (!empty($model->license)) {
                if (!array_key_exists($model->license, $countWLicense)) {
                    $countWLicense[$model->license] = 0;
                }
                $countWLicense[$model->license]++;
            }

            $table->addRows([new TableSeparator()]);
        }

        $licenseCell = '';
        ksort($countWLicense);
        foreach ($countWLicense as $license => $count) {
            $licenseCell .= $license . ' : ' . $count;
            if ($license !== array_key_last($countWLicense)) {
                $licenseCell .= PHP_EOL;
            }
        }

        $table->addRows([[
            'Total : ' . count($dataset),
            'Avg : ' . number_format($countStars / count($dataset), 2),
            'Opened : ' . $countIssuesOpened . PHP_EOL . 'Closed : ' . (count($dataset) - $countIssuesOpened),
            'Num : ' . $countWDescription,
            $licenseCell,
        ]]);

        $table->render();
        $output->writeLn(['', 'Output generated in ' . (time() - $timestamp) . 's.']);
    }
}
