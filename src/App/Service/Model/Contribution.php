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

    public function __construct(Datetime $occurredAt, string $state, string $pullRequestAuthor)
    {
        $this->occurredAt = $occurredAt;
        $this->state = $state;
        $this->pullRequestAuthor = $pullRequestAuthor;
    }

    /**
     * @return Datetime
     */
    public function getOccurredAt(): Datetime
    {
        return $this->occurredAt;
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @return string
     */
    public function getPullRequestAuthor(): string
    {
        return $this->pullRequestAuthor;
    }
}
