<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductoResource\Pages;
use App\Models\Producto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    // Le ponemos un ícono de código de barras para que quede fachero en el menú
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    // Cambiamos el nombre que aparece en el menú
    protected static ?string $navigationLabel = 'Productos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Magia Pura: Desplegable de Categorías
                Forms\Components\Select::make('categoria_id')
                    ->relationship('categoria', 'nombreCategoria') // Busca la relación en el modelo y muestra el nombre
                    ->required()
                    ->searchable() // Te pone un buscador adentro del select
                    ->preload() // Carga las opciones rápido
                    ->label('Categoría'),

                // Magia Pura: Desplegable de Marcas
                Forms\Components\Select::make('marca_id')
                    ->relationship('marca', 'nombreMarca')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Marca'),

                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('sku')
                    ->required()
                    ->unique(ignoreRecord: true) // Valida que no exista otro código igual (Checklist ítem 4)
                    ->maxLength(255)
                    ->label('SKU / Código de Barras'),

                Forms\Components\TextInput::make('precio_costo')
                    ->required()
                    ->numeric()
                    ->prefix('$'), // Le pone el signito de pesos adelante

                Forms\Components\TextInput::make('precio_venta')
                    ->required()
                    ->numeric()
                    ->prefix('$'),

                Forms\Components\TextInput::make('stock_minimo')
                    ->required()
                    ->numeric()
                    ->default(5)
                    ->label('Stock Mínimo de Alerta'),

                Forms\Components\Textarea::make('descripcion')
                    ->columnSpanFull(), // Hace que la descripción ocupe todo el ancho de la pantalla

                Forms\Components\FileUpload::make('imagen')
                    ->image()
                    ->directory('productos')
                    ->columnSpanFull()
                    ->label('Foto del Producto'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('imagen')
                    ->label('Foto'),

                Tables\Columns\TextColumn::make('sku')
                    ->searchable()
                    ->label('SKU'),

                Tables\Columns\TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),

                // Mostramos el nombre de la categoría, no el número ID
                Tables\Columns\TextColumn::make('categoria.nombreCategoria')
                    ->searchable()
                    ->sortable()
                    ->label('Categoría'),

                // Mostramos el nombre de la marca
                Tables\Columns\TextColumn::make('marca.nombreMarca')
                    ->searchable()
                    ->sortable()
                    ->label('Marca'),

                Tables\Columns\TextColumn::make('precio_venta')
                    ->money('ARS') // Lo formatea como moneda local
                    ->sortable()
                    ->label('Precio Venta'),
            ])
            ->filters([
                // Acá más adelante podemos meter filtros por marca o categoría
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('actualizarPrecios')
                        ->label('Aumentar Precios (%)')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('warning')
                        ->requiresConfirmation() // Pide confirmación para no hacer cagadas
                        ->form([
                            Forms\Components\TextInput::make('porcentaje')
                                ->label('Porcentaje de Aumento')
                                ->numeric()
                                ->required()
                                ->prefix('%')
                                ->helperText('Ejemplo: Escribí 15 para aumentar un 15% los precios.'),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                            $porcentaje = $data['porcentaje'];
                            
                            // Recorre todos los productos que seleccionaste y les hace la matemática
                            foreach ($records as $record) {
                                $nuevoCosto = $record->precio_costo * (1 + ($porcentaje / 100));
                                $nuevoVenta = $record->precio_venta * (1 + ($porcentaje / 100));

                                $record->update([
                                    'precio_costo' => $nuevoCosto,
                                    'precio_venta' => $nuevoVenta,
                                ]);
                            }
                        })
                    ->deselectRecordsAfterCompletion(),
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
            'index' => Pages\ListProductos::route('/'),
            'create' => Pages\CreateProducto::route('/create'),
            'edit' => Pages\EditProducto::route('/{record}/edit'),
        ];
    }
}