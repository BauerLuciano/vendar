<?php

namespace App\Filament\Resources\ConsumidorResource\Pages;

use App\Filament\Resources\ConsumidorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConsumidors extends ListRecords
{
    protected static string $resource = ConsumidorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
