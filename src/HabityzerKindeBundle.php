<?php

namespace Habityzer\KindeBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class HabityzerKindeBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}

