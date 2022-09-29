<?php

namespace App\Service\PrestaShop;

class ModuleGlobalStatisticsDTO
{
    public const RATING_BRANCH_MAX = 3;
    public const RATING_DESCRIPTION_MAX = 1;
    public const RATING_FILES_MAX = 27;
    public const RATING_GLOBAL_MAX = 43;
    public const RATING_ISSUES_MAX = 1;
    public const RATING_LABELS_MAX = 8;
    public const RATING_LICENSE_MAX = 1;
    public const RATING_TOPICS_MAX = 2;

    public int $all = 0;
    public int $branch = 0;
    public int $description = 0;
    public int $files = 0;
    public int $issues = 0;
    public int $labels = 0;
    public int $license = 0;
    public int $topics = 0;
}
