<?php

namespace App\Facturacion\Infrastructure\Arca\Entorno;

use App\Facturacion\Infrastructure\Arca\Exceptions\EntornoRestringidoException;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * La homologación es un entorno de prueba de ARCA: queda habilitada para el
 * dueño del comercio (SuperAdmin) y para Administración Global / Desarrollo.
 * Producción no se restringe aquí: su control vive en el wizard (requiere CUIT
 * verificado previamente, arquitectura §13.1 y §16).
 */
final class HabilitadorHomologacion
{
    public const ROL_ADMIN_GLOBAL = 'Administrador Global';

    public const ROL_SUPER_ADMIN = 'SuperAdmin';

    public function verificar(string|EntornoArca $entorno, ?Authenticatable $usuario = null): void
    {
        $entorno = $entorno instanceof EntornoArca ? $entorno : EntornoArca::desde($entorno);

        if ($entorno !== EntornoArca::HOMOLOGACION) {
            return;
        }

        $usuario ??= auth()->user();

        if (! $this->puedeUsarHomologacion($usuario)) {
            throw new EntornoRestringidoException(
                'El entorno de homologación está reservado al dueño del comercio (SuperAdmin) y a Administración Global y Desarrollo.'
            );
        }
    }

    private function puedeUsarHomologacion(?Authenticatable $usuario): bool
    {
        if ($usuario === null) {
            return false;
        }

        return in_array(HasRoles::class, class_uses_recursive($usuario), true)
            && method_exists($usuario, 'hasRole')
            && $usuario->hasRole([self::ROL_ADMIN_GLOBAL, self::ROL_SUPER_ADMIN]);
    }
}
