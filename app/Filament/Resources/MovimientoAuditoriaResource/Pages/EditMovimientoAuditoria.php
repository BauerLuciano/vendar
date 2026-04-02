<?php

namespace App\Filament\Resources\MovimientoAuditoriaResource\Pages;

use App\Filament\Resources\MovimientoAuditoriaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMovimientoAuditoria extends EditRecord
{
    protected static string $resource = MovimientoAuditoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
