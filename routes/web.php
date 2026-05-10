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
    GlobalAdminController,
    ConfiguracionController,
    TicketController,
    ImpersonateController,
};
use App\Models\CuentaCorriente;
use App\Models\Sucursal;
use App\Models\Producto;
use App\Http\Controllers\Auth\GoogleLoginController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


// ==========================================
// --- ZONA PÚBLICA (CATÁLOGO Y GPS) ---
// ==========================================

Route::get('/', function () {
    $comercio = \App\Models\Comercio::first();

    $sucursales = Sucursal::select('id', 'nombre', 'latitud', 'longitud', 'direccion', 'costo_delivery')
        ->where('estado', true)
        ->when($comercio, fn($q) => $q->where('comercio_id', $comercio->id))
        ->get()
        ->map(function ($sucursal) {
            $sucursal->latitud  = (float) $sucursal->latitud;
            $sucursal->longitud = (float) $sucursal->longitud;
            return $sucursal;
        });

    $categorias = \App\Models\Categoria::where('estado', true)
        ->orderBy('nombreCategoria')
        ->get()
        ->map(fn($c) => [
            'id'     => $c->id,
            'nombre' => $c->nombreCategoria,
        ]);

    return Inertia::render('Welcome', [
        'comercio'          => $comercio,
        'canLogin'          => Route::has('login'),
        'canRegister'       => Route::has('register'),
        'sucursalesBackend' => $sucursales,
        'categorias'        => $categorias,
    ]);
});

// PANTALLA DE BLOQUEO PARA COMERCIOS MOROSOS O SUSPENDIDOS
Route::get('/cuenta-suspendida', function () {
    return Inertia::render('Suspendido');
})->name('cuenta.suspendida');

Route::get('/api/catalogo/{sucursal_id}', function ($sucursal_id) {
    $sucursal = Sucursal::find($sucursal_id);
    if (!$sucursal) return response()->json([]);

    $productos = $sucursal->productos()
        ->with('categoria')
        ->where('productos.estado', true)
        ->get();

    return response()->json($productos->map(function ($prod) {
        return [
            'id'           => $prod->id,
            'nombre'       => $prod->nombre,
            'categoria_id' => $prod->categoria_id,
            'categoria'    => $prod->categoria
                ? ['id' => $prod->categoria->id, 'nombre' => $prod->categoria->nombreCategoria]
                : null,
            'precio'       => $prod->precio_venta,
            'imagen_url'   => $prod->url_imagen,
        ];
    }));
});


// --- RUTAS PARA CUALQUIER USUARIO LOGUEADO ---
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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
// MÓDULO: PUNTO DE VENTA (POS) Y CAJAS
// ------------------------------------------------------------------
Route::middleware(['auth', 'modulo:pos'])->group(function () {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/abrir-turno', [PosController::class, 'abrirTurno'])->name('pos.abrir_turno');
    Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
    Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::get('/ventas/{venta}/imprimir', [TicketController::class, 'imprimir'])->name('ventas.imprimir');

    Route::get('/cajas', [CajaController::class, 'index'])->name('cajas.index');
    Route::post('/cajas', [CajaController::class, 'store'])->name('cajas.store');
    Route::put('/cajas/{caja}', [CajaController::class, 'update'])->name('cajas.update');
    Route::patch('/cajas/{caja}/status', [CajaController::class, 'toggleEstado'])->name('cajas.status');
    Route::delete('/cajas/{caja}', [CajaController::class, 'destroy'])->name('cajas.destroy');

    Route::get('/caja-diaria', function () {
        return Inertia::render('CajaDiaria/Index');
    })->name('cajadiaria.index');

    Route::prefix('api/sesiones-caja')->group(function () {
        Route::get('/', [CajaDiariaController::class, 'index']);
        Route::get('/actual', [CajaDiariaController::class, 'getSesionActual']);
        Route::post('/abrir', [CajaDiariaController::class, 'abrirCaja']);
        Route::post('/movimiento-manual', [CajaDiariaController::class, 'crearMovimientoManual']);
        Route::get('/cajas-disponibles', [CajaDiariaController::class, 'getCajasDisponibles']);
        Route::get('/pendientes', [CajaDiariaController::class, 'getPendientes']);
        Route::get('/{id}/balance', [CajaDiariaController::class, 'getBalance']);
        Route::get('/{id}/movimientos', [CajaDiariaController::class, 'getMovimientos']);
        Route::post('/{id}/cerrar', [CajaDiariaController::class, 'cerrarCaja']);
        Route::get('/{id}/descargar_pdf', [CajaDiariaController::class, 'descargarPdf']);
    });
});

