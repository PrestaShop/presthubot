<?php

namespace App\Presenter\RepositoryCheck;

class RepositoryCheckViewModel
{
    public function __construct(
        public readonly string $title,
        public readonly string $stars,
        public readonly string $description,
        public readonly string $issues,
        public readonly string $license,
    ) {
    }
}
