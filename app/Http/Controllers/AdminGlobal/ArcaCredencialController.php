<?php

namespace App\Http\Controllers\AdminGlobal;

use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Infrastructure\Arca\Cifrado\CredencialPlataformaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

/**
 * Credencial de plataforma del padrón ARCA (Administración Global).
 *
 * La credencial es propiedad de VendAR (arquitectura §14.3/§14.4): solo la
 * puede configurar el Administrador Global, se guarda cifrada en la tabla de
 * configuración global y nunca se expone al comercio (invariante 10).
 */
class ArcaCredencialController extends Controller
{
    public function __construct(private CredencialPlataformaService $credencial) {}

    public function index()
    {
        $cred = $this->credencial->leer();

        return Inertia::render('AdminGlobal/ArcaCredencial', [
            'configurada' => $cred !== null,
            'cuit' => $cred !== null ? $this->enmascararCuit($cred->cuit->valor()) : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cuit' => ['required', 'string', function (string $attribute, $value, $fail) {
                if (! Cuit::esValido($value)) {
                    $fail('El CUIT no tiene un dígito verificador válido.');
                }
            }],
            'token' => ['required', 'string', 'min:10'],
            'sign' => ['required', 'string', 'min:10'],
        ]);

        try {
            $this->credencial->guardar(
                new Cuit($validated['cuit']),
                trim($validated['token']),
                trim($validated['sign']),
            );
        } catch (Throwable $e) {
            return back()->withErrors(['credencial' => 'No se pudo guardar la credencial: '.$e->getMessage()]);
        }

        return back()->with('success', 'Credencial de plataforma configurada correctamente.');
    }

    private function enmascararCuit(string $cuit): string
    {
        if (strlen($cuit) !== 11) {
            return $cuit;
        }

        return substr($cuit, 0, 2)
            .'-'
            .str_repeat('•', 8)
            .'-'
            .substr($cuit, 10, 1);
    }
}
