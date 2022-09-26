<?php

namespace App\Presenter\RepositoryCheck\Web;

use App\Presenter\RepositoryCheck\AbstractRepositoryCheckPresenter;

class RepositoryCheckPresenterWeb extends AbstractRepositoryCheckPresenter
{
    protected const SYMBOL_CHECK = '<i class="fa fa-check-circle-o text-success" >&nbsp;</i>';
    protected const SYMBOL_FAIL = '<i class="fa fa-exclamation-circle text-warning" >&nbsp;</i>';
    protected const SYMBOL_CRLF = '<br/>';
}
