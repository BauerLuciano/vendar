<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IngresoMercaderiaResource\Pages;
use App\Models\IngresoMercaderia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class IngresoMercaderiaResource extends Resource
{
    protected static ?string $model = IngresoMercaderia::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Ingreso de Mercadería';
    protected static ?string $modelLabel = 'Ingreso';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Remito / Comprobante')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('proveedor_id')
                            ->relationship('supplier', 'business_name') 
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Proveedor'),   

                        Forms\Components\Select::make('sucursal_id')
                            ->relationship('branch', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Sucursal Destino'),

                        Forms\Components\DatePicker::make('fecha_comprobante')
                            ->required()
                            ->default(now())
                            ->label('Fecha del Comprobante'),

                        Forms\Components\TextInput::make('total_factura')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->label('Total Factura (Opcional)'),
                    ]),

                Forms\Components\Section::make('Detalle de Productos')
                    ->schema([
                        Forms\Components\Repeater::make('detalles')
                            ->relationship() 
                            ->schema([
                                Forms\Components\Select::make('producto_id')
                                    ->relationship('producto', 'nombre')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems() 
                                    ->columnSpan(2)
                                    ->label('Producto'),

                                Forms\Components\TextInput::make('cantidad_recibida')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->label('Cantidad'),

                                Forms\Components\TextInput::make('precio_costo_actualizado')
                                    ->numeric()
                                    ->prefix('$')
                                    ->label('Nuevo Precio Costo')
                                    ->helperText('Dejar vacío si el precio no cambió'),
                            ])
                            ->columns(4) 
                            ->defaultItems(1)
                            ->addActionLabel('Añadir otro producto al remito'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fecha_comprobante')
                    ->date('d/m/Y')
                    ->sortable()
                    ->label('Fecha'),
                
                Tables\Columns\TextColumn::make('supplier.business_name') 
                    ->searchable()
                    ->label('Proveedor'),
                
                Tables\Columns\TextColumn::make('branch.name')
                    ->searchable()
                    ->badge()
                    ->color('success')
                    ->label('Sucursal'),
                
                Tables\Columns\TextColumn::make('total_factura')
                    ->money('ARS')
                    ->sortable()
                    ->label('Total Factura'),

                Tables\Columns\TextColumn::make('detalles_count')
                    ->counts('detalles')
                    ->label('Cant. Productos')
                    ->badge(),
            ])
            ->defaultSort('fecha_comprobante', 'desc')
            ->filters([
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIngresoMercaderias::route('/'),
            'create' => Pages\CreateIngresoMercaderia::route('/create'),
            'edit' => Pages\EditIngresoMercaderia::route('/{record}/edit'),
        ];
    }
}