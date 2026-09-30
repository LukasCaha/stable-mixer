<?php

namespace App\Filament\Resources\Horses\Pages;

use App\Filament\Resources\Horses\HorseResource;
use Filament\Resources\Pages\ListRecords;

class ListHorses extends ListRecords
{
    protected static string $resource = HorseResource::class;
}
