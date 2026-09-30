<?php

namespace App\Filament\Resources\StableMates\Pages;

use App\Filament\Resources\StableMates\StableMateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStableMates extends ListRecords
{
    protected static string $resource = StableMateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Invite stable mate'),
        ];
    }
}
