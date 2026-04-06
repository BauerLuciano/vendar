<?php

<<<<<<< HEAD
use App\Http\Controllers\SucursalController; 
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\TransferenciaSugeridaController;
use App\Http\Controllers\IngresoMercaderiaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ConsumidorController;
use App\Models\CuentaCorriente;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\CajaController; 
use App\Http\Controllers\CajaDiariaController; 
=======
use App\Http\Controllers\{
    SucursalController, ProfileController, ProductoController,
    CategoriaController, MarcaController, VentaController,
    TransferenciaSugeridaController, IngresoMercaderiaController,
    DashboardController, ConsumidorController, ProveedorController,
    PosController, RoleController, UsuarioController
};
>>>>>>> f40729119a84a63619d4ddbaa68366e6c7e7d7f9
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --- RUTA PÚBLICA ---
Route::get('/', function () {
    return redirect()->route('login');
});

// --- RUTAS PARA CUALQUIER USUARIO LOGUEADO ---
Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Perfil (Todos pueden editar su propia cuenta)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

<<<<<<< HEAD
    // ------------------------------------------------------------------
    // RUTAS DEL PUNTO DE VENTA Y CAJAS FÍSICAS (CRUD)
    // ------------------------------------------------------------------
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/abrir-turno', [PosController::class, 'abrirTurno'])->name('pos.abrir_turno');

    Route::get('/cajas', [CajaController::class, 'index'])->name('cajas.index');
    Route::post('/cajas', [CajaController::class, 'store'])->name('cajas.store');
    Route::put('/cajas/{caja}', [CajaController::class, 'update'])->name('cajas.update');
    Route::patch('/cajas/{caja}/estado', [CajaController::class, 'toggleEstado'])->name('cajas.estado');
    Route::delete('/cajas/{caja}', [CajaController::class, 'destroy'])->name('cajas.destroy');

    Route::get('/caja-diaria', function () {
        return Inertia::render('CajaDiaria/Index'); 
    })->name('cajadiaria.index');

    // ------------------------------------------------------------------
    // ENDPOINTS PARA EL COMPONENTE VUE (AXIOS) - PREFIJO API
    // ------------------------------------------------------------------
    Route::prefix('api/sesiones-caja')->group(function () {
        Route::get('/', [CajaDiariaController::class, 'index']); // Historial completo
        Route::get('/actual', [CajaDiariaController::class, 'getSesionActual']);
        Route::post('/abrir', [CajaDiariaController::class, 'abrirCaja']);
        Route::post('/movimiento-manual', [CajaDiariaController::class, 'crearMovimientoManual']);
        Route::get('/cajas-disponibles', [CajaDiariaController::class, 'getCajasDisponibles']);
        Route::get('/pendientes', [CajaDiariaController::class, 'getPendientes']);
        
        // Rutas con ID
        Route::get('/{id}/balance', [CajaDiariaController::class, 'getBalance']);
        Route::get('/{id}/movimientos', [CajaDiariaController::class, 'getMovimientos']);
        Route::post('/{id}/cerrar', [CajaDiariaController::class, 'cerrarCaja']);
        Route::get('/{id}/descargar_pdf', [CajaDiariaController::class, 'descargarPdf']);
    });

    // ------------------------------------------------------------------
    // RESTO DEL SISTEMA (Ventas, Productos, Stock, etc.)
    // ------------------------------------------------------------------
=======
// --- ZONA COMERCIAL (Admins, Cajeros y Encargados) ---
Route::middleware(['auth', 'role:SuperAdmin|Administrador Global|Cajero|Encargado'])->group(function () {
    
    // POS y Ventas
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/abrir-turno', [PosController::class, 'abrirTurno'])->name('pos.abrir_turno');
>>>>>>> f40729119a84a63619d4ddbaa68366e6c7e7d7f9
    Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index'); 
    Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
    
    // Clientes
    Route::get('/clientes', [ConsumidorController::class, 'index'])->name('consumidores.index');
    Route::post('/clientes', [ConsumidorController::class, 'store'])->name('consumidores.store');
    Route::put('/clientes/{consumidor}', [ConsumidorController::class, 'update'])->name('consumidores.update');

<<<<<<< HEAD
    Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
    Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
    Route::patch('/productos/{producto}/status', [ProductoController::class, 'status'])->name('productos.status');
    Route::post('/productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
    
    Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
    Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
    Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
    Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->name('categorias.destroy');
    Route::patch('/categorias/{categoria}/status', [CategoriaController::class, 'status'])->name('categorias.status');
    
    Route::get('/marcas', [MarcaController::class, 'index'])->name('marcas.index');
    Route::post('/marcas', [MarcaController::class, 'store'])->name('marcas.store');
    Route::post('/marcas/{marca}', [MarcaController::class, 'update'])->name('marcas.update');
    Route::patch('/marcas/{marca}/status', [MarcaController::class, 'status'])->name('marcas.status');

    Route::get('/transferencias-sugeridas', [TransferenciaSugeridaController::class, 'index'])->name('transferencias.index');
    Route::post('/transferencias-sugeridas/{transferencia}/aprobar', [TransferenciaSugeridaController::class, 'aprobar'])->name('transferencias.aprobar');

    Route::get('/ingresos', [IngresoMercaderiaController::class, 'index'])->name('ingresos.index');
    Route::post('/ingresos', [IngresoMercaderiaController::class, 'store'])->name('ingresos.store');
 
=======
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
>>>>>>> f40729119a84a63619d4ddbaa68366e6c7e7d7f9
    Route::get('/sucursales', [SucursalController::class, 'index'])->name('sucursales.index');
    Route::post('/sucursales', [SucursalController::class, 'store'])->name('sucursales.store');
    Route::put('/sucursales/{sucursal}', [SucursalController::class, 'update'])->name('sucursales.update');
    Route::patch('/sucursales/{sucursal}/status', [SucursalController::class, 'status'])->name('sucursales.status');

<<<<<<< HEAD
    Route::get('/clientes', [ConsumidorController::class, 'index'])->name('consumidores.index');
    Route::post('/clientes', [ConsumidorController::class, 'store'])->name('consumidores.store');
    Route::put('/clientes/{consumidor}', [ConsumidorController::class, 'update'])->name('consumidores.update');

    Route::get('/proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
    Route::post('/proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
    Route::put('/proveedores/{proveedore}', [ProveedorController::class, 'update'])->name('proveedores.update');
    Route::patch('/proveedores/{proveedore}/status', [ProveedorController::class, 'status'])->name('proveedores.status');

=======
    // Seguridad y Usuarios
    Route::resource('roles', RoleController::class);
    Route::resource('usuarios', UsuarioController::class);
    
    // Cancelar Ventas (Solo el jefe puede anular un ticket)
    Route::post('/ventas/{venta}/cancelar', [VentaController::class, 'cancelar'])->name('ventas.cancelar'); 
>>>>>>> f40729119a84a63619d4ddbaa68366e6c7e7d7f9
});

require __DIR__.'/auth.php';