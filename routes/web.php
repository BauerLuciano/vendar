<?php

use App\Http\Controllers\{
    SucursalController, ProfileController, ProductoController,
    CategoriaController, MarcaController, VentaController,
    TransferenciaSugeridaController, IngresoMercaderiaController,
    DashboardController, ConsumidorController, ProveedorController,
    PosController, RoleController, UsuarioController
};
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --- RUTA PÚBLICA ---
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// --- RUTAS PARA CUALQUIER USUARIO LOGUEADO ---
Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Perfil (Todos pueden editar su propia cuenta)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// --- ZONA COMERCIAL (Admins, Cajeros y Encargados) ---
Route::middleware(['auth', 'role:SuperAdmin|Administrador Global|Cajero|Encargado'])->group(function () {
    
    // POS y Ventas
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/abrir-turno', [PosController::class, 'abrirTurno'])->name('pos.abrir_turno');
    Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index'); 
    Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
    
    // Clientes
    Route::get('/clientes', [ConsumidorController::class, 'index'])->name('consumidores.index');
    Route::post('/clientes', [ConsumidorController::class, 'store'])->name('consumidores.store');
    Route::put('/clientes/{consumidor}', [ConsumidorController::class, 'update'])->name('consumidores.update');

    // Consultar Productos (Ver precios/stock)
    Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
});

// --- ZONA DE GESTIÓN (Solo Admins y Encargados) ---
Route::middleware(['auth', 'role:SuperAdmin|Administrador Global|Encargado'])->group(function () {
    
    // Stock e Ingresos
    Route::get('/ingresos', [IngresoMercaderiaController::class, 'index'])->name('ingresos.index');
    Route::post('/ingresos', [IngresoMercaderiaController::class, 'store'])->name('ingresos.store');
    
    // Catálogo
    Route::resource('categorias', CategoriaController::class);
    Route::resource('marcas', MarcaController::class);
    Route::resource('proveedores', ProveedorController::class);
    
    // Edición de Productos
    Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
    Route::post('/productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
    Route::patch('/productos/{producto}/status', [ProductoController::class, 'status'])->name('productos.status');

    // Transferencias
    Route::get('/transferencias-sugeridas', [TransferenciaSugeridaController::class, 'index'])->name('transferencias.index');
    Route::post('/transferencias-sugeridas/{transferencia}/aprobar', [TransferenciaSugeridaController::class, 'aprobar'])->name('transferencias.aprobar');
});

// --- ZONA DE PODER ABSOLUTO (Solo Dueños y Devs) ---
Route::middleware(['auth', 'role:SuperAdmin|Administrador Global'])->group(function () {
    
    // Sucursales
    Route::get('/sucursales', [SucursalController::class, 'index'])->name('sucursales.index');
    Route::post('/sucursales', [SucursalController::class, 'store'])->name('sucursales.store');
    Route::put('/sucursales/{sucursal}', [SucursalController::class, 'update'])->name('sucursales.update');
    Route::patch('/sucursales/{sucursal}/status', [SucursalController::class, 'status'])->name('sucursales.status');

    // Seguridad y Usuarios
    Route::resource('roles', RoleController::class);
    Route::resource('usuarios', UsuarioController::class);
    
    // Cancelar Ventas (Solo el jefe puede anular un ticket)
    Route::post('/ventas/{venta}/cancelar', [VentaController::class, 'cancelar'])->name('ventas.cancelar'); 
});

require __DIR__.'/auth.php';