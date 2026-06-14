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
    SuscripcionController,
    PedidoWebController, 
    GestionPedidosWebController,
    ReporteController,
};

use App\Models\CuentaCorriente;
use App\Models\Sucursal;
use App\Models\Producto;
use App\Models\PedidoWeb;
use App\Models\Plan;
use App\Http\Controllers\Auth\GoogleLoginController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


// ==========================================
// --- ZONA PÚBLICA (CATÁLOGO Y GPS) ---
// ==========================================

// PANTALLA DE BLOQUEO PARA COMERCIOS MOROSOS O SUSPENDIDOS
Route::get('/cuenta-suspendida', function () {
    return Inertia::render('Suspendido');
})->name('cuenta.suspendida');

// PANTALLA DE ESPERA PARA NUEVOS REGISTROS (ONBOARDING)
Route::get('/pending-approval', function () {
    return Inertia::render('PendingApproval');
})->name('pending.approval');

Route::get('/api/catalogo/{sucursal_id}', [\App\Http\Controllers\TiendaController::class, 'catalogo']);
Route::get('/api/promociones/{sucursal_id}', [\App\Http\Controllers\TiendaController::class, 'promociones']);


// --- RUTAS PARA CUALQUIER USUARIO LOGUEADO ---
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('redirect.cliente')
        ->name('dashboard');

    Route::get('/dashboard/pdf', [DashboardController::class, 'descargarPDF'])
        ->middleware('redirect.cliente')
        ->name('dashboard.pdf');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Ruta para pedidos web: acepta admin (User) y consumidores
Route::post('/api/pedidos-web', [PedidoWebController::class, 'store'])
    ->middleware('auth:web,consumidor')
    ->name('pedidos.web.store');

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
    Route::get('/pos/buscar-productos', [PosController::class, 'buscarProductos'])->name('pos.buscar.productos');
    Route::get('/pos/buscar-clientes', [PosController::class, 'buscarClientes'])->name('pos.buscar.clientes');
    Route::get('/pos/movimientos-turno', [PosController::class, 'movimientosTurno'])->name('pos.movimientos.turno');
    Route::post('/pos/guardar-carrito', [PosController::class, 'guardarCarrito'])->name('pos.guardar.carrito');
    Route::get('/pos/listar-pendientes', [PosController::class, 'listarPendientes'])->name('pos.listar.pendientes');
    Route::post('/pos/recuperar-carrito/{ventaPendiente}', [PosController::class, 'recuperarCarrito'])->name('pos.recuperar.carrito');
    Route::delete('/pos/eliminar-pendiente/{ventaPendiente}', [PosController::class, 'eliminarPendiente'])->name('pos.eliminar.pendiente');
    Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
    Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::get('/ventas/{venta}/imprimir', [TicketController::class, 'imprimir'])->name('ventas.imprimir');
    Route::patch('/ventas/{venta}/cancelar', [VentaController::class, 'cancelar'])->name('ventas.cancelar');

    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/pdf', [ReporteController::class, 'pdf'])->name('reportes.pdf');

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
    Route::get('/consumidores/check-email', [ConsumidorController::class, 'checkEmail'])->name('consumidores.checkEmail');
    Route::get('/consumidores/check-duplicados', [ConsumidorController::class, 'checkDuplicados'])->name('consumidores.checkDuplicados');
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
Route::middleware(['auth', 'role:SuperAdmin'])->group(function () {
    Route::get('/productos/{producto}/auditoria', [ProductoController::class, 'auditoria'])->name('productos.auditoria');
    Route::get('/auditoria', [App\Http\Controllers\AuditoriaController::class, 'index'])->name('auditoria.index');
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

    Route::get('/mi-plan', [SuscripcionController::class, 'miPlan'])->name('suscripcion.mi-plan');
    Route::post('/mi-plan/pagar', [SuscripcionController::class, 'generarPreferencia'])->name('suscripcion.pagar');

    Route::post('/api/mi-plan/confirmar-upgrade', [SuscripcionController::class, 'confirmarUpgrade'])->name('suscripcion.confirmar-upgrade');
    Route::get('/api/mi-plan/plan-actual', [SuscripcionController::class, 'planActual'])->name('suscripcion.plan-actual');

});

