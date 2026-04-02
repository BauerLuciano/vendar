<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MovimientoAuditoriaResource\Pages;
use App\Models\MovimientoAuditoria;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\KeyValueEntry;

class MovimientoAuditoriaResource extends Resource
{
    protected static ?string $model = MovimientoAuditoria::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Auditoría de Cambios';
    protected static ?string $modelLabel = 'Movimiento de Auditoría';

    public static function canCreate(): bool { return false; }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información del Movimiento')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('fecha')->label('Fecha')->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('usuario.name')->label('Usuario'),
                        TextEntry::make('branch.name')->label('Sucursal')->badge()->color('success'),
                        TextEntry::make('accion')->label('Acción')->badge()->color('warning'),
                        TextEntry::make('tabla')->label('Módulo'),
                        TextEntry::make('registro_id')->label('ID Registro'),
                    ]),
                
                Section::make('Detalle de los Cambios')
                    ->schema([
                        KeyValueEntry::make('antes')
                            ->label('Valores Anteriores')
                            ->state(fn ($record) => is_array($record->detalles) ? ($record->detalles['antes'] ?? []) : []),
                        
                        KeyValueEntry::make('despues')
                            ->label('Valores Nuevos')
                            ->state(fn ($record) => is_array($record->detalles) ? ($record->detalles['despues'] ?? []) : []),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')->dateTime('d/m/Y H:i')->sortable(),
                // CAMBIADO: .nombre -> .name
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('usuario.name')->label('Usuario')->searchable(),
                TextColumn::make('accion')->badge(),
                TextColumn::make('detalles')
                    ->label('Campos')
                    ->formatStateUsing(fn($state) => is_array($state) ? implode(', ', array_keys($state['antes'])) : '-'),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                SelectFilter::make('sucursal_id')
                    ->label('Filtrar por Sucursal')
                    ->relationship('branch', 'name')
                    ->preload(),

                SelectFilter::make('usuario_id')
                    ->label('Filtrar por Usuario')
                    ->relationship('usuario', 'name')
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMovimientoAuditorias::route('/'),
        ];
    }
}