<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\MarcaController;
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

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
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
    //Rutas de categorias
    Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
    Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
    Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
    Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->name('categorias.destroy');
    Route::patch('/categorias/{categoria}/status', [CategoriaController::class, 'status'])->name('categorias.status');
    //Rutas de marcas
    Route::get('/marcas', [MarcaController::class, 'index'])->name('marcas.index');
    Route::post('/marcas', [MarcaController::class, 'store'])->name('marcas.store');
    Route::post('/marcas/{marca}', [MarcaController::class, 'update'])->name('marcas.update');
    Route::patch('/marcas/{marca}/status', [MarcaController::class, 'status'])->name('marcas.status');
    //Ruta de sucursales
    Route::get('/sucursales', [BranchController::class, 'index'])->name('sucursales.index');
    Route::post('/sucursales', [BranchController::class, 'store'])->name('sucursales.store');
    Route::put('/sucursales/{branch}', [BranchController::class, 'update'])->name('sucursales.update');
    Route::patch('/sucursales/{branch}/status', [BranchController::class, 'status'])->name('sucursales.status');
});

require __DIR__.'/auth.php';