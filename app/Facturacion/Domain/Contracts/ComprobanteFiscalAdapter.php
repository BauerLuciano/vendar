<?php

namespace App\Facturacion\Domain\Contracts;

/**
 * Interfaz de integración con el comprobante fiscal (emisión vía WSFE).
 * Abstrae el servicio vigente (v4 / RG 5616/2024) detrás del dominio: un cambio
 * de versión de ARCA toca únicamente Infrastructure/Arca (arquitectura §14.2).
 */
interface ComprobanteFiscalAdapter extends Wsfet {}