// ------------------------------------------------------------------
// ZONA GESTIÓN DE PEDIDOS WEB
// ------------------------------------------------------------------
    Route::middleware(['auth', 'permission:gestionar pedidos web'])->group(function () {
    Route::get('/pedidos', [GestionPedidosWebController::class, 'index'])->name('pedidos.index');
    Route::patch('/pedidos/{id}/estado', [GestionPedidosWebController::class, 'updateEstado'])->name('pedidos.estado');
    Route::patch('/pedidos/{id}/pago', [GestionPedidosWebController::class, 'updatePago'])->name('pedidos.pago');
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

    Route::get('/planes', [\App\Http\Controllers\AdminGlobal\PlanController::class, 'index'])->name('admin.planes.index');
    Route::post('/planes', [\App\Http\Controllers\AdminGlobal\PlanController::class, 'store'])->name('admin.planes.store');
    Route::put('/planes/{plan}', [\App\Http\Controllers\AdminGlobal\PlanController::class, 'update'])->name('admin.planes.update');
    Route::delete('/planes/{plan}', [\App\Http\Controllers\AdminGlobal\PlanController::class, 'destroy'])->name('admin.planes.destroy');

    Route::get('/metricas', [GlobalAdminController::class, 'metricas'])->name('admin.metricas');
    Route::get('/facturacion', [GlobalAdminController::class, 'facturacion'])->name('admin.facturacion');
    Route::post('/facturacion/{comercio}/pagar', [GlobalAdminController::class, 'marcarPagado'])->name('admin.facturacion.pagar');
    Route::post('/facturacion/{comercio}/link-mp', [GlobalAdminController::class, 'generarLinkMP'])->name('admin.facturacion.link-mp');
    Route::get('/solicitudes', [GlobalAdminController::class, 'solicitudesPendientes'])->name('admin.solicitudes');
    Route::post('/solicitudes/{user}/aprobar', [GlobalAdminController::class, 'aprobarSolicitud'])->name('admin.solicitudes.aprobar');
    Route::post('/solicitudes/{user}/rechazar', [GlobalAdminController::class, 'rechazarSolicitud'])->name('admin.solicitudes.rechazar');

    Route::post('/impersonate/enter/{comercio}', [ImpersonateController::class, 'enter'])->name('impersonate.enter');
    Route::post('/impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');
});

// ------------------------------------------------------------------
// TIENDA PÚBLICA POR SLUG
// ------------------------------------------------------------------
// Auth para consumidores en la tienda (API)
Route::post('/api/tienda/login', [App\Http\Controllers\ConsumidorAuthController::class, 'login']);
Route::post('/api/tienda/register', [App\Http\Controllers\ConsumidorAuthController::class, 'register']);
Route::post('/api/tienda/logout', [App\Http\Controllers\ConsumidorAuthController::class, 'logout'])->middleware('auth:consumidor');
Route::get('/api/tienda/me', [App\Http\Controllers\ConsumidorAuthController::class, 'me']);
Route::post('/api/tienda/perfil', [App\Http\Controllers\ConsumidorAuthController::class, 'updateProfile'])->middleware('auth:consumidor');

// Logout de consumidor (antes de la ruta {slug} para no colisionar)
Route::get('/tienda/logout-consumidor', function () {
    $slug = session('ultima_tienda_slug', '');
    auth('consumidor')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return $slug ? redirect('/tienda/' . $slug) : redirect('/');
})->name('tienda.logout.consumidor');

