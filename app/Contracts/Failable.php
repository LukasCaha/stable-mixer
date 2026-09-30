<?php

namespace App\Contracts;

use Throwable;

interface Failable
{
    public function failed(?Throwable $exception): void;
}
