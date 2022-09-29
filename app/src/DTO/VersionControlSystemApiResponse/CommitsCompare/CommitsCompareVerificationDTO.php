<?php

namespace App\DTO\VersionControlSystemApiResponse\CommitsCompare;

class CommitsCompareVerificationDTO
{
    public bool $verified;
    public string $reason;
    public ?string $signature;
    public ?string $payload;
}
