<?php

namespace App\Component;

use App\Presenter\ModuleCheck\ModuleCheckViewModel;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('modulecheckline')]
class ModuleCheckLine
{
    public ModuleCheckViewModel $line;
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
