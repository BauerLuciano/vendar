<?php

namespace App\Facturacion\Infrastructure\Arca\Entorno;

use App\Facturacion\Infrastructure\Arca\Exceptions\EntornoRestringidoException;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * La homologación es exclusiva de Administración Global / Desarrollo y nunca
 * puede quedar habilitada para un comercio en producción (arquitectura §13.1 y §16).
 */
final class HabilitadorHomologacion
{
    public const ROL_ADMIN_GLOBAL = 'Administrador Global';

    public function verificar(string|EntornoArca $entorno, ?Authenticatable $usuario = null): void
    {
        $entorno = $entorno instanceof EntornoArca ? $entorno : EntornoArca::desde($entorno);

        if ($entorno !== EntornoArca::HOMOLOGACION) {
            return;
        }

        $usuario ??= auth()->user();

        if (! $this->esAdministradorGlobal($usuario)) {
            throw new EntornoRestringidoException(
                'El entorno de homologación está reservado a Administración Global y Desarrollo.'
            );
        }
    }

    private function esAdministradorGlobal(?Authenticatable $usuario): bool
    {
        if ($usuario === null) {
            return false;
        }

        return in_array(HasRoles::class, class_uses_recursive($usuario), true)
            && method_exists($usuario, 'hasRole')
            && $usuario->hasRole(self::ROL_ADMIN_GLOBAL);
    }
}
