<?php

namespace App\Filament\Resources\ActivityResource\Pages;

use App\Filament\Resources\ActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    /** Solo lectura: el historial no se crea desde el panel. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
