<?php

namespace App\Presenter\CqrsEndpoints;

class CqrsEndpointViewModel
{
    public function __construct(
        public readonly string $domain,
        public readonly string $type,
        public readonly string $name,
    ) {
    }
}
