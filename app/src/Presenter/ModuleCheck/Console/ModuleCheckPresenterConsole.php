<?php

namespace App\Presenter\ModuleCheck\Console;

use App\Presenter\ModuleCheck\AbstractModuleCheckPresenter;

class ModuleCheckPresenterConsole extends AbstractModuleCheckPresenter
{
    protected const SYMBOL_CHECK = '<info>✓ </info>';
    protected const SYMBOL_FAIL = '<error>✗ </error>';
    protected const SYMBOL_CRLF = PHP_EOL;
}
