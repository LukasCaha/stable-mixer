<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum HorseLogStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Done => 'Done',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Done => 'success',
            self::Failed => 'danger',
            self::Skipped => 'gray',
        };
    }
}
