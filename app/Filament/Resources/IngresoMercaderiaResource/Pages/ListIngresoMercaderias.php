<?php

namespace App\Filament\Resources\IngresoMercaderiaResource\Pages;

use App\Filament\Resources\IngresoMercaderiaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIngresoMercaderias extends ListRecords
{
    protected static string $resource = IngresoMercaderiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
