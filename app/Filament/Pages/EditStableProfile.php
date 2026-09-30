<?php

namespace App\Filament\Pages;

use App\Enums\StableLanguage;
use App\Filament\Forms\TenantCodeQrField;
use App\Models\Stable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;

class EditStableProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Stable settings';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                Select::make('language')
                    ->label('Language')
                    ->options(StableLanguage::class)
                    ->required()
                    ->helperText('Transcription and answers for this stable use this language. The phone has its own language setting.'),
                TextInput::make('tenant_code')
                    ->label('Tenant code')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Read-only after create. Regenerate only if the companion app code is compromised.'),
                TenantCodeQrField::make(),
                Toggle::make('is_active')->label('Accept companion uploads'),
            ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['tenant_code']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerateTenantCode')
                ->label('Regenerate tenant code')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Regenerate tenant code')
                ->modalDescription('The companion app will reject uploads until it is updated with the new code.')
                ->action(function (): void {
                    /** @var Stable $stable */
                    $stable = $this->tenant;
                    $code = $stable->regenerateTenantCode();

                    Notification::make()
                        ->success()
                        ->title('Tenant code regenerated')
                        ->body($code)
                        ->send();

                    $this->redirect(Filament::getPanel()->getUrl($stable));
                }),
        ];
    }
}
