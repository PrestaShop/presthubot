<?php

namespace Console\App\Service\Model;

/**
 * @see ReviewContributionsByOrganization
 */
class ReviewContributionsByRepository
{
    /**
     * @var string
     */
    private $repositoryName;

    /**
     * @var ContributionsCollection[]
     */
    private $contributionsCollections = [];

    /**
     * @param string $repositoryName
     */
    public function __construct(string $repositoryName)
    {
        $this->repositoryName = $repositoryName;
    }

    /**
     * @return string
     */
    public function getRepositoryName(): string
    {
        return $this->repositoryName;
    }

    /**
     * @return ContributionsCollection[]
     */
    public function getContributionsCollections(): array
    {
        return $this->contributionsCollections;
    }

    /**
     * @param ContributionsCollection[] $contributionsCollections
     *
     * @return ReviewContributionsByRepository
     */
    public function setContributionsCollections(array $contributionsCollections): self
    {
        $this->contributionsCollections = $contributionsCollections;

        return $this;
    }

    /**
     * @param ContributionsCollection $contributionsCollection
     *
     * @return ReviewContributionsByRepository
     */
    public function addContributionsCollection(ContributionsCollection $contributionsCollection): self
    {
        $this->contributionsCollections[] = $contributionsCollection;

        return $this;
    }
}
