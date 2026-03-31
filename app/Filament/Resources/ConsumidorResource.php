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
                Tables\Actions\Action::make('cobrar_deuda')
                    ->label('Cobrar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    // Solo mostramos el botón si el cliente debe plata (> 0)
                    ->visible(fn (Consumidor $record) => $record->cuentaCorriente && $record->cuentaCorriente->saldo_deudor > 0)
                    
                    // Armamos el Modal (Formulario Emergente)
                    ->form([
                        Forms\Components\TextInput::make('monto_pago')
                            ->label('Monto que entrega el cliente')
                            ->numeric()
                            ->required()
                            ->prefix('$')
                            // Evitamos que pague de más por error
                            ->maxValue(fn (Consumidor $record) => $record->cuentaCorriente->saldo_deudor)
                            // Le mostramos un mensajito de ayuda con su deuda actual
                            ->helperText(fn (Consumidor $record) => 'Deuda actual: $' . number_format($record->cuentaCorriente->saldo_deudor, 2, ',', '.')),
                    ])
                    
                    // La Lógica: ¿Qué pasa cuando el cajero aprieta "Confirmar"?
                    ->action(function (array $data, Consumidor $record): void {
                        $cuenta = $record->cuentaCorriente;
                        $montoPagado = $data['monto_pago'];

                        // 1. Guardamos el comprobante en la tabla nueva
                        \App\Models\MovimientoCuentaCorriente::create([
                            'cuenta_corriente_id' => $cuenta->id,
                            'monto' => $montoPagado,
                            'tipo' => 'pago',
                            'descripcion' => 'Pago por caja',
                        ]);

                        // 2. Le restamos la plata a su deuda
                        $cuenta->update([
                            'saldo_deudor' => $cuenta->saldo_deudor - $montoPagado,
                        ]);

                        // 3. Le tiramos una notificación verde de éxito al kiosquero
                        \Filament\Notifications\Notification::make()
                            ->title('Pago registrado correctamente')
                            ->success()
                            ->send();
                    }),
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