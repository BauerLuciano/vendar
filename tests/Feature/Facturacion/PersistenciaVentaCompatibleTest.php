<?php

namespace Tests\Feature\Facturacion;

use App\Models\Consumidor;
use App\Models\DetalleVenta;
use App\Models\Producto;
use Tests\TestCaseMultiTenant;

class PersistenciaVentaCompatibleTest extends TestCaseMultiTenant
{
    public function test_detalle_venta_se_inserta_sin_alicuota_iva(): void
    {
        $detalle = DetalleVenta::create([
            'venta_id' => 1,
            'producto_id' => 2,
            'cantidad' => 1,
            'precio_unitario' => 400,
            'subtotal' => 400,
        ]);

        $this->assertDatabaseHas('detalle_ventas', ['id' => $detalle->id]);
        $this->assertNull($detalle->fresh()->alicuota_iva);
    }

    public function test_producto_persiste_alicuota_iva(): void
    {
        $producto = Producto::find(1);
        $producto->update(['alicuota_iva' => 10.5]);

        $this->assertSame('10.50', $producto->fresh()->alicuota_iva);
    }

    public function test_consumidor_persiste_datos_fiscales(): void
    {
        $consumidor = Consumidor::find(2);
        $consumidor->update([
            'cuit' => '20111111119',
            'tipo_documento' => 'CUIT',
            'razon_social' => 'Juan Perez SA',
            'domicilio_fiscal' => 'Av. Test 123',
        ]);

        $fresh = $consumidor->fresh();
        $this->assertSame('20111111119', $fresh->cuit);
        $this->assertSame('CUIT', $fresh->tipo_documento);
        $this->assertSame('Juan Perez SA', $fresh->razon_social);
        $this->assertSame('Av. Test 123', $fresh->domicilio_fiscal);
    }
}
