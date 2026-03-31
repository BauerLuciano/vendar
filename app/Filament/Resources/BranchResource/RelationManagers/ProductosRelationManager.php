<?php

namespace App\Filament\Resources\BranchResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductosRelationManager extends RelationManager
{
    protected static string $relationship = 'productos';
    protected static ?string $title = 'Stock de Productos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('cantidad_fisica')
                    ->label('Stock Físico (Mostrador)')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('cantidad_reservada')
                    ->label('Stock Reservado (Pedidos)')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre')
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Producto'),

                Tables\Columns\TextColumn::make('cantidad_fisica')
                    ->label('Físico')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('cantidad_reservada')
                    ->label('Reservado')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('disponible')
                    ->label('Disponible para Venta')
                    ->getStateUsing(function ($record) {
                        return $record->pivot->cantidad_fisica - $record->pivot->cantidad_reservada;
                    })
                    ->badge()
                    ->color('info'), 
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Agregar Producto')
                    ->preloadRecordSelect()
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('cantidad_fisica')->numeric()->default(0)->label('Stock Físico'),
                        Forms\Components\TextInput::make('cantidad_reservada')->numeric()->default(0)->label('Stock Reservado'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar Stock'),
                Tables\Actions\DetachAction::make()->label('Quitar'),
            ]);
    }
}