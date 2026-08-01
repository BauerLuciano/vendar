<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;

class OnboardingService
{
    private ?array $estadoCache = null;
    private ?int $comercioId = null;
    private ?int $userId = null;

    public function __construct()
    {
        $user = auth()->user();
        if ($user) {
            $this->userId = $user->id;
            $this->comercioId = $user->comercio_id ?? $user->branch?->comercio_id;
        }
    }

    public function estado(): array
    {
        if ($this->estadoCache !== null) {
            return $this->estadoCache;
        }

        $pasos = [
            'comercio'       => $this->pasoComercio(),
            'sucursal'       => $this->pasoSucursal(),
            'caja'           => $this->pasoCaja(),
            'categoria'      => $this->pasoCategoria(),
            'proveedor'      => $this->pasoProveedor(),
            'marca'          => $this->pasoMarca(),
            'producto'       => $this->pasoProducto(),
            'ajustes'        => $this->pasoAjustesGlobales(),
            'turno'          => $this->pasoTurno(),
        ];

        $requeridos = collect($pasos)->reject(fn ($p) => $p['opcional']);
        $completados = $requeridos->where('completado', true)->count();
        $totalRequeridos = $requeridos->count();
        $totalVisibles = count($pasos);
        $porcentaje = $totalRequeridos > 0 ? round(($completados / $totalRequeridos) * 100) : 0;

        $this->estadoCache = [
            'pasos'            => array_values($pasos),
            'completados'      => $completados,
            'total'            => $totalVisibles,
            'total_requeridos' => $totalRequeridos,
            'porcentaje'       => $porcentaje,
            'completo'         => $completados === $totalRequeridos,
        ];

        return $this->estadoCache;
    }

    public function pasoComercio(): array
    {
        $user = auth()->user();
        $comercio = $user->comercio ?? $user->branch?->comercio;

        $completado = $comercio && $comercio->nombre;

        return [
            'id'            => 'comercio',
            'titulo'        => 'Tu Comercio',
            'descripcion'   => 'Datos de tu negocio',
            'instrucciones' => 'Completá el nombre, logo, dirección y datos fiscales de tu negocio en Configuración.',
            'completado'    => $completado,
            'url'           => route('configuracion.index'),
            'ruta'          => 'configuracion.index',
            'icono'         => 'tienda',
            'opcional'      => false,
        ];
    }

    public function pasoSucursal(): array
    {
        $comercioId = $this->comercioId;
        if (!$comercioId) return $this->pasoNoCompletado('sucursal', 'Tu Sucursal', 'El lugar donde vas a operar', 'Necesitás crear una sucursal para asignar tus productos y cajas.', 'sucursal', 'sucursales.index');

        $sucursales = Sucursal::where('comercio_id', $comercioId)
            ->where('tipo', 'punto_de_venta')
            ->count();

        return [
            'id'            => 'sucursal',
            'titulo'        => 'Tu Sucursal',
            'descripcion'   => 'El lugar donde vas a operar',
            'instrucciones' => 'Creá la sucursal donde vas a vender. Indicá dirección, teléfono y si tiene delivery.',
            'completado'    => $sucursales > 0,
            'url'           => route('sucursales.index'),
            'ruta'          => 'sucursales.index',
            'icono'         => 'sucursal',
            'extra'         => "$sucursales sucursal(es) creada(s)",
            'opcional'      => false,
        ];
    }

    public function pasoCaja(): array
    {
        $sucursalId = $this->sucursalActivaId();
        if (!$sucursalId) return $this->pasoNoCompletado('caja', 'Tu Caja', 'Necesitás una caja para cobrar', 'Sin una sucursal activa no podés crear cajas. Completá primero el paso anterior.', 'caja', 'cajas.index');

        $cajas = Caja::where('sucursal_id', $sucursalId)->count();

        return [
            'id'            => 'caja',
            'titulo'        => 'Tu Caja',
            'descripcion'   => 'Necesitás una caja para cobrar',
            'instrucciones' => 'Creá al menos una caja en tu sucursal. Cada caja puede tener un nombre (ej: "Caja Principal", "Caja 2").',
            'completado'    => $cajas > 0,
            'url'           => route('cajas.index'),
            'ruta'          => 'cajas.index',
            'icono'         => 'caja',
            'extra'         => "$cajas caja(s) creada(s)",
            'opcional'      => false,
        ];
    }

    public function pasoCategoria(): array
    {
        $categorias = Categoria::deComercio($this->comercioId)->count();

        return [
            'id'            => 'categoria',
            'titulo'        => 'Categorías',
            'descripcion'   => 'Organizá tus productos',
            'instrucciones' => 'Creá las categorías que usarás para agrupar tus productos (ej: Bebidas, Snacks, Limpieza, Lacteos).',
            'completado'    => $categorias > 0,
            'url'           => route('categorias.index'),
            'ruta'          => 'categorias.index',
            'icono'         => 'lista',
            'extra'         => "$categorias categoría(s) creada(s)",
            'opcional'      => false,
        ];
    }

