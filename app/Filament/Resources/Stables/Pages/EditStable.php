<?php

namespace App\Filament\Resources\Stables\Pages;

use App\Filament\Resources\Stables\StableResource;
use App\Models\Stable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStable extends EditRecord
{
    protected static string $resource = StableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerateTenantCode')
                ->label('Regenerate tenant code')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Regenerate tenant code')
                ->modalDescription('The companion app for this stable will reject uploads until it uses the new code.')
                ->action(function (): void {
                    /** @var Stable $stable */
                    $stable = $this->getRecord();
                    $code = $stable->regenerateTenantCode();

                    Notification::make()
                        ->success()
                        ->title('Tenant code regenerated')
                        ->body($code)
                        ->send();

                    $this->fillForm();
                }),
        ];
    }
}
