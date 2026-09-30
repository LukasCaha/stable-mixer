<?php

namespace App\Filament\Forms;

use App\Models\Stable;
use App\Support\TenantCodeQr;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

class TenantCodeQrField
{
    public static function make(): Placeholder
    {
        return Placeholder::make('tenant_code_qr')
            ->label('QR code')
            ->content(function (?Stable $record): HtmlString {
                $stable = $record instanceof Stable ? $record : Filament::getTenant();

                if (! $stable instanceof Stable || blank($stable->tenant_code)) {
                    return new HtmlString('');
                }

                return TenantCodeQr::image($stable->tenant_code);
            });
    }
}
