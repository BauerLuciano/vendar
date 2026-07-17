<?php

namespace App\Services;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Servicio centralizado de resolución de permisos por sucursal.
 *
 * Responsabilidades:
 * - Determinar la sucursal activa del usuario autenticado.
 * - Obtener las sucursales a las que el usuario tiene acceso según su rol.
 * - Validar acceso a una sucursal específica.
 * - Identificar el rol del usuario (AdminGlobal, SuperAdmin, Encargado, Cajero).
 *
 * Política de permisos:
 * - Administrador Global: accede a cualquier comercio, sin restricción por sucursal.
 * - SuperAdmin (dueño del comercio): accede a TODAS las sucursales de SU comercio.
 * - Encargado: opera únicamente sobre la sucursal activa.
 * - Cajero: opera únicamente sobre la sucursal activa.
 *
 * La sucursal activa se resuelve con:
 *   1. session('sucursal_activa_id')
 *   2. Fallback: $user->branch_id
 */
class SucursalScopeService
{
    private ?User $user = null;

    /**
     * Establece el usuario para operaciones manuales.
     * Si no se llama, se usa auth()->user() automáticamente.
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Obtiene el usuario activo (auth o el establecido manualmente).
     */
    public function usuario(): User
    {
        return $this->user ?? auth()->user();
    }

    // =====================================================================
    // IDENTIFICACIÓN DE ROL
    // =====================================================================

    /**
     * Indica si el usuario es Administrador Global (desarrolladores del sistema).
     */
    public function esAdminGlobal(): bool
    {
        return $this->usuario()->hasRole('Administrador Global');
    }

    /**
     * Indica si el usuario es SuperAdmin (dueño del comercio).
     */
    public function esSuperAdmin(): bool
    {
        return $this->usuario()->hasRole('SuperAdmin');
    }

    /**
     * Indica si el usuario tiene permisos de jefe (SuperAdmin o Administrador Global).
     * Equivalente al patrón $esJefe usado en HandleInertiaRequests y DashboardController.
     */
    public function esJefe(): bool
    {
        return $this->esSuperAdmin() || $this->esAdminGlobal();
    }

    /**
     * Indica si el usuario es Encargado.
     */
    public function esEncargado(): bool
    {
        return $this->usuario()->hasRole('Encargado');
    }

    /**
     * Indica si el usuario es Cajero.
     */
    public function esCajero(): bool
    {
        return $this->usuario()->hasRole('Cajero');
    }

    /**
     * Indica si el usuario opera a nivel de sucursal (Encargado o Cajero).
     */
    public function operaPorSucursal(): bool
    {
        return $this->esEncargado() || $this->esCajero();
    }

    // =====================================================================
    // SUCURSAL ACTIVA
    // =====================================================================

    /**
     * Obtiene el ID de la sucursal activa del usuario.
     *
     * Resolución:
     *   1. session('sucursal_activa_id') — sucursal elegida por el usuario
     *   2. $user->branch_id — sucursal "hogar" asignada
     *
     * @return int|null ID de la sucursal activa, o null si no tiene ninguna.
     */
    public function obtenerSucursalActiva(): ?int
    {
        $user = $this->usuario();

        $sucursalId = session('sucursal_activa_id', $user->branch_id);

        return $sucursalId ? (int) $sucursalId : null;
    }

    // =====================================================================
    // SUCURSALES PERMITIDAS
    // =====================================================================

    /**
     * Obtiene todas las sucursales a las que el usuario puede acceder.
     *
     * Comportamiento según rol:
     * - Administrador Global: todas las sucursales (se retorna colección vacía
     *   para indicar que no hay restricción por sucursal; usar esAdminGlobal()).
     * - SuperAdmin: todas las sucursales de su comercio.
     * - Encargado/Cajero: solo la sucursal activa.
     *
     * @return Collection<Sucursal>
     */
    public function obtenerSucursalesPermitidas(): Collection
    {
        $user = $this->usuario();

        if ($this->esAdminGlobal()) {
            // AdminGlobal no tiene restricción por sucursal.
            // Se retorna colección vacía como señal de "sin filtro".
            // Los controllers que necesiten filtering por comercio
            // deben usar obtenerTodasLasSucursales() explícitamente.
            return collect();
        }

        if ($this->esSuperAdmin() && $user->comercio) {
            return $user->comercio->sucursales()->get();
        }

        // Encargado o Cajero: solo la sucursal activa
        $sucursalActiva = $this->obtenerSucursalActiva();
        if ($sucursalActiva) {
            $sucursal = Sucursal::find($sucursalActiva);
            return $sucursal ? collect([$sucursal]) : collect();
        }

        return collect();
    }

