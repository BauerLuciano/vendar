<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionFiscalComercio extends Model
{
    use Auditable;

    protected $table = 'configuracion_fiscal_comercios';

    protected $fillable = [
        'comercio_id',
        'cuit',
        'razon_social',
        'condicion_fiscal',
        'domicilio_fiscal',
        'entorno',
        'punto_venta_activo',
        'estado_modulo',
        'certificado_id',
        'alicuota_iva_recargo',
    ];

    protected $casts = [
        'punto_venta_activo' => 'integer',
        'alicuota_iva_recargo' => 'decimal:2',
    ];

    public function comercio()
    {
        return $this->belongsTo(Comercio::class);
    }

    public function certificado()
    {
        return $this->belongsTo(CertificadoFiscal::class, 'certificado_id');
    }

    public function getListaParaFacturarAttribute(): bool
    {
        return $this->estado_modulo === 'listo_para_facturar';
    }
}
