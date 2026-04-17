<?php

use App\Http\Controllers\{
    SucursalController,
    ProfileController,
    ProductoController,
    CategoriaController,
    MarcaController,
    VentaController,
    TransferenciaSugeridaController,
    IngresoMercaderiaController,
    DashboardController,
    ConsumidorController,
    ProveedorController,
    PosController,
    CajaController,
    CajaDiariaController,
    RoleController,
    UsuarioController,
    OrdenCompraController,
    ReposicionController,
};
use App\Models\CuentaCorriente;
use App\Http\Controllers\Auth\GoogleLoginController;
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

// ==========================================
// RUTAS PARA LOGIN CON GOOGLE
// ==========================================
Route::get('/auth/google', [GoogleLoginController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleLoginController::class, 'callback']);

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
    Route::post('/consumidores/{consumidor}/cobrar', [ConsumidorController::class, 'cobrarDeuda'])->name('consumidores.cobrar');
    Route::patch('/consumidores/{consumidor}/status', [ConsumidorController::class, 'status'])->name('consumidores.status');
    Route::get('/consumidores/{consumidor}/cuenta', [ConsumidorController::class, 'estadoCuenta'])->name('consumidores.cuenta');
    
    // 👇 NUEVA RUTA PARA VERIFICAR DISPONIBILIDAD DEL DNI EN TIEMPO REAL
    Route::get('/consumidores/check-documento', [ConsumidorController::class, 'checkDocumento'])->name('consumidores.checkDocumento');

    // PLU CODIGO DE BARRAS
    Route::get('/productos/generar-plu', [\App\Http\Controllers\ProductoController::class, 'generarPlu'])->name('productos.generar-plu');

    // Productos (Rutas unificadas para evitar conflictos)
    Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
    Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
    Route::post('/productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
    Route::patch('/productos/{producto}/status', [ProductoController::class, 'status'])->name('productos.status');
    
    // Catálogo General
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
});

// --- ZONA DE GESTIÓN (Solo Admins y Encargados) ---
Route::middleware(['auth', 'role:SuperAdmin|Administrador Global|Encargado'])->group(function () {
    
    // Gestión de Stock específica
    Route::post('/productos/{producto}/ajuste-stock', [ProductoController::class, 'ajustarStock'])->name('productos.ajustar');
    Route::get('/productos/{producto}/auditoria', [ProductoController::class, 'auditoria'])->name('productos.auditoria');

    // Recursos
    Route::resource('proveedores', ProveedorController::class)->except(['index', 'store', 'update']); // index/store/update ya definidos
});

// --- ZONA DE PODER ABSOLUTO (Solo Dueños y Devs) ---
Route::middleware(['auth', 'role:SuperAdmin|Administrador Global'])->group(function () {
    
    // Sucursales
    Route::get('/sucursales', [SucursalController::class, 'index'])->name('sucursales.index');
    Route::post('/sucursales', [SucursalController::class, 'store'])->name('sucursales.store');
    Route::put('/sucursales/{sucursal}', [SucursalController::class, 'update'])->name('sucursales.update');
    Route::patch('/sucursales/{sucursal}/status', [SucursalController::class, 'status'])->name('sucursales.status');

    Route::get('/proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
    Route::post('/proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
    Route::put('/proveedores/{proveedore}', [ProveedorController::class, 'update'])->name('proveedores.update');
    Route::patch('/proveedores/{proveedore}/status', [ProveedorController::class, 'status'])->name('proveedores.status');

    // Seguridad y Usuarios
    Route::resource('roles', RoleController::class);
    
    // 🔥 RUTAS MANUALES PARA PERMISOS (Agregadas)
    Route::post('/permisos', [RoleController::class, 'storePermiso'])->name('permisos.store');
    Route::put('/permisos/{permiso}', [RoleController::class, 'updatePermiso'])->name('permisos.update');

    Route::resource('usuarios', UsuarioController::class);

    // Órdenes de Compra
    Route::resource('ordenes-compra', OrdenCompraController::class)->except(['create', 'show', 'edit', 'update']);
    Route::post('/ordenes-compra/sugerencias', [OrdenCompraController::class, 'generarSugerencias'])->name('ordenes-compra.sugerencias');
    Route::patch('/ordenes-compra/{ordenCompra}/estado', [OrdenCompraController::class, 'cambiarEstado'])->name('ordenes-compra.estado');
    Route::post('/ordenes-compra/{ordenCompra}/aprobar', [OrdenCompraController::class, 'aprobarYRecibir'])->name('ordenes-compra.aprobar');
    Route::post('/ordenes-compra/{ordenCompra}/confirmar', [OrdenCompraController::class, 'confirmarPedido'])->name('ordenes-compra.confirmar');
    
    // Reposición
    Route::get('/reposicion', [ReposicionController::class, 'index'])->name('reposicion.index');
    Route::post('/reposicion/generar', [ReposicionController::class, 'generarPreOrdenes'])->name('reposicion.generar');

    Route::get('/cotizar/{id}', [ReposicionController::class, 'verCotizacion'])->name('cotizar.ver');
    Route::post('/cotizar/{id}', [ReposicionController::class, 'guardarCotizacion'])->name('cotizar.guardar');

    // Configuraciones Generales
    Route::get('/configuracion', [\App\Http\Controllers\ConfiguracionController::class, 'index'])->name('configuracion.index');
    Route::post('/configuracion', [\App\Http\Controllers\ConfiguracionController::class, 'update'])->name('configuracion.update');
});

require __DIR__.'/auth.php';