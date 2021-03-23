<?php

namespace Console\App\Service\Model;

/**
 * @see ReviewContributionsByOrganization
 */
class ContributionsCollection
{
    /**
     * @var int
     */
    private $total;

    /**
     * @var Contribution[]
     */
    private $contributions = [];

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @param int $total
     *
     * @return ContributionsCollection
     */
    public function setTotal(int $total): self
    {
        $this->total = $total;

        return $this;
    }

    /**
     * @return Contribution[]
     */
    public function getContributions(): array
    {
        return $this->contributions;
    }

    /**
     * @param Contribution[] $contributions
     *
     * @return ContributionsCollection
     */
    public function setContributions(array $contributions): self
    {
        $this->contributions = $contributions;

        return $this;
    }

    /**
     * @param Contribution $contribution
     *
     * @return ContributionsCollection
     */
    public function addContribution(Contribution $contribution): self
    {
        $this->contributions[] = $contribution;

        return $this;
    }
}
