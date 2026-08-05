<?php

namespace App\Facturacion\Domain\ValueObjects;

/**
 * Estados del módulo fiscal por comercio (arquitectura §15.1).
 *
 * Secuencia normal: sin_datos → datos_cargados → datos_validados → cert_cargado →
 * pv_habilitado → listo_para_facturar.
 *
 * Estados de falla (recuperables): cuit_inactivo, condicion_discrepante,
 * certificado_vencido, desincronizado_arca, error_integracion.
 *
 * Estado de exclusión (terminal, sin remediación en el MVP): no_soportado
 * (emisor monotributista, §1.2).
 */
enum EstadoModuloFiscal: string
{
    case SIN_DATOS = 'sin_datos';
    case DATOS_CARGADOS = 'datos_cargados';
    case DATOS_VALIDADOS = 'datos_validados';
    case CERT_CARGADO = 'cert_cargado';
    case PV_HABILITADO = 'pv_habilitado';
    case LISTO_PARA_FACTURAR = 'listo_para_facturar';

    case CUIT_INACTIVO = 'cuit_inactivo';
    case CONDICION_DISCREPANTE = 'condicion_discrepante';
    case CERTIFICADO_VENCIDO = 'certificado_vencido';
    case DESINCRONIZADO_ARCA = 'desincronizado_arca';
    case ERROR_INTEGRACION = 'error_integracion';

    case NO_SOPORTADO = 'no_soportado';

    public function esListoParaFacturar(): bool
    {
        return $this === self::LISTO_PARA_FACTURAR;
    }

    public function esFalla(): bool
    {
        return in_array($this, [
            self::CUIT_INACTIVO,
            self::CONDICION_DISCREPANTE,
            self::CERTIFICADO_VENCIDO,
            self::DESINCRONIZADO_ARCA,
            self::ERROR_INTEGRACION,
        ], true);
    }

    public function esTerminal(): bool
    {
        return $this === self::NO_SOPORTADO;
    }
}