// ------------------------------------------------------------------
// MÓDULO: CUENTAS CORRIENTES (FIADOS)
// ------------------------------------------------------------------
Route::middleware(['auth', 'modulo:fiados'])->group(function () {
    Route::get('/clientes', [ConsumidorController::class, 'index'])->name('consumidores.index');
    Route::post('/clientes', [ConsumidorController::class, 'store'])->name('consumidores.store');
    Route::put('/clientes/{consumidor}', [ConsumidorController::class, 'update'])->name('consumidores.update');
    Route::post('/consumidores/{consumidor}/cobrar', [ConsumidorController::class, 'cobrarDeuda'])->name('consumidores.cobrar');
    Route::patch('/consumidores/{consumidor}/status', [ConsumidorController::class, 'status'])->name('consumidores.status');
    Route::get('/consumidores/{consumidor}/cuenta', [ConsumidorController::class, 'estadoCuenta'])->name('consumidores.cuenta');
    Route::get('/consumidores/check-documento', [ConsumidorController::class, 'checkDocumento'])->name('consumidores.checkDocumento');
});

// ------------------------------------------------------------------
// MÓDULO: GESTIÓN DE STOCK AVANZADA (LOTES)
// ------------------------------------------------------------------
Route::middleware(['auth', 'modulo:lotes'])->group(function () {
    Route::get('/lotes', [App\Http\Controllers\LoteController::class, 'index'])->name('lotes.index');
});

