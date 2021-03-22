<?php

namespace Console\App\Command;

use Console\App\Service\Github;
use Console\App\Service\PrestaShop\Filter\ReviewFilter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GithubReviewReportCommand extends Command
{
    /**
     * @see https://docs.github.com/en/graphql/reference/objects#user field contributionsCollection
     */
    private const PRESTASHOP_ORGANIZATION_ID = 'MDEyOk9yZ2FuaXphdGlvbjI4MTU2OTY=';

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
        $reviewsDate = $reviewsDateAuthor = $reviewsAuthor = [];
        $insiders = isset($_ENV['REVIEW_INSIDER']) ? explode(',', $_ENV['REVIEW_INSIDER']) : $this->github->getMaintainers();

        $maintainers = $this->github->getMaintainers();

        foreach ($maintainers as $maintainer) {
            $reviewsFilter = new ReviewFilter(
                $maintainer,
                \DateTime::createFromFormat('Y-m-d', $this->dateStart),
                \DateTime::createFromFormat('Y-m-d', $this->dateEnd)
            );
            $reviewContributionsByOrganization = $this->github->getReviewsContributions(static::PRESTASHOP_ORGANIZATION_ID, $reviewsFilter);

            if ($input->getOption('byDate')) {
                // Review by date
                if (!in_array($maintainer, $reviewsDateAuthor)) {
                    $reviewsDateAuthor[] = $maintainer;
                }
                $reviewsDate = $reviewContributionsByOrganization->getReviewsByDate();
            } else {
                // Review by author
                if (!isset($reviewsAuthor[$maintainer])) {
                    $reviewsAuthor[$maintainer] = $reviewContributionsByOrganization->getTotalReviews($insiders);
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