    public function pasoProveedor(): array
    {
        $proveedores = Proveedor::deComercio($this->comercioId)->count();

        return [
            'id'            => 'proveedor',
            'titulo'        => 'Proveedores',
            'descripcion'   => 'Opcional: vinculá tus proveedores',
            'instrucciones' => 'Registrá tus proveedores habituales con sus datos de contacto y CUIT. Si tenés productos sin marca ni proveedor, podés saltear este paso.',
            'completado'    => $proveedores > 0,
            'url'           => route('proveedores.index'),
            'ruta'          => 'proveedores.index',
            'icono'         => 'proveedor',
            'extra'         => "$proveedores proveedor(es) registrado(s)",
            'opcional'      => true,
        ];
    }

    public function pasoMarca(): array
    {
        $marcas = Marca::deComercio($this->comercioId)->count();

        return [
            'id'            => 'marca',
            'titulo'        => 'Marcas',
            'descripcion'   => 'Identificá tus productos',
            'instrucciones' => 'Creá las marcas que venden tus productos (ej: Coca-Cola, Pepsi, La Serenísima, Milka).',
            'completado'    => $marcas > 0,
            'url'           => route('marcas.index'),
            'ruta'          => 'marcas.index',
            'icono'         => 'tag',
            'extra'         => "$marcas marca(s) creada(s)",
            'opcional'      => false,
        ];
    }

    public function pasoProducto(): array
    {
        $sucursalId = $this->sucursalActivaId();
        $productos = Producto::where('estado', true)
            ->when($sucursalId, fn ($q) => $q->whereHas('sucursales', fn ($sq) => $sq->where('sucursal_id', $sucursalId)))
            ->count();

        return [
            'id'            => 'producto',
            'titulo'        => 'Productos',
            'descripcion'   => 'Agregá tu primer producto para vender',
            'instrucciones' => 'Agregá al menos un producto con su precio, código de barras y stock. Cargá todo lo que vendés.',
            'completado'    => $productos > 0,
            'url'           => route('productos.index'),
            'ruta'          => 'productos.index',
            'icono'         => 'producto',
            'extra'         => "$productos producto(s) disponible(s)",
            'opcional'      => false,
        ];
    }

    public function pasoAjustesGlobales(): array
    {
        $comercio = auth()->user()->comercio ?? auth()->user()->branch?->comercio;

        $tieneCbu = $comercio && filled($comercio->transferencia_cbu);
        $tieneMetodoPago = false;

        if ($comercio) {
            $tieneMetodoPago = \App\Models\PaymentMethodConfiguration::where('comercio_id', $comercio->id)
                ->where('enabled', true)
                ->exists();
        }

        $completado = $tieneCbu || $tieneMetodoPago;

        return [
            'id'            => 'ajustes',
            'titulo'        => 'Ajustes Globales',
            'descripcion'   => 'Opcional: medios de pago y configuración',
            'instrucciones' => 'Configurá medios de pago (transferencia, CBU/CVU) y los datos de tu tienda online. Podés completarlo después.',
            'completado'    => $completado,
            'url'           => route('configuracion.index'),
            'ruta'          => 'configuracion.index',
            'icono'         => 'ajustes',
            'extra'         => $tieneCbu ? 'CBU configurado' : ($tieneMetodoPago ? 'Medio de pago POS configurado' : 'Sin configurar'),
            'opcional'      => true,
        ];
    }

    public function pasoTurno(): array
    {
        $userId = $this->userId;
        if (!$userId) return $this->pasoNoCompletado('turno', 'Abrir Turno', 'Iniciá tu primera jornada', 'Abrí un turno para habilitar la caja y empezar a cobrar.', 'turno');

        $turnoAbierto = TurnoCaja::where('user_id', $userId)
            ->where('estado', 'Abierto')
            ->exists();

        $turnosTotales = TurnoCaja::where('user_id', $userId)->count();

        return [
            'id'            => 'turno',
            'titulo'        => 'Abrir Turno',
            'descripcion'   => 'Iniciá tu primera jornada',
            'instrucciones' => 'Andá al POS y abrí un turno. Esto habilita la caja para empezar a cobrar. Necesitás una caja creada primero.',
            'completado'    => $turnosTotales > 0,
            'url'           => route('pos.index'),
            'ruta'          => 'pos.index',
            'icono'         => 'turno',
            'extra'         => $turnoAbierto ? 'Turno abierto activo' : ($turnosTotales > 0 ? 'Turnos anteriores' : 'Sin turnos'),
            'opcional'      => false,
        ];
    }

    public function primerPasoPendiente(): ?array
    {
        $pasos = $this->estado()['pasos'];
        foreach ($pasos as $paso) {
            if (!$paso['completado'] && !$paso['opcional']) return $paso;
        }
        return null;
    }

    public function pasoPorId(string $id): ?array
    {
        $pasos = $this->estado()['pasos'];
        foreach ($pasos as $paso) {
            if ($paso['id'] === $id) return $paso;
        }
        return null;
    }

    private function pasoNoCompletado(string $id, string $titulo, string $descripcion, string $instrucciones, string $icono, string $ruta = 'configuracion.index'): array
    {
        return [
            'id'            => $id,
            'titulo'        => $titulo,
            'descripcion'   => $descripcion,
            'instrucciones' => $instrucciones,
            'completado'    => false,
            'url'           => route($ruta),
            'ruta'          => $ruta,
            'icono'         => $icono,
        ];
    }

    private function sucursalActivaId(): ?int
    {
        return session('sucursal_activa_id', auth()->user()?->branch_id);
    }
}
