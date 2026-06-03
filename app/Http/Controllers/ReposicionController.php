<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use App\Mail\PreOrdenProveedor;
use Illuminate\Support\Str;

class ReposicionController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);
        
        $sucursalId = $user->branch_id;
        if (!$sucursalId) {
            $sucursalId = null;
        }
        if (!$sucursalId) {
            return redirect()->back()->withErrors(['error' => 'No tenés una sucursal asignada.']);
        }

        // 1. Buscamos TODOS los productos que están por debajo del mínimo en esa sucursal
        $faltantes = DB::table('productos')
            ->join('producto_sucursal', 'productos.id', '=', 'producto_sucursal.producto_id')
            ->where('producto_sucursal.sucursal_id', $sucursalId)
            ->whereRaw('producto_sucursal.cantidad_fisica <= productos.stock_minimo')
            ->select(
                'productos.id', 
                'productos.nombre', 
                'productos.codigo_barras',
                'productos.proveedor_id', // El proveedor por defecto
                'productos.stock_minimo', 
                'productos.precio_costo',
                'producto_sucursal.cantidad_fisica'
            )->get();

        return Inertia::render('Reposicion/Index', [
            'faltantes' => $faltantes,
            'proveedores' => Proveedor::where('estado', true)->get(),
            'sucursalActual' => Sucursal::find($sucursalId)
        ]);
    }

    public function generarPreOrdenes(Request $request)
    {
        $request->validate([
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.proveedor_id' => 'required|exists:proveedores,id',
            'productos.*.cantidad' => 'required|integer|min:1',
            'productos.*.precio_costo' => 'required|numeric|min:0',
        ]);

        $user = auth()->user();
        $sucursalId = $user->branch_id;
        if (!$sucursalId) {
            return redirect()->back()->withErrors(['error' => 'No tenés una sucursal asignada.']);
        }

        DB::beginTransaction();

        try {
            $porProveedor = collect($request->productos)->groupBy('proveedor_id');

            foreach ($porProveedor as $proveedorId => $items) {
                $orden = OrdenCompra::create([
                    'sucursal_id' => $sucursalId,
                    'proveedor_id' => $proveedorId,
                    'user_id' => $user->id,
                    'estado' => 'Borrador', 
                    'token_cotizacion' => Str::random(40),
                    'fecha_emision' => now(),
                    'total_estimado' => 0,
                    'observaciones' => 'Solicitud de reposición inteligente.',
                ]);

                $total = 0;

                foreach ($items as $item) {
                    $subtotal = $item['cantidad'] * $item['precio_costo'];
                    
                    OrdenCompraDetalle::create([
                        'orden_compra_id' => $orden->id,
                        'producto_id' => $item['producto_id'],
                        'cantidad_pedida' => $item['cantidad'],
                        'costo_unitario_estimado' => $item['precio_costo'],
                        'subtotal_estimado' => $subtotal
                    ]);

                    $total += $subtotal;
                }
                $correoDestino = $orden->proveedor->email ?? 'proveedor@test.com';
                Mail::to($correoDestino)->send(new PreOrdenProveedor($orden));
                $orden->update(['total_estimado' => $total]);
            }

            DB::commit();
            
            return redirect()->back()->with('success', '¡Pre-Órdenes generadas y correos enviados exitosamente!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Hubo un error: ' . $e->getMessage());
        }
    }

    public function verCotizacion(Request $request, $id)
    {
        $orden = OrdenCompra::with(['detalles.producto', 'proveedor', 'sucursal'])->findOrFail($id);
        if (!$orden->token_cotizacion || $request->token !== $orden->token_cotizacion) {
            abort(403, 'Este enlace de cotización es inválido, falso o ya caducó.');
        }

        return Inertia::render('Reposicion/Cotizar', [
            'orden' => $orden
        ]);
    }

    public function guardarCotizacion(Request $request, $id)
    {
        $request->validate([
            'fecha_entrega' => 'required|date',
            'detalles' => 'required|array',
            'detalles.*.cantidad_pedida' => 'required|numeric|min:0',
            'detalles.*.costo_unitario_estimado' => 'required|numeric|min:0',
        ]);

        $orden = OrdenCompra::findOrFail($id);

        if (!$orden->token_cotizacion || $request->token !== $orden->token_cotizacion) {
            abort(403, 'Enlace inválido.');
        }

        $totalEstimado = 0;

        foreach ($request->detalles as $item) {
            $subtotal = $item['cantidad_pedida'] * $item['costo_unitario_estimado'];
            
            OrdenCompraDetalle::where('id', $item['id'])->update([
                'cantidad_pedida' => $item['cantidad_pedida'],
                'costo_unitario_estimado' => $item['costo_unitario_estimado'],
                'subtotal_estimado' => $subtotal
            ]);

            $totalEstimado += $subtotal;
        }

        $orden->update([
            'estado' => 'Cotizada',
            'total_estimado' => $totalEstimado,
            'fecha_entrega_esperada' => $request->fecha_entrega,
        ]);

        return redirect()->back();
    }
}