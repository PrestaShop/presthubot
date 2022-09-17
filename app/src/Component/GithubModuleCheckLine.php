<?php

namespace App\Component;

use App\Presenter\ModuleCheck\Web\ModuleCheckWebViewModel;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('githubmodulecheckline')]
class GithubModuleCheckLine
{
    public ModuleCheckWebViewModel $line;
    public int $lineNumber;

    public function getConstValue(string $type): int
    {
        return match ($type) {
            'CHECK_FILES_EXIST' => 1,
            'CHECK_FILES_CONTAIN' => 2,
            'CHECK_FILES_TEMPLATE' => 3,
            'CHECK_COMPOSER_VALID' => 4,
        };
    }
}
