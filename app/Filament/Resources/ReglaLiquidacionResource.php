<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReglaLiquidacionResource\Pages;
use App\Models\ReglaLiquidacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReglaLiquidacionResource extends Resource
{
    protected static ?string $model = ReglaLiquidacion::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Promos x Vencimiento';
    
    // Nombres en español
    protected static ?string $modelLabel = 'Regla de Liquidación';
    protected static ?string $pluralModelLabel = 'Reglas de Liquidación';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('producto_id')
                    ->relationship('producto', 'nombre')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Producto a Bonificar'),
                    
                Forms\Components\TextInput::make('dias_anticipacion')
                    ->required()
                    ->numeric()
                    ->label('Días antes del vencimiento')
                    ->helperText('Ej: 5 (se aplica 5 días antes de vencer)'),
                    
                Forms\Components\TextInput::make('porcentaje_descuento')
                    ->required()
                    ->numeric()
                    ->prefix('%')
                    ->label('Porcentaje de Descuento')
                    ->helperText('Ej: 30 para un 30% off'),
                    
                // Acá está el Toggle que pedía tu checklist
                Forms\Components\Toggle::make('estado')
                    ->required()
                    ->default(true)
                    ->label('Regla Activa')
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('producto.nombre')
                    ->searchable()
                    ->sortable()
                    ->label('Producto'),
                    
                Tables\Columns\TextColumn::make('dias_anticipacion')
                    ->numeric()
                    ->sortable()
                    ->label('Días Anticipación'),
                    
                Tables\Columns\TextColumn::make('porcentaje_descuento')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->label('% Descuento'),
                    
                // Mostramos el Toggle también en la tabla para apagarlo con 1 clic
                Tables\Columns\ToggleColumn::make('estado')
                    ->label('Activa'),
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
            'index' => Pages\ListReglaLiquidacions::route('/'),
            'create' => Pages\CreateReglaLiquidacion::route('/create'),
            'edit' => Pages\EditReglaLiquidacion::route('/{record}/edit'),
        ];
    }
}