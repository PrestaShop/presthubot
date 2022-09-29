<?php

namespace App\DTO\VersionControlSystemApiResponse\Common;

class LicenseDTO
{
    public string $key;
    public string $name;
    public string $spdx_id;
    public ?string $url;
    public string $node_id;
    public ?string $html_url;
}
