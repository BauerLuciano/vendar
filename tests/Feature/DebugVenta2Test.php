<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venta;
use Tests\TestCaseMultiTenant;

class DebugVenta2Test extends TestCaseMultiTenant
{
    public function test_debug_venta_cancel(): void
    {
        $this->actingAsAdminA();

        $user = User::where('email', 'admin.a@test.com')->first();
        dump('is_active:', $user->is_active);
        dump('branch_id:', $user->branch_id);
        $sucursal = $user->branch;
        dump('sucursal:', $sucursal?->toArray());
        dump('comercio:', $sucursal?->comercio?->toArray());

        $venta = Venta::with('turno.caja.sucursal')->find(3);
        dump('venta turno caja sucursal:', $venta?->turno?->caja?->sucursal?->toArray());

        $comercioId = auth()->user()->branch?->comercio_id;
        dump('comercioId:', $comercioId);

        $existe = Venta::where('id', 3)
            ->whereHas('turno.caja.sucursal', fn ($q) => $q->where('comercio_id', $comercioId))
            ->exists();
        dump('existe en mi comercio:', $existe);

        $response = $this->patch('/ventas/3/cancelar', [
            'motivo' => 'Test cross-tenant',
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }
}
