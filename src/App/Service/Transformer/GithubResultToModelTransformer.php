<?php

namespace Console\App\Service\Transformer;

use Console\App\Service\Model\Contribution;
use Console\App\Service\Model\ContributionsCollection;
use Console\App\Service\Model\ReviewContributionsByOrganization;
use Console\App\Service\Model\ReviewContributionsByRepository;

class GithubResultToModelTransformer
{
    public function transformContributionsToModel(string $reviewer, array $pullRequestReviewContributionsByRepository): ReviewContributionsByOrganization
    {
        $reviewContributionsByOrganization = new ReviewContributionsByOrganization($reviewer);

        foreach ($pullRequestReviewContributionsByRepository as $pullRequestReviewContributionByRepository) {
            $reviewContributionsByRepository = new ReviewContributionsByRepository();

            $reviewContributionsByRepository->setRepositoryName(
                $pullRequestReviewContributionByRepository['repository']['name']
            );

            $contributionsCollection = new ContributionsCollection();
            $contributionsCollection->setTotal($pullRequestReviewContributionByRepository['contributions']['totalCount']);
            foreach ($pullRequestReviewContributionByRepository['contributions']['nodes'] as $pullRequestReviewContribution) {
                $contribution = new Contribution();
                $contribution
                    ->setOccurredAt(\DateTime::createFromFormat(\DateTime::ISO8601, $pullRequestReviewContribution['occurredAt']))
                    ->setState($pullRequestReviewContribution['pullRequestReview']['state'])
                    ->setPullRequestAuthor($pullRequestReviewContribution['pullRequestReview']['pullRequest']['author']['login']);

                $contributionsCollection->addContribution($contribution);
            }
            $reviewContributionsByRepository->addContributionsCollection($contributionsCollection);

            $reviewContributionsByOrganization->addReviewContributionsByRepository($reviewContributionsByRepository);
        }

        return $reviewContributionsByOrganization;
    }
}
