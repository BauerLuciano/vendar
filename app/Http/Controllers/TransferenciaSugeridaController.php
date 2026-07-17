<?php

namespace App\Http\Controllers;

use App\Models\TransferenciaSugerida;
use App\Services\SucursalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferenciaSugeridaController extends Controller
{
    public function __construct(
        private readonly SucursalScopeService $scope
    ) {}

    /**
     * Listado de transferencias según el rol del usuario.
     *
     * SuperAdmin/AdminGlobal: todas las transferencias del comercio.
     * Encargado/Cajero: solo transferencias donde su sucursal participa.
     */
    public function index()
    {
        $sucursalIds = $this->scope->obtenerSucursalesPermitidasIds();

        // AdminGlobal sin comercio: vista vacía
        if ($this->scope->esAdminGlobal() && empty($sucursalIds)) {
            return inertia('Transferencias/Index', [
                'sugerencias' => [],
                'enTransito' => [],
                'historial' => [],
            ]);
        }

        // =================================================================
        // MOTOR PREVENTIVO: Detectar bajo stock y armar sugerencias
        // =================================================================
        $alcanceIds = $this->scope->esJefe()
            ? $this->scope->obtenerSucursalesDelComercioIds()
            : $sucursalIds;

        $necesitados = DB::table('producto_sucursal')
            ->join('productos', 'productos.id', '=', 'producto_sucursal.producto_id')
            ->select('producto_sucursal.*', 'productos.stock_minimo')
            ->whereIn('producto_sucursal.sucursal_id', $alcanceIds)
            ->whereRaw('producto_sucursal.cantidad_fisica < productos.stock_minimo')
            ->get();

        foreach ($necesitados as $necesitado) {
            $cantidadFaltante = $necesitado->stock_minimo - $necesitado->cantidad_fisica;

            if ($cantidadFaltante > 0) {
                $salvador = DB::table('producto_sucursal')
                    ->join('productos', 'productos.id', '=', 'producto_sucursal.producto_id')
                    ->whereIn('producto_sucursal.sucursal_id', $alcanceIds)
                    ->where('producto_sucursal.producto_id', $necesitado->producto_id)
                    ->where('producto_sucursal.sucursal_id', '!=', $necesitado->sucursal_id)
                    ->whereRaw('producto_sucursal.cantidad_fisica >= (CAST(? AS NUMERIC) + CAST(productos.stock_minimo AS NUMERIC))', [$cantidadFaltante])
                    ->select('producto_sucursal.sucursal_id')
                    ->first();

                if ($salvador) {
                    TransferenciaSugerida::firstOrCreate([
                        'origen_id' => $salvador->sucursal_id,
                        'destino_id' => $necesitado->sucursal_id,
                        'producto_id' => $necesitado->producto_id,
                        'estado' => 'pendiente'
                    ], [
                        'cantidad' => $cantidadFaltante
                    ]);
                }
            }
        }

        // =================================================================
        // LECTURA: Pendientes para despachar (soy origen)
        // =================================================================
        $sugerencias = TransferenciaSugerida::with(['origen', 'destino', 'producto'])
            ->whereIn('origen_id', $sucursalIds)
            ->where('estado', 'pendiente')
            ->get();

        // =================================================================
        // LECTURA: En tránsito para recibir (soy destino)
        // =================================================================
        $enTransito = TransferenciaSugerida::with(['origen', 'destino', 'producto'])
            ->whereIn('destino_id', $sucursalIds)
            ->where('estado', 'en_transito')
            ->get();

        // =================================================================
        // LECTURA: Historial (finalizadas o canceladas)
        // =================================================================
        $historial = TransferenciaSugerida::with(['origen', 'destino', 'producto'])
            ->where(function ($q) use ($sucursalIds) {
                $q->whereIn('origen_id', $sucursalIds)
                  ->orWhereIn('destino_id', $sucursalIds);
            })
            ->whereIn('estado', ['recibida', 'cancelada', 'rechazada'])
            ->orderBy('updated_at', 'desc')
            ->take(50)
            ->get();

        return inertia('Transferencias/Index', [
            'sugerencias' => $sugerencias,
            'enTransito' => $enTransito,
            'historial'   => $historial,
        ]);
    }

    /**
     * Despacha stock desde la sucursal origen.
     * Cambia estado: pendiente -> en_transito
     *
     * SuperAdmin: puede despachar desde cualquier sucursal del comercio.
     * Encargado/Cajero: solo puede despachar si la sucursal origen es su sucursal activa.
     */
    public function despachar(TransferenciaSugerida $transferencia)
    {
        if (!$this->scope->puedeAccederSucursal($transferencia->origen_id)) {
            return redirect()->back()->with('error', 'No estás autorizado para despachar desde esta sucursal.');
        }

        if ($transferencia->estado !== 'pendiente') {
            return redirect()->back()->with('error', 'Solo se pueden despachar transferencias pendientes.');
        }

        DB::transaction(function () use ($transferencia) {
            $userId = auth()->id();

            // Bloquear y validar stock origen
            $stockOrigen = DB::table('producto_sucursal')
                ->where('sucursal_id', $transferencia->origen_id)
                ->where('producto_id', $transferencia->producto_id)
                ->lockForUpdate()
                ->first();

            $cantDisponible = $stockOrigen ? $stockOrigen->cantidad_fisica : 0;

            if ($cantDisponible < $transferencia->cantidad) {
                throw new \Exception(
                    "Stock insuficiente en sucursal origen. " .
                    "Disponible: {$cantDisponible}, requerido: {$transferencia->cantidad}. " .
                    "La transferencia fue cancelada automáticamente."
                );
            }

            $nuevaCant = $cantDisponible - $transferencia->cantidad;

            DB::table('producto_sucursal')
                ->where('producto_id', $transferencia->producto_id)
                ->where('sucursal_id', $transferencia->origen_id)
                ->update(['cantidad_fisica' => $nuevaCant]);

            // Auditoría origen
            DB::table('movimientos_stock')->insert([
                'producto_id' => $transferencia->producto_id,
                'sucursal_id' => $transferencia->origen_id,
                'user_id' => $userId,
                'tipo_movimiento' => 'Transferencia Enviada',
                'cantidad_anterior' => $cantDisponible,
                'cantidad_movimiento' => -$transferencia->cantidad,
                'cantidad_actual' => $nuevaCant,
                'motivo' => "Despacho a sucursal destino ID: {$transferencia->destino_id}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $transferencia->update(['estado' => 'en_transito']);
        });

        return redirect()->back()->with('success', 'Transferencia despachada. Stock en tránsito hacia la sucursal destino.');
    }

    /**
     * Recibe stock en la sucursal destino.
     * Cambia estado: en_transito -> recibida
     *
     * SuperAdmin: puede recibir en cualquier sucursal del comercio.
     * Encargado/Cajero: solo puede recibir si la sucursal destino es su sucursal activa.
     */
    public function recibir(TransferenciaSugerida $transferencia)
    {
        if (!$this->scope->puedeAccederSucursal($transferencia->destino_id)) {
            return redirect()->back()->with('error', 'No estás autorizado para recibir en esta sucursal.');
        }

        if ($transferencia->estado !== 'en_transito') {
            return redirect()->back()->with('error', 'Solo se pueden recibir transferencias en tránsito.');
        }

        DB::transaction(function () use ($transferencia) {
            $userId = auth()->id();

            // Bloquear stock destino
            $stockDestino = DB::table('producto_sucursal')
                ->where('sucursal_id', $transferencia->destino_id)
                ->where('producto_id', $transferencia->producto_id)
                ->lockForUpdate()
                ->first();

            if ($stockDestino) {
                $cantAnt = $stockDestino->cantidad_fisica;
                $nuevaCant = $cantAnt + $transferencia->cantidad;

                DB::table('producto_sucursal')
                    ->where('producto_id', $transferencia->producto_id)
                    ->where('sucursal_id', $transferencia->destino_id)
                    ->update(['cantidad_fisica' => $nuevaCant]);
            } else {
                // Producto no existe aún en destino, crear registro
                $cantAnt = 0;
                $nuevaCant = $transferencia->cantidad;

                DB::table('producto_sucursal')->insert([
                    'producto_id' => $transferencia->producto_id,
                    'sucursal_id' => $transferencia->destino_id,
                    'cantidad_fisica' => $nuevaCant,
                    'cantidad_reservada' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Auditoría destino
            DB::table('movimientos_stock')->insert([
                'producto_id' => $transferencia->producto_id,
                'sucursal_id' => $transferencia->destino_id,
                'user_id' => $userId,
                'tipo_movimiento' => 'Transferencia Recibida',
                'cantidad_anterior' => $cantAnt,
                'cantidad_movimiento' => $transferencia->cantidad,
                'cantidad_actual' => $nuevaCant,
                'motivo' => "Recepción desde sucursal origen ID: {$transferencia->origen_id}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $transferencia->update(['estado' => 'recibida']);
        });

        return redirect()->back()->with('success', 'Transferencia recibida. Stock actualizado en esta sucursal.');
    }

    /**
     * Cancela una transferencia. Solo permitido si el estado es pendiente.
     *
     * SuperAdmin: puede cancelar si la transferencia pertenece a su comercio.
     * Encargado/Cajero: solo puede cancelar si la sucursal origen es su sucursal activa.
     */
    public function cancelar(TransferenciaSugerida $transferencia)
    {
        if (!$this->scope->puedeAccederSucursal($transferencia->origen_id)) {
            return redirect()->back()->with('error', 'No estás autorizado para cancelar esta transferencia.');
        }

        if ($transferencia->estado !== 'pendiente') {
            return redirect()->back()->with('error', 'Solo se pueden cancelar transferencias pendientes.');
        }

        $transferencia->update(['estado' => 'cancelada']);

        return redirect()->back()->with('success', 'Transferencia cancelada.');
    }
}
