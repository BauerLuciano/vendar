<?php

namespace App\Filament\Resources\ReglaLiquidacionResource\Pages;

use App\Filament\Resources\ReglaLiquidacionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReglaLiquidacion extends EditRecord
{
    protected static string $resource = ReglaLiquidacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