// ------------------------------------------------------------------
// MÓDULO: GESTIÓN DE PROVEEDORES Y COMPRAS
// ------------------------------------------------------------------
Route::middleware(['auth', 'modulo:proveedores'])->group(function () {
    Route::get('/proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
    Route::post('/proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
    Route::put('/proveedores/{proveedore}', [ProveedorController::class, 'update'])->name('proveedores.update');
    Route::patch('/proveedores/{proveedore}/status', [ProveedorController::class, 'status'])->name('proveedores.status');
    Route::resource('proveedores-resource', ProveedorController::class)->except(['index', 'store', 'update']);

    Route::get('/ingresos', [IngresoMercaderiaController::class, 'index'])->name('ingresos.index');
    Route::post('/ingresos', [IngresoMercaderiaController::class, 'store'])->name('ingresos.store');

    Route::resource('ordenes-compra', OrdenCompraController::class)->except(['create', 'show', 'edit', 'update']);
    Route::get('/ordenes-compra/{ordenCompra}/pdf', [OrdenCompraController::class, 'descargarPDF'])->name('ordenes-compra.pdf');
    Route::post('/ordenes-compra/sugerencias', [OrdenCompraController::class, 'generarSugerencias'])->name('ordenes-compra.sugerencias');
    Route::patch('/ordenes-compra/{ordenCompra}/estado', [OrdenCompraController::class, 'cambiarEstado'])->name('ordenes-compra.estado');
    Route::post('/ordenes-compra/{ordenCompra}/aprobar', [OrdenCompraController::class, 'aprobarYRecibir'])->name('ordenes-compra.aprobar');
    Route::post('/ordenes-compra/{ordenCompra}/confirmar', [OrdenCompraController::class, 'confirmarPedido'])->name('ordenes-compra.confirmar');

    Route::get('/reposicion', [ReposicionController::class, 'index'])->name('reposicion.index');
    Route::post('/reposicion/generar', [ReposicionController::class, 'generarPreOrdenes'])->name('reposicion.generar');
    Route::get('/cotizar/{id}', [ReposicionController::class, 'verCotizacion'])->name('cotizar.ver');
    Route::post('/cotizar/{id}', [ReposicionController::class, 'guardarCotizacion'])->name('cotizar.guardar');
});

// ------------------------------------------------------------------
// MÓDULO: OPTIMIZACIÓN DE STOCK (TRANSFERENCIAS)
// ------------------------------------------------------------------
Route::middleware(['auth', 'modulo:transferencias'])->group(function () {
    Route::get('/transferencias-sugeridas', [TransferenciaSugeridaController::class, 'index'])->name('transferencias.index');
    Route::post('/transferencias-sugeridas/{transferencia}/aprobar', [TransferenciaSugeridaController::class, 'aprobar'])->name('transferencias.aprobar');
});

// ------------------------------------------------------------------
// MÓDULO: AUDITORÍA
// ------------------------------------------------------------------
Route::middleware(['auth', 'modulo:auditoria'])->group(function () {
    Route::get('/productos/{producto}/auditoria', [ProductoController::class, 'auditoria'])->name('productos.auditoria');
});

// ------------------------------------------------------------------
// ZONA CORE (Productos, Sucursales, Config) - Siempre Activo
// ------------------------------------------------------------------
Route::middleware(['auth', 'role:SuperAdmin|Administrador Global|Encargado'])->group(function () {
    Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
    Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
    Route::post('/productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
    Route::patch('/productos/{producto}/status', [ProductoController::class, 'status'])->name('productos.status');
    Route::get('/productos/generar-plu', [ProductoController::class, 'generarPlu'])->name('productos.generar-plu');
    Route::post('/productos/{producto}/ajuste-stock', [ProductoController::class, 'ajustarStock'])->name('productos.ajustar');

    Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
    Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
    Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
    Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->name('categorias.destroy');
    Route::patch('/categorias/{categoria}/status', [CategoriaController::class, 'status'])->name('categorias.status');

    Route::get('/marcas', [MarcaController::class, 'index'])->name('marcas.index');
    Route::post('/marcas', [MarcaController::class, 'store'])->name('marcas.store');
    Route::post('/marcas/{marca}', [MarcaController::class, 'update'])->name('marcas.update');
    Route::patch('/marcas/{marca}/status', [MarcaController::class, 'status'])->name('marcas.status');
});

// ------------------------------------------------------------------
// ZONA DUEÑO DEL LOCAL (Configuración)
// ------------------------------------------------------------------
Route::middleware(['auth', 'role:SuperAdmin|Administrador Global'])->group(function () {
    Route::get('/sucursales', [SucursalController::class, 'index'])->name('sucursales.index');
    Route::post('/sucursales', [SucursalController::class, 'store'])->name('sucursales.store');
    Route::put('/sucursales/{sucursal}', [SucursalController::class, 'update'])->name('sucursales.update');
    Route::patch('/sucursales/{sucursal}/status', [SucursalController::class, 'status'])->name('sucursales.status');

    Route::resource('roles', RoleController::class);
    Route::post('/permisos', [RoleController::class, 'storePermiso'])->name('permisos.store');
    Route::put('/permisos/{permiso}', [RoleController::class, 'updatePermiso'])->name('permisos.update');

    Route::resource('usuarios', UsuarioController::class);

    Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
    Route::post('/configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
});

// ==================================================================
// ZONA DE ADMINISTRACIÓN GLOBAL (VEND-AR MASTER)
// ==================================================================
Route::middleware(['auth', 'role:Administrador Global'])->prefix('admin-global')->group(function () {
    Route::get('/comercios', [GlobalAdminController::class, 'index'])->name('admin.comercios.index');
    Route::post('/comercios', [GlobalAdminController::class, 'store'])->name('admin.comercios.store');
    Route::put('/comercios/{comercio}', [GlobalAdminController::class, 'update'])->name('admin.comercios.update');

    Route::post('/impersonate/enter/{comercio}', [ImpersonateController::class, 'enter'])->name('impersonate.enter');
    Route::post('/impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');
});

// ------------------------------------------------------------------
// TIENDA PÚBLICA POR SLUG
// ------------------------------------------------------------------
Route::get('/tienda/{slug}', function ($slug) {
    $comercio = \App\Models\Comercio::where('slug', $slug)->firstOrFail();

    $sucursales = \App\Models\Sucursal::where('comercio_id', $comercio->id)
        ->where('estado', true)
        ->select('id', 'nombre', 'latitud', 'longitud', 'direccion', 'costo_delivery')
        ->get()
        ->map(function ($sucursal) {
            $sucursal->latitud  = (float) $sucursal->latitud;
            $sucursal->longitud = (float) $sucursal->longitud;
            return $sucursal;
        });

    $categorias = \App\Models\Categoria::where('estado', true)
        ->orderBy('nombreCategoria')
        ->get()
        ->map(fn($c) => [
            'id'     => $c->id,
            'nombre' => $c->nombreCategoria,
        ]);

    return Inertia::render('Welcome', [
        'comercio'          => $comercio,
        'sucursalesBackend' => $sucursales,
        'categorias'        => $categorias,
        'canLogin'          => Route::has('login'),
        'canRegister'       => Route::has('register'),
    ]);
})->name('tienda.publica');

require __DIR__.'/auth.php';