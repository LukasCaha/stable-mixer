<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StableLanguage: string implements HasLabel
{
    case English = 'en';
    case Czech = 'cs';

    public function getLabel(): string
    {
        return match ($this) {
            self::English => 'EN',
            self::Czech => 'CZ',
        };
    }

    public function promptName(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Czech => 'Czech',
        };
    }
}
