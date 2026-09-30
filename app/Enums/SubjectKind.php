<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubjectKind: string implements HasColor, HasLabel
{
    case Animal = 'animal';
    case Vehicle = 'vehicle';
    case Stock = 'stock';
    case Place = 'place';

    public function getLabel(): string
    {
        return match ($this) {
            self::Animal => 'Animal',
            self::Vehicle => 'Vehicle',
            self::Stock => 'Stock',
            self::Place => 'Place',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Animal => 'success',
            self::Vehicle => 'warning',
            self::Stock => 'info',
            self::Place => 'gray',
        };
    }
}
