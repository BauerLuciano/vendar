<?php

namespace App\Filament\Resources\MovimientoAuditoriaResource\Pages;

use App\Filament\Resources\MovimientoAuditoriaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMovimientoAuditoria extends ViewRecord
{
    protected static string $resource = MovimientoAuditoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
