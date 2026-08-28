<?php

namespace App\Http\Controllers\Facturacion;

use App\Facturacion\Application\WizardConfiguracionService;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\EstadoModuloFiscal;
use App\Http\Controllers\Controller;
use App\Models\CertificadoFiscal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

/**
 * Wizard de configuración fiscal por comercio (F7, arquitectura §13).
 * Controller delgado: delega la lógica en WizardConfiguracionService y solo
 * traduce requests/respuestas para las vistas Inertia.
 */
class WizardConfiguracionFiscalController extends Controller
{
    public function __construct(private WizardConfiguracionService $servicio) {}

    public function index(Request $request)
    {
        $comercioId = $this->comercioId($request);

        $configuracion = $this->servicio->buscarPorComercio($comercioId);
        $certificado = $configuracion?->certificadoId() !== null
            ? CertificadoFiscal::find($configuracion->certificadoId())
            : null;

        return Inertia::render('Facturacion/Wizard', [
            'configuracion' => $this->configuracionParaVista($configuracion),
            'certificado' => $this->certificadoParaVista($certificado),
            'puntosVenta' => $this->puntosVentaDisponibles($configuracion),
            'resultadoConexion' => session('facturacion.resultado_conexion.'.$comercioId),
        ]);
    }

    public function verificarCuit(Request $request)
    {
        $validated = $request->validate([
            'cuit' => ['required', 'string', function (string $attribute, $value, $fail) {
                if (! \App\Facturacion\Domain\ValueObjects\Cuit::esValido($value)) {
                    $fail('El CUIT no tiene un dígito verificador válido.');
                }
            }],
            'entorno' => ['required', 'in:produccion,homologacion'],
        ]);

        try {
            $configuracion = $this->servicio->verificarCuit(
                $this->comercioId($request),
                $validated['entorno'],
                $validated['cuit'],
                $request->user(),
            );

            if ($configuracion->estadoModulo()->esTerminal()) {
                return back()->withErrors([
                    'cuit' => 'El CUIT no corresponde a un Responsable Inscripto: el módulo no puede activarse para este comercio.',
                ]);
            }

            if ($configuracion->estadoModulo()->esFalla()) {
                return back()->withErrors([
                    'cuit' => $this->mensajeFalla($configuracion->estadoModulo()),
                ]);
            }

            return back()->with('success', 'CUIT verificado en el padrón de ARCA.');
        } catch (Throwable $e) {
            Log::warning('Wizard: error verificando CUIT', ['error' => $e->getMessage()]);

            return back()->withErrors(['cuit' => $e->getMessage()]);
        }
    }

    public function confirmarDatos(Request $request)
    {
        $validated = $request->validate([
            'domicilio_fiscal' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->servicio->confirmarDatos(
                $this->comercioId($request),
                $validated['domicilio_fiscal'] ?: null,
            );

            return back()->with('success', 'Datos del emisor confirmados.');
        } catch (Throwable $e) {
            return back()->withErrors(['datos' => $e->getMessage()]);
        }
    }

    public function cargarCertificado(Request $request)
    {
        $validated = $request->validate([
            'archivo_pfx' => [
                'required', 'file', 'max:4096',
                'mimetypes:application/x-pkcs12,application/pkcs12,application/x-pkcs12,application/octet-stream',
            ],
            'password_pfx' => ['required', 'string', 'min:1', 'max:255'],
        ], [
            'archivo_pfx.required' => 'Seleccioná el archivo .pfx del certificado.',
            'archivo_pfx.file' => 'El archivo del certificado no es válido.',
            'archivo_pfx.max' => 'El archivo .pfx no puede superar 4 MB.',
            'password_pfx.required' => 'Ingresá la contraseña del certificado.',
        ]);

        $pfx = file_get_contents($validated['archivo_pfx']->getRealPath());

        if ($pfx === false) {
            return back()->withErrors(['certificado' => 'No se pudo leer el archivo del certificado.']);
        }

        try {
            $configuracion = $this->servicio->cargarCertificado(
                $this->comercioId($request),
                $this->entornoDelComercio($request),
                $pfx,
                $validated['password_pfx'],
            );

            if ($configuracion->estadoModulo()->esFalla()) {
                return back()->withErrors([
                    'certificado' => $this->mensajeFalla($configuracion->estadoModulo()),
                ]);
            }

            return back()->with('success', 'Certificado cargado y encriptado.');
        } catch (Throwable $e) {
            Log::warning('Wizard: error cargando certificado', ['error' => $e->getMessage()]);

            return back()->withErrors(['certificado' => $e->getMessage()]);
        }
    }

    public function seleccionarPuntoVenta(Request $request)
    {
        $validated = $request->validate([
            'punto_venta' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->servicio->seleccionarPuntoVenta(
                $this->comercioId($request),
                $validated['punto_venta'],
            );

            return back()->with('success', 'Punto de venta seleccionado.');
        } catch (Throwable $e) {
            return back()->withErrors(['punto_venta' => $e->getMessage()]);
        }
    }

    public function probarConexion(Request $request)
    {
        try {
            $comercioId = $this->comercioId($request);
            $resultado = $this->servicio->probarConexion($comercioId);

            session()->flash('facturacion.resultado_conexion.'.$comercioId, $resultado);

            $ok = collect($resultado)->every(fn ($r) => $r['ok']);

            return back()->with($ok ? 'success' : 'error', $ok
                ? 'Conexión con ARCA verificada correctamente.'
                : 'Se detectaron problemas de conexión con ARCA.');
        } catch (Throwable $e) {
            Log::warning('Wizard: error probando conexión', ['error' => $e->getMessage()]);

            return back()->withErrors(['conexion' => $e->getMessage()]);
        }
    }

    public function activar(Request $request)
    {
        try {
            $configuracion = $this->servicio->activar($this->comercioId($request));

            if ($configuracion->estaListoParaFacturar()) {
                return back()->with('success', 'Módulo de facturación activado.');
            }

            return back()->withErrors(['activar' => 'El módulo aún no está listo para activarse.']);
        } catch (Throwable $e) {
            return back()->withErrors(['activar' => $e->getMessage()]);
        }
    }

    private function comercioId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->comercio_id ?? $user->branch?->comercio_id);
    }

