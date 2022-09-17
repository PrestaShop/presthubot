<?php

namespace App\DTO\VersionControlSystemApiResponse\ModuleCheck;

class ModuleCheckDTO
{
    public string $repositoryName;
    public string $repositoryLink;
    public int $numberOfStargazers;
    public int $numberOfPullRequestOpened;
    public int $descriptionRating;
    public int $numberOfFiles;
    public bool $hasIssueOpened;
    public int $numberIssuesOpened;
    public ?string $license;
    public int $globalRating;
    /**
     * @var array <int, string>
     */
    public array $checkLabels;
    /**
     * @var array <int, string>
     */
    public array $checkBranches;
    /**
     * @var array <int, string>
     */
    public array $checkFiles;
    /**
     * @var array <int, string>
     */
    public array $checkTopics;

    public function __construct(
        string $repositoryName,
        string $repositoryLink,
        int $numberOfStargazers,
        int $numberOfPullRequestOpened,
        int $numberOfFiles,
        bool $hasIssueOpened,
        int $numberIssuesOpened,
        ?string $license,
        array $checkLabels,
        array $checkBranches,
        array $checkFiles,
        array $checkTopics,
        int $descriptionRating,
        int $globalRating
    ) {
        $this->repositoryName = $repositoryName;
        $this->repositoryLink = $repositoryLink;
        $this->numberOfStargazers = $numberOfStargazers;
        $this->numberOfPullRequestOpened = $numberOfPullRequestOpened;
        $this->numberOfFiles = $numberOfFiles;
        $this->hasIssueOpened = $hasIssueOpened;
        $this->numberIssuesOpened = $numberIssuesOpened;
        $this->license = $license;
        $this->checkLabels = $checkLabels;
        $this->checkBranches = $checkBranches;
        $this->checkFiles = $checkFiles;
        $this->checkTopics = $checkTopics;
        $this->descriptionRating = $descriptionRating;
        $this->globalRating = $globalRating;
    }
}
