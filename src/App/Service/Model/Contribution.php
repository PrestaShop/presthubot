<?php

namespace Console\App\Service\Model;

use Datetime;

/**
 * @see ReviewContributionsByOrganization
 */
class Contribution
{
    /**
     * @var Datetime
     */
    private $occurredAt;

    /**
     * @var string
     */
    private $state;

    /**
     * @var string
     */
    private $pullRequestAuthor;

    /**
     * @return Datetime
     */
    public function getOccurredAt(): Datetime
    {
        return $this->occurredAt;
    }

    /**
     * @param Datetime $occurredAt
     *
     * @return Contribution
     */
    public function setOccurredAt(Datetime $occurredAt): self
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @param string $state
     *
     * @return Contribution
     */
    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return string
     */
    public function getPullRequestAuthor(): string
    {
        return $this->pullRequestAuthor;
    }

    /**
     * @param string $pullRequestAuthor
     *
     * @return Contribution
     */
    public function setPullRequestAuthor(string $pullRequestAuthor): self
    {
        $this->pullRequestAuthor = $pullRequestAuthor;

        return $this;
    }
}
