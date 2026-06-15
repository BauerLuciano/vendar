<?php

namespace App\Enums;

enum PaymentChannel: string
{
    case MANUAL = 'manual';
    case API = 'api';
    case QR = 'qr';
    case POINT = 'point';
}
