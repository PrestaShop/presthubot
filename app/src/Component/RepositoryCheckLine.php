<?php

namespace App\Component;

use App\Presenter\RepositoryCheck\RepositoryCheckViewModel;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('repositorycheckline')]
class RepositoryCheckLine
{
    public RepositoryCheckViewModel $line;
}
