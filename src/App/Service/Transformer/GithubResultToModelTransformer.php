<?php

namespace Console\App\Service\Transformer;

use Console\App\Service\Model\Contribution;
use Console\App\Service\Model\ContributionsCollection;
use Console\App\Service\Model\ReviewContributionsByOrganization;
use Console\App\Service\Model\ReviewContributionsByRepository;
use DateTime;

class GithubResultToModelTransformer
{
    /**
     * This method will transform the array of user's review contributions into a ReviewContributionsByOrganization object
     * and create all the nested objects. @see ReviewContributionsByOrganization to review the structure of the array needed
     *
     * @param string $reviewer
     * @param array $pullRequestReviewContributionsByRepository
     *
     * @return ReviewContributionsByOrganization
     */
    public function transformContributionsToModel(string $reviewer, array $pullRequestReviewContributionsByRepository): ReviewContributionsByOrganization
    {
        $reviewContributionsByOrganization = new ReviewContributionsByOrganization($reviewer);

        foreach ($pullRequestReviewContributionsByRepository as $pullRequestReviewContributionByRepository) {
            $reviewContributionsByRepository = new ReviewContributionsByRepository(
                $pullRequestReviewContributionByRepository['repository']['name']
            );

            $contributionsCollection = new ContributionsCollection();
            $contributionsCollection->setTotal($pullRequestReviewContributionByRepository['contributions']['totalCount']);
            foreach ($pullRequestReviewContributionByRepository['contributions']['nodes'] as $pullRequestReviewContribution) {
                $contribution = new Contribution(
                    DateTime::createFromFormat(DateTime::ISO8601, $pullRequestReviewContribution['occurredAt']),
                    $pullRequestReviewContribution['pullRequestReview']['state'],
                    $pullRequestReviewContribution['pullRequestReview']['pullRequest']['author']['login']
                );

                $contributionsCollection->addContribution($contribution);
            }
            $reviewContributionsByRepository->addContributionsCollection($contributionsCollection);

            $reviewContributionsByOrganization->addReviewContributionsByRepository($reviewContributionsByRepository);
        }

        return $reviewContributionsByOrganization;
    }
}
