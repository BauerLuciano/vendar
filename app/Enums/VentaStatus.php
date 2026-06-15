<?php

namespace App\Enums;

enum VentaStatus: string
{
    case PENDING = 'Pendiente';
    case COMPLETED = 'Completada';
    case CANCELLED = 'Cancelada';
}
