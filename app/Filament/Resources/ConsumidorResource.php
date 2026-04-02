<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConsumidorResource\Pages;
use App\Models\Consumidor;
use App\Models\MovimientoCuentaCorriente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

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
                    ->maxLength(255)
                    ->regex('/^[\pL\s\-]+$/u') 
                    ->validationMessages([
                        'regex' => 'El nombre no puede contener números ni símbolos.',
                    ]),
                Forms\Components\TextInput::make('dni')
                    ->label('DNI')
                    ->required()
                    ->numeric()
                    ->minLength(7)
                    ->maxLength(8)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'numeric' => 'El DNI debe ser solo números.',
                        'min' => 'El DNI debe tener al menos 7 dígitos.',
                        'max' => 'El DNI no puede superar los 8 dígitos.',
                    ]),
                Forms\Components\TextInput::make('telefono')
                    ->label('Teléfono / WhatsApp')
                    ->tel() 
                    ->regex('/^[0-9]+$/') 
                    ->maxLength(15)
                    ->validationMessages([
                        'regex' => 'Ingresá solo los números del teléfono, sin espacios ni guiones.',
                    ]),
                Forms\Components\TextInput::make('direccion')
                    ->label('Dirección')
                    ->maxLength(255),
                Forms\Components\TextInput::make('limite_cuenta_corriente')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$')
                    ->label('Límite de Fiado (CC)'),
                Forms\Components\TextInput::make('puntos_acumulados')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->label('Puntos de Fidelidad'),
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
                Tables\Columns\TextColumn::make('limite_cuenta_corriente')
                    ->money('ARS')
                    ->sortable()
                    ->label('Límite Permitido'),
                Tables\Columns\TextColumn::make('puntos_acumulados')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->label('Puntos'),
                Tables\Columns\TextColumn::make('cuentaCorriente.saldo_deudor')
                    ->money('ARS')
                    ->label('Deuda Actual')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                // 1. CANJEAR PUNTOS
                Tables\Actions\Action::make('canjear_puntos')
                    ->label('Canjear Puntos')
                    ->icon('heroicon-o-gift')
                    ->color('warning')
                    ->visible(fn (Consumidor $record) => $record->puntos_acumulados > 0)
                    ->form([
                        Forms\Components\TextInput::make('puntos_a_descontar')
                            ->label('Puntos a canjear (Premio)')
                            ->numeric()
                            ->required()
                            ->maxValue(fn (Consumidor $record) => $record->puntos_acumulados)
                            ->helperText(fn (Consumidor $record) => 'Puntos disponibles: ' . $record->puntos_acumulados),
                    ])
                    ->action(function (array $data, Consumidor $record): void {
                        $record->update([
                            'puntos_acumulados' => $record->puntos_acumulados - $data['puntos_a_descontar'],
                        ]);

                        Notification::make()
                            ->title('Premio canjeado con éxito')
                            ->success()
                            ->send();
                    }),

                // 2. COBRAR DEUDA
                Tables\Actions\Action::make('cobrar_deuda')
                    ->label('Cobrar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Consumidor $record) => $record->cuentaCorriente && $record->cuentaCorriente->saldo_deudor > 0)
                    ->form([
                        Forms\Components\TextInput::make('monto_pago')
                            ->label('Monto que entrega el cliente')
                            ->numeric()
                            ->required()
                            ->prefix('$')
                            ->maxValue(fn (Consumidor $record) => $record->cuentaCorriente->saldo_deudor)
                            ->helperText(fn (Consumidor $record) => 'Deuda actual: $' . number_format($record->cuentaCorriente->saldo_deudor, 2, ',', '.')),
                    ])
                    ->action(function (array $data, Consumidor $record): void {
                        $cuenta = $record->cuentaCorriente;
                        $montoPagado = $data['monto_pago'];

                        MovimientoCuentaCorriente::create([
                            'cuenta_corriente_id' => $cuenta->id,
                            'monto' => $montoPagado,
                            'tipo' => 'pago',
                            'descripcion' => 'Pago por caja',
                        ]);

                        $cuenta->update([
                            'saldo_deudor' => $cuenta->saldo_deudor - $montoPagado,
                        ]);

                        Notification::make()
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

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsumidors::route('/'),
            'create' => Pages\CreateConsumidor::route('/create'),
            'edit' => Pages\EditConsumidor::route('/{record}/edit'),
        ];
    }
}