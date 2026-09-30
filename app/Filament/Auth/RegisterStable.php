<?php

namespace App\Filament\Auth;

use App\Enums\UserRole;
use App\Models\Stable;
use App\Models\User;
use Filament\Auth\Pages\Register;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

class RegisterStable extends Register
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getStableNameFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    public function getHeading(): string
    {
        return 'Create your stable';
    }

    protected function getStableNameFormComponent(): Component
    {
        return TextInput::make('stable_name')
            ->label('Stable name')
            ->required()
            ->maxLength(255);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $stable = Stable::query()->create([
            'name' => $data['stable_name'],
        ]);

        return User::query()->create([
            'stable_id' => $stable->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Owner,
            'is_super_admin' => false,
        ]);
    }
}
