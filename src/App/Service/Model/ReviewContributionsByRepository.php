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
    private $contributionsCollections;

    /**
     * @return string
     */
    public function getRepositoryName(): string
    {
        return $this->repositoryName;
    }

    /**
     * @param string $repositoryName
     *
     * @return ReviewContributionsByRepository
     */
    public function setRepositoryName(string $repositoryName): self
    {
        $this->repositoryName = $repositoryName;

        return $this;
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
