<?php

namespace App\DTO\VersionControlSystemApiResponse\CqrsEndpoints;

class CqrsEndpointDTO
{
    public function __construct(
        public string $domain,
        public string $type,
        public string $name
    ) {
    }
}
