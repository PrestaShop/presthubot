<?php

namespace App\DTO\VersionControlSystemApiResponse\CommitsCompare;

class CommitsCompareFileDTO
{
    public string $sha;
    public string $filename;
    public string $status;
    public int $additions;
    public int $deletions;
    public int $changes;
    public string $blob_url;
    public string $raw_url;
    public string $contents_url;
    public string $patch;
}
