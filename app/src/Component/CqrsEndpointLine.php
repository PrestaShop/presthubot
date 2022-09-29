<?php

namespace App\Component;

use App\Presenter\CqrsEndpoints\CqrsEndpointViewModel;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('cqrsendpointline')]
class CqrsEndpointLine
{
    public CqrsEndpointViewModel $line;
}
