<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\VentaController;
use App\Models\CuentaCorriente; // Importado para el cálculo de deudas
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB; // Importado para cruzar tablas
use Inertia\Inertia;
use App\Http\Controllers\TransferenciaSugeridaController;
use App\Http\Controllers\IngresoMercaderiaController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// TU NUEVO DASHBOARD
Route::get('/dashboard', function () {
    
    // 1. Cálculo de Deuda Total en la calle
    $deudaTotal = CuentaCorriente::sum('saldo_deudor');

    // 2. Cálculo de Productos con Bajo Stock (Cruce de tablas)
    $productosBajoStock = DB::table('productos')
        ->join('branch_producto', 'productos.id', '=', 'branch_producto.producto_id')
        ->join('branches', 'branches.id', '=', 'branch_producto.branch_id')
        ->select(
            'productos.nombre as producto', 
            'productos.stock_minimo', 
            'branch_producto.cantidad_fisica', 
            'branches.name as sucursal'
        )
        ->whereRaw('branch_producto.cantidad_fisica <= productos.stock_minimo')
        ->get();

    // 3. Le mandamos la data servida a Vue
    return Inertia::render('Dashboard', [
        'deudaTotal' => $deudaTotal,
        'productosBajoStock' => $productosBajoStock
    ]);
    
})->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // RUTAS DE PRODUCTOS
    Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
    Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
    Route::patch('/productos/{producto}/status', [ProductoController::class, 'status'])->name('productos.status');
    Route::post('/productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
    
    // Rutas de categorias
    Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
    Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
    Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
    Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->name('categorias.destroy');
    Route::patch('/categorias/{categoria}/status', [CategoriaController::class, 'status'])->name('categorias.status');
    
    // Rutas de marcas
    Route::get('/marcas', [MarcaController::class, 'index'])->name('marcas.index');
    Route::post('/marcas', [MarcaController::class, 'store'])->name('marcas.store');
    Route::post('/marcas/{marca}', [MarcaController::class, 'update'])->name('marcas.update');
    Route::patch('/marcas/{marca}/status', [MarcaController::class, 'status'])->name('marcas.status');

    // Rutas de Transferencias
    Route::get('/transferencias-sugeridas', [TransferenciaSugeridaController::class, 'index'])->name('transferencias.index');
    Route::post('/transferencias-sugeridas/{transferencia}/aprobar', [TransferenciaSugeridaController::class, 'aprobar'])->name('transferencias.aprobar');

    // Ruta de Ingreso de Mercadería
    Route::post('/ingresos', [IngresoMercaderiaController::class, 'store'])->name('ingresos.store');

    // Rutas de Ventas
    Route::get('/pos', [VentaController::class, 'index'])->name('pos.index');
    Route::post('/pos', [VentaController::class, 'store'])->name('ventas.store');   
});

require __DIR__.'/auth.php';