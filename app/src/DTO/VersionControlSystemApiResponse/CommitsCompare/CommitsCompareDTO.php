<?php

namespace App\DTO\VersionControlSystemApiResponse\CommitsCompare;

class CommitsCompareDTO
{
    public string $url;
    public string $html_url;
    public string $permalink_url;
    public string $diff_url;
    public string $patch_url;
    public CommitsCompareBaseCommitDTO $base_commit;
    public CommitsCompareBaseCommitDTO $merge_base_commit;
    public string $status;
    public int $ahead_by;
    public int $behind_by;
    public int $total_commits;
    /** @var CommitsCompareBaseCommitDTO[] */
    public array $commits;
    /** @var CommitsCompareFileDTO[] */
    public array $files;

    public function addCommit(CommitsCompareBaseCommitDTO $commit): void
    {
        $this->commits[] = $commit;
    }

    public function addFile(CommitsCompareFileDTO $file): void
    {
        $this->files[] = $file;
    }
}
