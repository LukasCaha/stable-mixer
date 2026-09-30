<?php

namespace App\Filament\Widgets;

use App\Models\Stable;
use App\Support\TenantCodeQr as TenantCodeQrCode;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class TenantCodeQr extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.tenant-code-qr';

    /**
     * @return array{code: string, qr: string}
     */
    protected function getViewData(): array
    {
        /** @var Stable $stable */
        $stable = Filament::getTenant();

        return [
            'code' => $stable->tenant_code,
            'qr' => TenantCodeQrCode::dataUri($stable->tenant_code),
        ];
    }
}
