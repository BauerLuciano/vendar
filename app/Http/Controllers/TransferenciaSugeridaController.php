<?php

namespace App\Http\Controllers;

use App\Models\TransferenciaSugerida;
use App\Models\Lote;
use App\Services\SucursalScopeService;
use App\Services\LoteService;
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
            $cantidadTotal = (float) $transferencia->cantidad;

            $lotes = Lote::where('producto_id', $transferencia->producto_id)
                ->where('sucursal_id', $transferencia->origen_id)
                ->where('stock_actual', '>', 0)
                ->orderBy('fecha_vencimiento', 'asc')
                ->lockForUpdate()
                ->get();

            $pendientePorRestar = $cantidadTotal;
            $lotesDespacho = [];

            foreach ($lotes as $lote) {
                if ($pendientePorRestar <= 0) break;

                if ((float) $lote->stock_actual >= $pendientePorRestar) {
                    $cantidadRestada = $pendientePorRestar;
                    $lote->decrement('stock_actual', $pendientePorRestar);
                    $pendientePorRestar = 0;
                } else {
                    $cantidadRestada = (float) $lote->stock_actual;
                    $pendientePorRestar -= (float) $lote->stock_actual;
                    $lote->update(['stock_actual' => 0]);
                }

                $lotesDespacho[] = [
                    'lote_id' => $lote->id,
                    'cantidad' => $cantidadRestada,
                    'fecha_vencimiento' => $lote->fecha_vencimiento->format('Y-m-d'),
                ];
            }

            if ($pendientePorRestar > 0) {
                throw new \Exception(
                    "Stock de lotes insuficiente en sucursal origen. " .
                    "Faltan {$pendientePorRestar} unidades para cubrir la transferencia."
                );
            }

            $stockOrigen = DB::table('producto_sucursal')
                ->where('sucursal_id', $transferencia->origen_id)
                ->where('producto_id', $transferencia->producto_id)
                ->lockForUpdate()
                ->first();

            $cantDisponible = $stockOrigen ? (float) $stockOrigen->cantidad_fisica : 0;

            if ($cantDisponible < $cantidadTotal) {
                throw new \Exception(
                    "Stock insuficiente en sucursal origen. " .
                    "Disponible: {$cantDisponible}, requerido: {$cantidadTotal}. " .
                    "La transferencia fue cancelada automáticamente."
                );
            }

            $nuevaCant = $cantDisponible - $cantidadTotal;

            DB::table('producto_sucursal')
                ->where('producto_id', $transferencia->producto_id)
                ->where('sucursal_id', $transferencia->origen_id)
                ->update([
                    'cantidad_fisica' => $nuevaCant,
                    'updated_at' => now(),
                ]);

            DB::table('movimientos_stock')->insert([
                'producto_id' => $transferencia->producto_id,
                'sucursal_id' => $transferencia->origen_id,
                'user_id' => $userId,
                'tipo_movimiento' => 'Transferencia Enviada',
                'cantidad_anterior' => $cantDisponible,
                'cantidad_movimiento' => -$cantidadTotal,
                'cantidad_actual' => $nuevaCant,
                'motivo' => "Despacho a sucursal destino ID: {$transferencia->destino_id}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $transferencia->update([
                'estado' => 'en_transito',
                'lotes_despacho' => $lotesDespacho,
            ]);
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
            $lotesDespacho = $transferencia->lotes_despacho ?? [];

            $loteService = app(LoteService::class);
            foreach ($lotesDespacho as $loteInfo) {
                $loteService->upsert(
                    (int) $transferencia->producto_id,
                    (int) $transferencia->destino_id,
                    $loteInfo['fecha_vencimiento'],
                    (float) $loteInfo['cantidad']
                );
            }

            $stockDestino = DB::table('producto_sucursal')
                ->where('sucursal_id', $transferencia->destino_id)
                ->where('producto_id', $transferencia->producto_id)
                ->lockForUpdate()
                ->first();

            if ($stockDestino) {
                $cantAnt = (float) $stockDestino->cantidad_fisica;
                $nuevaCant = $cantAnt + (float) $transferencia->cantidad;

                DB::table('producto_sucursal')
                    ->where('producto_id', $transferencia->producto_id)
                    ->where('sucursal_id', $transferencia->destino_id)
                    ->update([
                        'cantidad_fisica' => $nuevaCant,
                        'updated_at' => now(),
                    ]);
            } else {
                $cantAnt = 0;
                $nuevaCant = (float) $transferencia->cantidad;

                DB::table('producto_sucursal')->insert([
                    'producto_id' => $transferencia->producto_id,
                    'sucursal_id' => $transferencia->destino_id,
                    'cantidad_fisica' => $nuevaCant,
                    'cantidad_reservada' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

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

    /**
     * Rechaza una transferencia en tránsito y devuelve el stock al origen.
     * Cambia estado: en_transito -> rechazada
     *
     * Revierte exactamente el despacho usando lotes_despacho.
     */
    public function rechazar(TransferenciaSugerida $transferencia)
    {
        if (!$this->scope->puedeAccederSucursal($transferencia->origen_id)) {
            return redirect()->back()->with('error', 'No estás autorizado para rechazar esta transferencia.');
        }

        if ($transferencia->estado !== 'en_transito') {
            return redirect()->back()->with('error', 'Solo se pueden rechazar transferencias en tránsito.');
        }

        $lotesDespacho = $transferencia->lotes_despacho ?? [];
        if (empty($lotesDespacho)) {
            return redirect()->back()->with('error', 'No hay datos de lotes para revertir. Contacte al administrador.');
        }

        DB::transaction(function () use ($transferencia, $lotesDespacho) {
            $userId = auth()->id();
            $cantidadTotal = (float) $transferencia->cantidad;

            $loteIds = collect($lotesDespacho)->pluck('lote_id')->values()->all();
            DB::table('lotes')->whereIn('id', $loteIds)->lockForUpdate()->get();

            foreach ($lotesDespacho as $loteInfo) {
                $lote = DB::table('lotes')->where('id', $loteInfo['lote_id'])->first();

                if ($lote) {
                    DB::table('lotes')
                        ->where('id', $loteInfo['lote_id'])
                        ->update([
                            'stock_actual' => DB::raw("stock_actual + " . (float) $loteInfo['cantidad']),
                            'updated_at'  => now(),
                        ]);
                } else {
                    DB::table('lotes')->insert([
                        'producto_id'       => $transferencia->producto_id,
                        'sucursal_id'       => $transferencia->origen_id,
                        'fecha_vencimiento' => $loteInfo['fecha_vencimiento'],
                        'stock_inicial'     => (float) $loteInfo['cantidad'],
                        'stock_actual'      => (float) $loteInfo['cantidad'],
                        'estado_liquidacion' => false,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                }
            }

            $stockOrigen = DB::table('producto_sucursal')
                ->where('sucursal_id', $transferencia->origen_id)
                ->where('producto_id', $transferencia->producto_id)
                ->lockForUpdate()
                ->first();

            $cantAnterior = $stockOrigen ? (float) $stockOrigen->cantidad_fisica : 0;
            $nuevaCant = $cantAnterior + $cantidadTotal;

            if ($stockOrigen) {
                DB::table('producto_sucursal')
                    ->where('producto_id', $transferencia->producto_id)
                    ->where('sucursal_id', $transferencia->origen_id)
                    ->update([
                        'cantidad_fisica' => $nuevaCant,
                        'updated_at'     => now(),
                    ]);
            } else {
                DB::table('producto_sucursal')->insert([
                    'producto_id'       => $transferencia->producto_id,
                    'sucursal_id'       => $transferencia->origen_id,
                    'cantidad_fisica'   => $nuevaCant,
                    'cantidad_reservada' => 0,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            DB::table('movimientos_stock')->insert([
                'producto_id'         => $transferencia->producto_id,
                'sucursal_id'         => $transferencia->origen_id,
                'user_id'             => $userId,
                'tipo_movimiento'     => 'Transferencia Rechazada',
                'cantidad_anterior'   => $cantAnterior,
                'cantidad_movimiento' => $cantidadTotal,
                'cantidad_actual'     => $nuevaCant,
                'motivo'              => "Rechazo de transferencia desde sucursal destino ID: {$transferencia->destino_id}",
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            $transferencia->update(['estado' => 'rechazada']);
        });

        return redirect()->back()->with('success', 'Transferencia rechazada. Stock restaurado en la sucursal origen.');
    }
}
