<?php

namespace App\Filament\Resources\StableMates;

use App\Enums\UserRole;
use App\Filament\Resources\StableMates\Pages\CreateStableMate;
use App\Filament\Resources\StableMates\Pages\EditStableMate;
use App\Filament\Resources\StableMates\Pages\ListStableMates;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StableMateResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'mates';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Stable mates';

    protected static ?string $modelLabel = 'stable mate';

    protected static ?string $pluralModelLabel = 'stable mates';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                Select::make('role')
                    ->options(UserRole::class)
                    ->required(fn (): bool => (bool) auth()->user()?->isOwner())
                    ->disabled(function (?User $record): bool {
                        $actor = auth()->user();

                        if (! $actor?->isOwner()) {
                            return true;
                        }

                        if (! $record?->isOwner()) {
                            return false;
                        }

                        return User::query()
                            ->where('stable_id', $record->stable_id)
                            ->where('role', UserRole::Owner)
                            ->count() < 2;
                    }),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Leave blank to keep the current password.'
                        : 'Share this password with the stable mate. There is no invitation email in v0.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge(),
                IconColumn::make('is_super_admin')
                    ->label('Super admin')
                    ->boolean()
                    ->visible(fn (): bool => (bool) auth()->user()?->is_super_admin),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStableMates::route('/'),
            'create' => CreateStableMate::route('/create'),
            'edit' => EditStableMate::route('/{record}/edit'),
        ];
    }
}
