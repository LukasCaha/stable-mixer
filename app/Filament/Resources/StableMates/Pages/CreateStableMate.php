<?php

namespace App\Filament\Resources\StableMates\Pages;

use App\Filament\Resources\StableMates\StableMateResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateStableMate extends CreateRecord
{
    protected static string $resource = StableMateResource::class;

    public function getTitle(): string
    {
        return 'Invite stable mate';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['stable_id'] = Filament::getTenant()?->getKey() ?? auth()->user()?->stable_id;

        return $data;
    }
}
