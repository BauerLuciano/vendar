<?php

namespace App\Filament\Resources\MovimientoAuditoriaResource\Pages;

use App\Filament\Resources\MovimientoAuditoriaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMovimientoAuditorias extends ListRecords
{
    protected static string $resource = MovimientoAuditoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
