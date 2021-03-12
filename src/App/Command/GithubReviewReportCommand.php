<?php

namespace Console\App\Command;

use Console\App\Service\Github;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubReviewReportCommand extends Command
{
    /**
     * @var Github
     */
    protected $github;
    /**
     * @var string
     */
    protected $dateStart;
    /**
     * @var string
     */
    protected $dateEnd;

    protected function configure()
    {
        $this->setName('github:review:report')
            ->setDescription('')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN'] ?? null
            )
            ->addOption(
                'dateStart',
                null,
                InputOption::VALUE_REQUIRED,
                ''
            )
            ->addOption(
                'dateEnd',
                null,
                InputOption::VALUE_OPTIONAL,
                ''
            )
            ->addOption(
                'byDate',
                '0',
                InputOption::VALUE_OPTIONAL,
                ''
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->github = new Github($input->getOption('ghtoken'));

        // Get Stats
        $time = time();

        if ($this->assertInput($input, $output)) {
            $this->generateReport($input, $output);
        }
        $output->writeLn(['', 'Output generated in ' . (time() - $time) . 's.']);

        return 0;
    }

    private function assertInput(InputInterface $input, OutputInterface $output): bool
    {
        if ($input->getOption('dateStart') === null) {
            $output->writeln('<error>Error: Empty parameter dateStart</error>');

            return false;
        }
        $dateStart = strtotime($input->getOption('dateStart'));
        if (date('Y-m-d', $dateStart) != $input->getOption('dateStart')) {
            $output->writeln('<error>Error: Unrecognizable dateStart format : ' . $input->getOption('dateStart') . '</error>');

            return false;
        }
        $this->dateStart = date('Y-m-d', $dateStart);
        if ($input->getOption('dateEnd') !== null) {
            $dateEnd = strtotime($input->getOption('dateEnd'));
            if (date('Y-m-d', $dateEnd) != $input->getOption('dateEnd')) {
                $output->writeln('<error>Error: Unrecognizable dateEnd format : ' . $input->getOption('dateEnd') . '</error>');

                return false;
            }
        } else {
            $dateEnd = time();
        }
        $this->dateEnd = date('Y-m-d', $dateEnd);

        return true;
    }

    private function generateReport(InputInterface $input, OutputInterface $output): void
    {
        $maintainers = $this->github->getMaintainers();

        $reviewsDate = $reviewsDateAuthor = $reviewsAuthor = [];
        $insiders = isset($_ENV['REVIEW_INSIDER']) ? explode(',', $_ENV['REVIEW_INSIDER']) : $maintainers;

        foreach ($maintainers as $maintainer) {
            $pullRequests = $this->github->getReviews('PrestaShop', '', $maintainer);

            if (1000 === count($pullRequests)) {
                $output->writeln("Careful! The result is maybe not complete");
            }
            foreach ($pullRequests as $pullRequest) {
                foreach ($pullRequest['reviews']['edges'] as $review) {
                    $review = $review['node'];
                    $date = date('Y-m-d', strtotime($review['createdAt']));
                    $reviewer = $review['author']['login'];
                    $state = $review['state'];
                    $authorIsInsider = in_array($pullRequest['author']['login'], $insiders);
                    $reviewerIsMaintainer = in_array($reviewer, $maintainers);

                    if ($maintainer !== $reviewer) {
                        continue;
                    }

                    if (!$reviewerIsMaintainer) {
                        continue;
                    }
                    if ($state == 'DISMISSED') {
                        continue;
                    }
                    if ($date < $this->dateStart) {
                        continue;
                    }
                    if ($date > $this->dateEnd) {
                        continue;
                    }

                    if ($input->getOption('byDate')) {
                        // Review by date
                        if (!in_array($reviewer, $reviewsDateAuthor)) {
                            $reviewsDateAuthor[] = $reviewer;
                        }
                        if (!isset($reviewsDate[$date])) {
                            $reviewsDate[$date] = [];
                        }
                        if (!isset($reviewsDate[$date][$reviewer])) {
                            $reviewsDate[$date][$reviewer] = 0;
                        }
                        ++$reviewsDate[$date][$reviewer];
                    } else {
                        // Review by author
                        if (!isset($reviewsAuthor[$reviewer])) {
                            $reviewsAuthor[$reviewer] = [
                                'ALL' => 0,
                                'COMMENTED' => 0,
                                'APPROVED' => 0,
                                'CHANGES_REQUESTED' => 0,
                                'INSIDE' => 0,
                                'OUTSIDE' => 0,
                            ];
                        }
                        ++$reviewsAuthor[$reviewer]['ALL'];
                        ++$reviewsAuthor[$reviewer][$state];
                        ++$reviewsAuthor[$reviewer][$authorIsInsider ? 'INSIDE' : 'OUTSIDE'];
                    }
                }
            }
        }

        $rows = [];
        if ($input->getOption('byDate')) {
            ksort($reviewsDateAuthor, SORT_STRING | SORT_FLAG_CASE);
            ksort($reviewsDate, SORT_STRING | SORT_FLAG_CASE);
            $headers = array_merge(['Date'], $reviewsDateAuthor);
            foreach ($reviewsDate as $date => $reviews) {
                $row = [$date];
                foreach ($reviewsDateAuthor as $author) {
                    $row[] = $reviews[$author] ?? '';
                }
                $rows[] = $row;
            }
        } else {
            ksort($reviewsAuthor, SORT_STRING | SORT_FLAG_CASE);
            $headers = ['Author', '#Reviews', '#Commented', '#Approved', '#ChangesRequested', '#Insiders', '#Outsiders'];
            foreach ($reviewsAuthor as $reviewer => $reviews) {
                $rows[] = array_merge([$reviewer], $reviews);
            }
        }

        $table = new Table($output);
        $table
            ->setHeaders($headers)
            ->setRows($rows)
        ;
        $table->render();
    }
}