    /**
     * Obtiene solo los IDs de las sucursales permitidas.
     *
     * @return array<int> IDs de sucursales, o array vacío si AdminGlobal (sin restricción).
     */
    public function obtenerSucursalesPermitidasIds(): array
    {
        return $this->obtenerSucursalesPermitidas()
            ->pluck('id')
            ->toArray();
    }

    /**
     * Obtiene todas las sucursales del comercio del usuario (sin importar su rol).
     * Útil para módulos donde se necesitan las sucursales del comercio
     * independientemente de quién esté logueado.
     *
     * @return Collection<Sucursal>
     */
    public function obtenerSucursalesDelComercio(): Collection
    {
        $user = $this->usuario();
        $comercio = $user->comercio;

        if (!$comercio) {
            return collect();
        }

        return $comercio->sucursales()->get();
    }

    /**
     * Obtiene los IDs de todas las sucursales del comercio.
     *
     * @return array<int>
     */
    public function obtenerSucursalesDelComercioIds(): array
    {
        return $this->obtenerSucursalesDelComercio()
            ->pluck('id')
            ->toArray();
    }

    // =====================================================================
    // VALIDACIÓN DE ACCESO
    // =====================================================================

    /**
     * Verifica si el usuario puede acceder a una sucursal específica.
     *
     * @param int $sucursalId ID de la sucursal a validar.
     * @return bool true si el usuario tiene acceso.
     */
    public function puedeAccederSucursal(int $sucursalId): bool
    {
        $user = $this->usuario();

        if ($this->esAdminGlobal()) {
            return true;
        }

        if ($this->esSuperAdmin()) {
            return Sucursal::where('id', $sucursalId)
                ->where('comercio_id', $user->comercio_id)
                ->exists();
        }

        // Encargado o Cajero: solo su sucursal activa
        return (int) $this->obtenerSucursalActiva() === $sucursalId;
    }

    // =====================================================================
    // CONTEXTO DEL COMERCIO
    // =====================================================================

    /**
     * Obtiene el ID del comercio al que pertenece el usuario.
     *
     * @return int|null
     */
    public function obtenerComercioId(): ?int
    {
        $user = $this->usuario();
        return $user->comercio_id ?? $user->branch?->comercio_id;
    }

    /**
     * Indica si el usuario tiene un comercio asociado.
     */
    public function tieneComercio(): bool
    {
        return $this->obtenerComercioId() !== null;
    }

    // =====================================================================
    // HELPERS DE CONSULTA (para usar en controllers)
    // =====================================================================

    /**
     * Aplica el filtro de sucursal a una query Eloquent/D query builder.
     *
     * Si el usuario es AdminGlobal, no aplica filtro (retorna la query sin modificar).
     * Si es SuperAdmin, filtra por todas las sucursales del comercio.
     * Si es Encargado/Cajero, filtra por la sucursal activa.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param string $columna Nombre de la columna que contiene el sucursal_id (default: 'sucursal_id')
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function aplicarFiltroSucursal($query, string $columna = 'sucursal_id')
    {
        if ($this->esAdminGlobal()) {
            return $query;
        }

        if ($this->esJefe()) {
            $ids = $this->obtenerSucursalesDelComercioIds();
            return empty($ids) ? $query->whereRaw('1 = 0') : $query->whereIn($columna, $ids);
        }

        // Encargado o Cajero: solo sucursal activa
        $sucursalActiva = $this->obtenerSucursalActiva();
        if ($sucursalActiva) {
            return $query->where($columna, $sucursalActiva);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Aplica filtro de comercio a una query (para datos compartidos como Clientes).
     *
     * Si el usuario es AdminGlobal, no aplica filtro.
     * Para otros roles, filtra por comercio_id.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param string $columna Nombre de la columna que contiene el comercio_id (default: 'comercio_id')
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function aplicarFiltroComercio($query, string $columna = 'comercio_id')
    {
        if ($this->esAdminGlobal()) {
            return $query;
        }

        $comercioId = $this->obtenerComercioId();
        if ($comercioId) {
            return $query->where($columna, $comercioId);
        }

        return $query->whereRaw('1 = 0');
    }
}
