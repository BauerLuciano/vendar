<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\TransferenciaSugeridaController;
use App\Http\Controllers\IngresoMercaderiaController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ConsumidorController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// DASHBOARD (Limpiado y apuntando a su controlador)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');


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
    
    // Rutas de Sucursales
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::put('/branches/{branch}/status', [BranchController::class, 'status'])->name('branches.status');

    // Rutas de Clientes (Consumidores)
    Route::get('/clientes', [ConsumidorController::class, 'index'])->name('consumidores.index');
    Route::post('/clientes', [ConsumidorController::class, 'store'])->name('consumidores.store');
    Route::put('/clientes/{consumidor}', [ConsumidorController::class, 'update'])->name('consumidores.update');
});

require __DIR__.'/auth.php';