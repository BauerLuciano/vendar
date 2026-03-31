<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConsumidorResource\Pages;
use App\Models\Consumidor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ConsumidorResource extends Resource
{
    protected static ?string $model = Consumidor::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Clientes / Fiados';
    protected static ?string $modelLabel = 'Consumidor';
    protected static ?string $pluralModelLabel = 'Consumidores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('dni')
                    ->label('DNI')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('telefono')
                    ->tel()
                    ->label('Teléfono / WhatsApp')
                    ->maxLength(255),
                Forms\Components\TextInput::make('direccion')
                    ->label('Dirección')
                    ->maxLength(255),
                Forms\Components\TextInput::make('limite_cuenta_corriente')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$')
                    ->label('Límite de Fiado (CC)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('telefono')
                    ->searchable(),
                
                // Mostrar el Límite de Crédito
                Tables\Columns\TextColumn::make('limite_cuenta_corriente')
                    ->money('ARS')
                    ->sortable()
                    ->label('Límite Permitido'),

                // Magia Pura: Traemos el saldo desde la tabla de cuentas_corrientes
                Tables\Columns\TextColumn::make('cuentaCorriente.saldo_deudor')
                    ->money('ARS')
                    ->label('Deuda Actual')
                    ->badge() // Lo convierte en una "pastilla" visual
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success') // Rojo si debe plata, verde si está en $0
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsumidors::route('/'),
            'create' => Pages\CreateConsumidor::route('/create'),
            'edit' => Pages\EditConsumidor::route('/{record}/edit'),
        ];
    }
}