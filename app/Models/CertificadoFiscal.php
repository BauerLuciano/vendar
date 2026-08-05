<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class CertificadoFiscal extends Model
{
    use Auditable;

    protected $table = 'certificados_fiscales';

    protected $fillable = [
        'comercio_id',
        'entorno',
        'archivo_pfx',
        'password_pfx',
        'distinguished_name',
        'numero_serie',
        'vigencia_desde',
        'vigencia_hasta',
    ];

    protected $casts = [
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
    ];

    public function comercio()
    {
        return $this->belongsTo(Comercio::class);
    }

    public function configuracion()
    {
        return $this->hasOne(ConfiguracionFiscalComercio::class, 'certificado_id');
    }

    public function getVencidoAttribute(): bool
    {
        return $this->vigencia_hasta !== null && $this->vigencia_hasta->isPast();
    }
}