    private function entornoDelComercio(Request $request): string
    {
        $comercioId = $this->comercioId($request);
        $configuracion = $this->servicio->buscarPorComercio($comercioId);

        return $configuracion?->entorno() ?? 'homologacion';
    }

    private function configuracionParaVista(mixed $configuracion): ?array
    {
        if ($configuracion === null) {
            return null;
        }

        return [
            'comercio_id' => $configuracion->comercioId(),
            'cuit' => $configuracion->cuit()?->formateado(),
            'razon_social' => $configuracion->razonSocial(),
            'condicion_fiscal' => $configuracion->condicionFiscal()?->value,
            'condicion_fiscal_label' => $this->condicionLabel($configuracion->condicionFiscal()),
            'domicilio_fiscal' => $configuracion->domicilioFiscal(),
            'entorno' => $configuracion->entorno(),
            'punto_venta_activo' => $configuracion->puntoVentaActivo(),
            'estado_modulo' => $configuracion->estadoModulo()->value,
            'lista_para_facturar' => $configuracion->estaListoParaFacturar(),
        ];
    }

    private function certificadoParaVista(?CertificadoFiscal $certificado): ?array
    {
        if ($certificado === null) {
            return null;
        }

        return [
            'distinguished_name' => $certificado->distinguished_name,
            'numero_serie' => $certificado->numero_serie,
            'vigencia_desde' => $certificado->vigencia_desde?->format('d/m/Y'),
            'vigencia_hasta' => $certificado->vigencia_hasta?->format('d/m/Y'),
            'vencido' => (bool) $certificado->vencido,
        ];
    }

    private function puntosVentaDisponibles(mixed $configuracion): array
    {
        if ($configuracion === null || $configuracion->certificadoId() === null) {
            return [];
        }

        try {
            return $this->servicio->puntosVenta($configuracion->comercioId());
        } catch (Throwable $e) {
            Log::warning('Wizard: no se pudieron listar los puntos de venta', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function condicionLabel(?CondicionFiscal $condicion): string
    {
        return match ($condicion) {
            CondicionFiscal::RESPONSABLE_INSCRIPTO => 'Responsable Inscripto',
            CondicionFiscal::MONOTRIBUTO => 'Monotributo',
            CondicionFiscal::CONSUMIDOR_FINAL => 'Consumidor Final',
            CondicionFiscal::EXENTO => 'Exento',
            CondicionFiscal::NO_ALCANZADO => 'No alcanzado',
            default => '—',
        };
    }

    private function mensajeFalla(EstadoModuloFiscal $estado): string
    {
        return match ($estado) {
            EstadoModuloFiscal::CUIT_INACTIVO => 'El CUIT no está activo en el padrón de ARCA.',
            EstadoModuloFiscal::CONDICION_DISCREPANTE => 'La condición fiscal del padrón no coincide con un Responsable Inscripto.',
            EstadoModuloFiscal::CERTIFICADO_VENCIDO => 'El certificado cargado está vencido.',
            EstadoModuloFiscal::DESINCRONIZADO_ARCA => 'Los datos no están sincronizados con ARCA.',
            EstadoModuloFiscal::ERROR_INTEGRACION => 'Ocurrió un error de integración con ARCA.',
            default => 'El paso no pudo completarse.',
        };
    }
}
