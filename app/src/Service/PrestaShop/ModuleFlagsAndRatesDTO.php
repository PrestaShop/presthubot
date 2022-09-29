<?php

namespace App\Service\PrestaShop;

class ModuleFlagsAndRatesDTO
{
    public ModuleFlagsDTO $flags;
    public ModuleRatesDTO $rates;

    public function __construct()
    {
        $this->flags = new ModuleFlagsDTO();
        $this->rates = new ModuleRatesDTO();
    }
}
