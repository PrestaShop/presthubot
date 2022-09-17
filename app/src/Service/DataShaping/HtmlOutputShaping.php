<?php

namespace App\Service\DataShaping;

class HtmlOutputShaping
{
    public function transformToConsole(string $input): string
    {
        return str_replace(
            [
                '##CHECK##',
                '##FAIL##',
                '##LINEEND##',
                '##LINESTART##',
                '##LFCR##',
            ],
            [
                '<span aria-hidden="true" class="btn-success"><i class="fa fa-check"></i></span>',
                '<span aria-hidden="true" class="btn-danger"><i class="fa fa-close"></i></span>',
                '</li>',
                '<li>',
                '<br/>',
            ],
            $input
        );
    }
}
