<?php

namespace Console\App\Service\Model;

/**
 * This class is a representation of the result of Github::getReviewsContributions
 *
 * The structure of the array represented is
 *
 * [
 *      "contributions" => [
 *          "totalCount" => int,
 *          "nodes" => [
 *              [
 *                  "occurredAt" => "2021-03-11T10:29:58Z",
 *                  "pullRequestReview" => [
 *                      "state" => string,
 *                      "pullRequest" => [
 *                          "author" => [
 *                              "login" => string
 *                          ],
 *                      ],
 *                  ],
 *              ],
 *              ...
 *      ],
 *      ...
 * ]
 */
class ReviewContributionsByOrganization
{
    /**
     * @var string
     */
    private $reviewer;

    /**
     * @var ReviewContributionsByRepository[]
     */
    private $reviewContributionsByRepositories;

    /**
     * @return string
     */
    public function getReviewer(): string
    {
        return $this->reviewer;
    }

    /**
     * @param string $reviewer
     *
     * @return ReviewContributionsByOrganization
     */
    public function setReviewer(string $reviewer): self
    {
        $this->reviewer = $reviewer;

        return $this;
    }

    /**
     * @return ReviewContributionsByRepository[]
     */
    public function getReviewContributionsByRepositories(): array
    {
        return $this->reviewContributionsByRepositories;
    }

    /**
     * @param ReviewContributionsByRepository[] $reviewContributionsByRepositories
     *
     * @return ReviewContributionsByOrganization
     */
    public function setReviewContributionsByRepositories(array $reviewContributionsByRepositories): self
    {
        $this->reviewContributionsByRepositories = $reviewContributionsByRepositories;

        return $this;
    }

    /**
     * @param ReviewContributionsByRepository $reviewContributionsByRepository
     *
     * @return ReviewContributionsByOrganization
     */
    public function addReviewContributionsByRepository(ReviewContributionsByRepository $reviewContributionsByRepository): self
    {
        $this->reviewContributionsByRepositories[] = $reviewContributionsByRepository;

        return $this;
    }

    /**
     * @param string $repository
     *
     * @return ReviewContributionsByOrganization
     */
    public function filterByRepository(string $repository): self
    {
        foreach ($this->reviewContributionsByRepositories as $i => $reviewContributionsByRepository) {
            if ($repository !== $reviewContributionsByRepository->getRepositoryName()) {
                unset($this->reviewContributionsByRepositories[$i]);
            }
        }

        return $this;
    }

    /**
     * @param array $insiders
     *
     * @return int[]
     *
     * Existing states are :
     *
     * APPROVED : A review allowing the pull request to merge.
     * CHANGES_REQUESTED : A review blocking the pull request from merging.
     * COMMENTED : An informational review.
     * DISMISSED : A review that has been dismissed.
     * PENDING : A review that has not yet been submitted.
     */
    public function getTotalReviews(array $insiders): array
    {
        $total = [
            'ALL' => 0,
            'COMMENTED' => 0,
            'APPROVED' => 0,
            'CHANGES_REQUESTED' => 0,
            'INSIDE' => 0,
            'OUTSIDE' => 0,
        ];
        foreach ($this->reviewContributionsByRepositories as $i => $reviewContributionsByRepository) {
            foreach ($reviewContributionsByRepository->getContributionsCollections() as $contributionsCollection) {
                foreach ($contributionsCollection->getContributions() as $contribution) {
                    // shouldn't we count them ?
                    if ('DISMISSED' === $contribution->getState()) {
                        continue;
                    }

                    ++$total['ALL'];

                    switch ($contribution->getState()) {
                        case 'APPROVED':
                            ++$total['APPROVED'];
                            break;
                        case 'CHANGES_REQUESTED':
                            ++$total['CHANGES_REQUESTED'];
                            break;
                        case 'COMMENTED':
                            ++$total['COMMENTED'];
                            break;
                        default:
                            var_dump($contribution->getState());
                            break;
                    }

                    if (in_array($contribution->getPullRequestAuthor(), $insiders)) {
                        ++$total['INSIDE'];
                    } else {
                        ++$total['OUTSIDE'];
                    }
                }
            }
        }

        return $total;
    }

    /**
     * @return array
     */
    public function getReviewsByDate(): array
    {
        $reviewsByDate = [];

        foreach ($this->reviewContributionsByRepositories as $i => $reviewContributionsByRepository) {
            foreach ($reviewContributionsByRepository->getContributionsCollections() as $contributionsCollection) {
                foreach ($contributionsCollection->getContributions() as $contribution) {
                    $date = $contribution->getOccurredAt()->format('Y-m-d');

                    if (!isset($reviewsByDate[$date])) {
                        $reviewsByDate[$date] = [];
                    }

                    if (!isset($reviewsByDate[$date][$this->reviewer])) {
                        $reviewsByDate[$date][$this->reviewer] = 0;
                    }

                    ++$reviewsByDate[$date][$this->reviewer];
                }
            }
        }

        return $reviewsByDate;
    }
}
