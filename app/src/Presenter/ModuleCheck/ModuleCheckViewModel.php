<?php

namespace App\Presenter\ModuleCheck;

class ModuleCheckViewModel
{
    public function __construct(
        public readonly string $title,
        public readonly string $kpis,
        public readonly string $issues,
        public readonly string $description,
        public readonly string $license,
        public readonly string $labels,
        public readonly string $branchDev,
        public readonly string $files,
        public readonly string $topics,
        public readonly string $percentage,
    ) {
    }
}