// Panel del consumidor (antes de la ruta {slug})
Route::get('/tienda/{slug}/panel', function ($slug) {
    $comercio = \App\Models\Comercio::where('slug', $slug)->firstOrFail();
    $consumidor = auth('consumidor')->user();

    if (!$consumidor) {
        return redirect('/tienda/' . $slug);
    }

    $pedidos = \App\Models\PedidoWeb::where('comercio_id', $comercio->id)
        ->where('cliente_telefono', $consumidor->telefono)
        ->orWhere(function ($q) use ($consumidor, $comercio) {
            $q->where('comercio_id', $comercio->id)
              ->where('cliente_nombre', $consumidor->nombre . ' ' . $consumidor->apellido);
        })
        ->orderBy('created_at', 'desc')
        ->with('items')
        ->get();

    return Inertia::render('Cliente/Panel', [
        'comercio'   => $comercio,
        'consumidor' => [
            'id'        => $consumidor->id,
            'nombre'    => $consumidor->nombre,
            'apellido'  => $consumidor->apellido,
            'email'     => $consumidor->email,
            'telefono'  => $consumidor->telefono,
            'direccion' => $consumidor->direccion,
        ],
        'pedidos'    => $pedidos,
        'tienda_slug'=> $slug,
    ]);
})->name('tienda.panel');

// Confirmación de pago MP (antes de la ruta pública {slug})
Route::get('/tienda/{slug}/pedido/{pedido}/confirmacion', function ($slug, PedidoWeb $pedido) {
    $comercio = \App\Models\Comercio::where('slug', $slug)->firstOrFail();
    if ($pedido->comercio_id !== $comercio->id) abort(404);

    session(['ultima_tienda_slug' => $slug]);
    session(['comercio_id_actual' => $comercio->id]);

    return Inertia::render('Cliente/Confirmacion', [
        'comercio'      => $comercio,
        'pedido'        => $pedido->load('items.producto'),
        'status_inicial'=> request('status', 'pending'),
        'tienda_slug'   => $slug,
    ]);
})->name('tienda.pedido.confirmacion');

// Polling de estado de pago (liviano, solo strings)
Route::get('/api/tienda/pedido/{pedido}/estado', function (PedidoWeb $pedido) {
    if ($pedido->comercio_id !== (int) request('comercio_id')) {
        abort(404);
    }
    return response()->json([
        'estado_pago'   => $pedido->estado_pago,
        'estado_pedido' => $pedido->estado_pedido,
    ]);
})->name('api.pedido.estado');

// Tienda pública (slug catch-all debe ir último)
Route::get('/tienda/{slug}', \App\Http\Controllers\TiendaController::class)->name('tienda.publica');

// Webhook MercadoPago (sin CSRF ni auth, MP envía desde sus servidores)
Route::post('/api/mercadopago/notificacion', [\App\Http\Controllers\MercadoPagoNotificacionController::class, 'notificacion'])
    ->middleware('throttle:30,1')
    ->name('mercadopago.notificacion');

// Login y registro como páginas dedicadas
Route::get('/tienda/{slug}/login', function ($slug) {
    $comercio = \App\Models\Comercio::where('slug', $slug)->firstOrFail();
    session(['comercio_id_actual' => $comercio->id]);
    session(['ultima_tienda_slug' => $slug]);

    return Inertia::render('Cliente/Login', [
        'comercio'    => $comercio,
        'tienda_slug' => $slug,
    ]);
})->name('tienda.login');

Route::get('/tienda/{slug}/register', function ($slug) {
    $comercio = \App\Models\Comercio::where('slug', $slug)->firstOrFail();
    session(['comercio_id_actual' => $comercio->id]);
    session(['ultima_tienda_slug' => $slug]);

    return Inertia::render('Cliente/Register', [
        'comercio'    => $comercio,
        'tienda_slug' => $slug,
    ]);
})->name('tienda.register');


// ==========================================
// RUTA DE INICIO PARA CLIENTES (evita bucle de redirección)
// ==========================================
Route::get('/cliente/inicio', function () {
    return Inertia::render('Cliente/Inicio', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('cliente.inicio');

Route::get('/', function () {
    $planes = Plan::where('activo', true)
        ->orderBy('orden')
        ->orderBy('precio_mensual')
        ->get();

    return Inertia::render('LandingPage', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'planes' => $planes,
    ]);
});

require __DIR__.'/auth.php';