<?php

namespace App\Service\PrestaShop;

class ModuleRatesDTO
{
    public const RATING_BRANCH = 'rating_branch';
    public const RATING_DESCRIPTION = 'rating_description';
    public const RATING_FILES = 'rating_files';
    public const RATING_GLOBAL = 'rating_global';
    public const RATING_ISSUES = 'rating_issues';
    public const RATING_LABELS = 'rating_labels';
    public const RATING_LICENSE = 'rating_license';
    public const RATING_TOPICS = 'rating_topics';

    public int $rating_branch = 0;
    public int $rating_description = 0;
    public int $rating_files = 0;
    public int $rating_global = 0;
    public int $rating_issues = 0;
    public int $rating_labels = 0;
    public int $rating_license = 0;
    public int $rating_topics = 0;

    public function getSum(): int
    {
        return $this->rating_topics + $this->rating_files + $this->rating_branch + $this->rating_labels + $this->rating_license + $this->rating_description + $this->rating_issues;
    }
}
