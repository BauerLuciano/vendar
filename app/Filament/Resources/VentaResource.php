<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VentaResource\Pages;
use App\Filament\Resources\VentaResource\RelationManagers;
use App\Models\Venta;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VentaResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Información de la Venta')
                ->schema([
                    Forms\Components\Select::make('consumidor_id')
                        ->relationship('consumidor', 'nombre')
                        ->searchable()
                        ->preload()
                        ->label('Cliente')
                        ->helperText('Dejar vacío para "Consumidor Final"'),
                    
                    Forms\Components\Select::make('sucursal_id')
                        ->relationship('sucursal', 'name')
                        ->required()
                        ->default(1) // Ajustar según tu ID por defecto
                        ->label('Sucursal'),

                    Forms\Components\Select::make('metodo_pago')
                        ->options([
                            'efectivo' => 'Efectivo',
                            'fiado' => 'Fiado (Cuenta Corriente)',
                            'mercadopago' => 'Mercado Pago',
                        ])
                        ->required()
                        ->native(false)
                        ->label('Método de Pago'),
                ])->columns(3),

            Forms\Components\Section::make('Carrito de Compras')
                ->schema([
                    Forms\Components\Repeater::make('detalles')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('producto_id')
                                ->relationship('producto', 'nombre')
                                ->required()
                                ->reactive()
                                ->searchable()
                                ->preload()
                                ->afterStateUpdated(fn ($state, $set) => 
                                    $set('precio_unitario', \App\Models\Producto::find($state)?->precio_venta ?? 0))
                                ->columnSpan(3),
                                
                            Forms\Components\TextInput::make('cantidad')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->reactive()
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('precio_unitario')
                                ->numeric()
                                ->prefix('$')
                                ->disabled() // No queremos que el cajero lo cambie a mano
                                ->dehydrated() // Pero sí que se guarde en la BD
                                ->columnSpan(2),

                            Forms\Components\Placeholder::make('subtotal_placeholder')
                                ->label('Subtotal')
                                ->content(fn ($get) => '$' . number_format((float)$get('cantidad') * (float)$get('precio_unitario'), 2))
                                ->columnSpan(2),

                            Forms\Components\Hidden::make('subtotal')
                                ->default(0)
                                ->dehydrated(true)
                                // Cada vez que alguien toca el formulario, recalcula esto en silencio
                                ->mutateDehydratedStateUsing(function ($get) {
                                    $cantidad = (float) $get('cantidad');
                                    $precio = (float) $get('precio_unitario');
                                    return $cantidad * $precio;
                                }),
                        ])
                        ->columns(8)
                        ->reorderable(false)
                        ->addActionLabel('Agregar Producto')
                ]),
        ]);
}

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Nº Ticket')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('consumidor.nombre')
                    ->label('Cliente')
                    ->default('Consumidor Final') // Si es null, muestra esto
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sucursal.name')
                    ->label('Sucursal')
                    ->sortable(),

                Tables\Columns\TextColumn::make('metodo_pago')
                    ->label('Método de Pago')
                    ->badge() // Le ponemos un globito de color para que quede pro
                    ->color(fn (string $state): string => match ($state) {
                        'efectivo' => 'success',
                        'fiado' => 'danger',
                        'mercadopago' => 'info',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('ARS')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc') // Ordena para que las ventas nuevas salgan arriba
            ->filters([
                // Acá más adelante podemos poner filtros por fecha
            ])
            ->actions([
                Tables\Actions\ViewAction::make(), // Agregamos botón para ver el detalle
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
            'index' => Pages\ListVentas::route('/'),
            'create' => Pages\CreateVenta::route('/create'),
            'edit' => Pages\EditVenta::route('/{record}/edit'),
        ];
    }
}
