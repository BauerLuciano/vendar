<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
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
        $pasos = [
            'comercio'     => $this->pasoComercio(),
            'sucursal'     => $this->pasoSucursal(),
            'caja'         => $this->pasoCaja(),
            'categoria'    => $this->pasoCategoria(),
            'marca'        => $this->pasoMarca(),
            'producto'     => $this->pasoProducto(),
            'turno'        => $this->pasoTurno(),
        ];

        $completados = collect($pasos)->where('completado', true)->count();
        $total = count($pasos);
        $porcentaje = $total > 0 ? round(($completados / $total) * 100) : 0;

        return [
            'pasos'       => $pasos,
            'completados' => $completados,
            'total'       => $total,
            'porcentaje'  => $porcentaje,
            'completo'    => $completados === $total,
        ];
    }

    public function pasoComercio(): array
    {
        $user = auth()->user();
        $comercio = $user->comercio ?? $user->branch?->comercio;

        $completado = $comercio && $comercio->nombre;

        return [
            'id'          => 'comercio',
            'titulo'      => 'Tu Comercio',
            'descripcion' => 'Datos básicos de tu negocio',
            'completado'  => $completado,
            'url'         => route('dashboard'),
            'icono'       => 'tienda',
        ];
    }

    public function pasoSucursal(): array
    {
        $userId = $this->userId;
        if (!$userId) return $this->pasoNoCompletado('sucursal', 'Tu Sucursal', 'Creá la sucursal donde vas a vender', 'sucursal');

        $sucursales = Sucursal::where('comercio_id', $this->comercioId)
            ->where('tipo', 'punto_de_venta')
            ->count();

        return [
            'id'          => 'sucursal',
            'titulo'      => 'Tu Sucursal',
            'descripcion' => 'El lugar donde vas a operar',
            'completado'  => $sucursales > 0,
            'url'         => route('sucursales.index'),
            'icono'       => 'sucursal',
            'extra'       => "$sucursales sucursal(es) creada(s)",
        ];
    }

    public function pasoCaja(): array
    {
        $sucursalId = $this->sucursalActivaId();
        if (!$sucursalId) return $this->pasoNoCompletado('caja', 'Tu Caja', 'Necesitás una caja para cobrar', 'caja');

        $cajas = Caja::where('sucursal_id', $sucursalId)->count();

        return [
            'id'          => 'caja',
            'titulo'      => 'Tu Caja',
            'descripcion' => 'Necesitás una caja para cobrar',
            'completado'  => $cajas > 0,
            'url'         => route('cajas.index'),
            'icono'       => 'caja',
            'extra'       => "$cajas caja(s) creada(s)",
        ];
    }

    public function pasoCategoria(): array
    {
        $categorias = Categoria::deComercio($this->comercioId)->count();

        return [
            'id'          => 'categoria',
            'titulo'      => 'Categorías',
            'descripcion' => 'Organizá tus productos',
            'completado'  => $categorias > 0,
            'url'         => route('categorias.index'),
            'icono'       => 'lista',
            'extra'       => "$categorias categoría(s) creada(s)",
        ];
    }

    public function pasoMarca(): array
    {
        $marcas = Marca::deComercio($this->comercioId)->count();

        return [
            'id'          => 'marca',
            'titulo'      => 'Marcas',
            'descripcion' => 'Identificá tus productos',
            'completado'  => $marcas > 0,
            'url'         => route('marcas.index'),
            'icono'       => 'tag',
            'extra'       => "$marcas marca(s) creada(s)",
        ];
    }

    public function pasoProducto(): array
    {
        $sucursalId = $this->sucursalActivaId();
        $productos = Producto::where('estado', true)
            ->when($sucursalId, fn ($q) => $q->whereHas('sucursales', fn ($sq) => $sq->where('sucursal_id', $sucursalId)))
            ->count();

        return [
            'id'          => 'producto',
            'titulo'      => 'Productos',
            'descripcion' => 'Agregá tu primer producto para vender',
            'completado'  => $productos > 0,
            'url'         => route('productos.index'),
            'icono'       => 'producto',
            'extra'       => "$productos producto(s) disponible(s)",
        ];
    }

    public function pasoTurno(): array
    {
        $userId = $this->userId;
        if (!$userId) return $this->pasoNoCompletado('turno', 'Abrir Turno', 'Abrí un turno para empezar a vender', 'turno');

        $turnoAbierto = TurnoCaja::where('user_id', $userId)
            ->where('estado', 'Abierto')
            ->exists();

        $turnosTotales = TurnoCaja::where('user_id', $userId)->count();

        return [
            'id'          => 'turno',
            'titulo'      => 'Abrir Turno',
            'descripcion' => 'Abrí un turno para empezar a vender',
            'completado'  => $turnosTotales > 0,
            'url'         => route('pos.index'),
            'icono'       => 'turno',
            'extra'       => $turnoAbierto ? 'Turno abierto activo' : ($turnosTotales > 0 ? 'Turnos anteriores' : 'Sin turnos'),
        ];
    }

    public function primerPasoPendiente(): ?array
    {
        $pasos = $this->estado()['pasos'];
        foreach ($pasos as $paso) {
            if (!$paso['completado']) return $paso;
        }
        return null;
    }

    private function pasoNoCompletado(string $id, string $titulo, string $descripcion, string $icono): array
    {
        return [
            'id'          => $id,
            'titulo'      => $titulo,
            'descripcion' => $descripcion,
            'completado'  => false,
            'url'         => route('dashboard'),
            'icono'       => $icono,
        ];
    }

    private function sucursalActivaId(): ?int
    {
        return session('sucursal_activa_id', auth()->user()?->branch_id);
    }
}
