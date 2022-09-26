<?php

namespace App\Presenter\RepositoryCheck\Console;

use App\Presenter\RepositoryCheck\AbstractRepositoryCheckPresenter;

class RepositoryCheckPresenterConsole extends AbstractRepositoryCheckPresenter
{
    protected const SYMBOL_CHECK = '<info>✓ </info>';
    protected const SYMBOL_FAIL = '<error>✗ </error>';
    protected const SYMBOL_CRLF = PHP_EOL;
}
