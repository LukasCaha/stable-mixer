<?php

namespace App\Filament\Resources\Stables;

use App\Filament\Forms\TenantCodeQrField;
use App\Filament\Resources\Stables\Pages\EditStable;
use App\Filament\Resources\Stables\Pages\ListStables;
use App\Models\Stable;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StableResource extends Resource
{
    protected static ?string $model = Stable::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Stables';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('tenant_code')
                    ->label('Tenant code')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Read-only. Regenerate it from the page header if the companion app code leaks.'),
                TenantCodeQrField::make(),
                Toggle::make('is_active')->label('Accept companion uploads'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('tenant_code')->label('Tenant code')->searchable()->copyable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('users_count')->counts('users')->label('Mates'),
                TextColumn::make('memos_count')->counts('memos')->label('Memos'),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStables::route('/'),
            'edit' => EditStable::route('/{record}/edit'),
        ];
    }
}
