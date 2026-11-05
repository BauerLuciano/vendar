<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CajaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $esAdminGlobal = $this->esAdminGlobal($user);
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);

        $comercioId = $esAdminGlobal ? null : $this->comercioDelActor($user);

        if (!$esAdminGlobal && !$comercioId) {
            abort(403, 'Tu usuario no tiene un comercio asignado.');
        }

        $query = Caja::with('sucursal');

        if (!$esAdminGlobal) {
            $query->whereHas('sucursal', fn ($q) => $q->where('comercio_id', $comercioId));

            // Los usuarios operativos siguen viendo solo su sucursal activa.
            if (!$esJefe) {
                $sucursalId = session('sucursal_activa_id', $user->branch_id);
                if ($sucursalId) {
                    $query->where('sucursal_id', $sucursalId);
                }
            }
        }

        $cajas = $query->orderBy('estado', 'desc')->orderBy('id', 'desc')->get();

        if ($esAdminGlobal) {
            $sucursales = Sucursal::where('tipo', 'punto_de_venta')->get();
        } elseif ($esJefe) {
            $sucursales = Sucursal::where('tipo', 'punto_de_venta')
                ->where('comercio_id', $comercioId)
                ->get();
        } else {
            $sucursales = Sucursal::where('id', session('sucursal_activa_id', $user->branch_id))
                ->where('tipo', 'punto_de_venta')
                ->where('comercio_id', $comercioId)
                ->get();
        }

        return Inertia::render('Cajas/Index', [
            'cajas' => $cajas,
            'sucursales' => $sucursales
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->asegurarComercio($user);
        $this->normalizarNombreRequest($request);

        $sucursalId = $this->sucursalIdDelRequest($request);

        $validated = $request->validate([
            'sucursal_id' => ['required', 'integer', $this->reglaSucursalValida($user)],
            'nombre' => ['required', 'string', 'max:255', $this->reglaNombreUnico($sucursalId)],
        ], $this->mensajesValidacion());

        try {
            Caja::create([
                'sucursal_id' => $validated['sucursal_id'],
                'nombre' => $validated['nombre'],
            ]);
        } catch (QueryException $e) {
            $this->rethrowSiDuplicado($e);
        }

        return redirect()->back()->with('success', 'Caja creada correctamente.');
    }

    public function update(Request $request, Caja $caja)
    {
        $user = $request->user();
        $this->autorizarCaja($caja, $user);
        $this->asegurarComercio($user);
        $this->normalizarNombreRequest($request);

        $cambiaSucursal = $request->has('sucursal_id');
        $sucursalId = $cambiaSucursal
            ? $this->sucursalIdDelRequest($request)
            : (int) $caja->sucursal_id;

        $reglas = [
            'nombre' => ['required', 'string', 'max:255', $this->reglaNombreUnico($sucursalId, $caja->id)],
        ];

        if ($cambiaSucursal) {
            $reglas['sucursal_id'] = ['required', 'integer', $this->reglaSucursalValida($user)];
        }

        $validated = $request->validate($reglas, $this->mensajesValidacion());

        try {
            $caja->update([
                'sucursal_id' => $cambiaSucursal ? $validated['sucursal_id'] : $caja->sucursal_id,
                'nombre' => $validated['nombre'],
            ]);
        } catch (QueryException $e) {
            $this->rethrowSiDuplicado($e);
        }

        return redirect()->back()->with('success', 'Caja actualizada correctamente.');
    }

    public function toggleEstado(Request $request, Caja $caja)
    {
        $this->autorizarCaja($caja, $request->user());

        $caja->update(['estado' => !$caja->estado]);
        $mensaje = $caja->estado ? 'reactivada' : 'inactivada';

        return redirect()->back()->with('success', "Caja {$mensaje} correctamente.");
    }

    public function destroy(Request $request, Caja $caja)
    {
        $this->autorizarCaja($caja, $request->user());

        if (!$caja->puedeEliminarse()) {
            return redirect()->back()->with('error', 'No se puede eliminar esta caja porque tiene historial de turnos asociados. Por favor, utiliza la opción de inactivarla.');
        }

        $caja->delete();
        return redirect()->back()->with('success', 'Caja eliminada correctamente.');
    }

    private function esAdminGlobal(User $user): bool
    {
        return $user->hasRole('Administrador Global');
    }

    private function comercioDelActor(User $user): ?int
    {
        if ($user->comercio_id) {
            return (int) $user->comercio_id;
        }

        $comercioId = $user->branch?->comercio_id;

        return $comercioId ? (int) $comercioId : null;
    }

    private function asegurarComercio(User $user): void
    {
        if (!$this->esAdminGlobal($user) && !$this->comercioDelActor($user)) {
            abort(403, 'Tu usuario no tiene un comercio asignado.');
        }
    }

    private function autorizarCaja(Caja $caja, User $user): void
    {
        if ($this->esAdminGlobal($user)) {
            return;
        }

        $comercioId = $this->comercioDelActor($user);
        $comercioCaja = $caja->sucursal?->comercio_id;

        if (!$comercioId || !$comercioCaja || (int) $comercioCaja !== $comercioId) {
            abort(403, 'Esta caja no pertenece a tu comercio.');
        }
    }

    private function normalizarNombreRequest(Request $request): void
    {
        $nombre = $request->input('nombre');

        if (is_string($nombre)) {
            // Solo recortamos extremos para coincidir con lower(btrim(nombre)).
            $request->merge(['nombre' => trim($nombre)]);
        }
    }

    private function sucursalIdDelRequest(Request $request): int
    {
        $sucursalId = $request->input('sucursal_id');

        return is_numeric($sucursalId) ? (int) $sucursalId : 0;
    }

    private function reglaSucursalValida(User $user): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('sucursales', 'id')->where(function ($query) use ($user) {
            $query->where('tipo', 'punto_de_venta');

            if (!$this->esAdminGlobal($user)) {
                $query->where('comercio_id', $this->comercioDelActor($user));
            }
        });
    }

    private function reglaNombreUnico(int $sucursalId, ?int $ignorarCajaId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($sucursalId, $ignorarCajaId) {
            if (!is_string($value) || $sucursalId <= 0) {
                return;
            }

            $existe = Caja::query()
                ->where('sucursal_id', $sucursalId)
                ->whereNull('deleted_at')
                ->whereRaw('lower(btrim(nombre)) = lower(btrim(?))', [$value])
                ->when($ignorarCajaId, fn ($query) => $query->where('id', '!=', $ignorarCajaId))
                ->exists();

            if ($existe) {
                $fail('Ya existe una caja con ese nombre en este local.');
            }
        };
    }

    private function rethrowSiDuplicado(QueryException $e): void
    {
        if ($e->getCode() === '23505' || (($e->errorInfo[0] ?? null) === '23505')) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe una caja con ese nombre en este local.',
            ]);
        }

        throw $e;
    }

    private function mensajesValidacion(): array
    {
        return [
            'nombre.required' => 'Ingresá un nombre para la caja.',
            'nombre.string' => 'Ingresá un nombre válido para la caja.',
            'nombre.max' => 'El nombre de la caja no puede superar los 255 caracteres.',
            'sucursal_id.required' => 'Seleccioná una sucursal.',
            'sucursal_id.integer' => 'Seleccioná una sucursal válida.',
            'sucursal_id.exists' => 'La sucursal seleccionada no es válida, no es un punto de venta o no pertenece a tu comercio.',
        ];
    }

    public function cierreDiario(Request $request)
    {
        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);

        $periodo = $request->get('periodo', 'dia');
        $fechaBase = $request->get('fecha', Carbon::today()->format('Y-m-d'));

        [$fechaDesde, $fechaHasta, $tituloPeriodo] = match ($periodo) {
            'semana' => [
                Carbon::parse($fechaBase)->startOfWeek()->format('Y-m-d'),
                Carbon::parse($fechaBase)->endOfWeek()->format('Y-m-d'),
                'Semana del ' . Carbon::parse($fechaBase)->startOfWeek()->format('d/m') . ' al ' . Carbon::parse($fechaBase)->endOfWeek()->format('d/m/Y'),
            ],
            'mes' => [
                Carbon::parse($fechaBase)->startOfMonth()->format('Y-m-d'),
                Carbon::parse($fechaBase)->endOfMonth()->format('Y-m-d'),
                Carbon::parse($fechaBase)->translatedFormat('F Y'),
            ],
            default => [
                Carbon::parse($fechaBase)->format('Y-m-d'),
                Carbon::parse($fechaBase)->format('Y-m-d'),
                Carbon::parse($fechaBase)->format('d/m/Y'),
            ],
        };

        $turnos = TurnoCaja::with(['caja', 'cajero', 'sucursal'])
            ->whereBetween('fecha_apertura', [$fechaDesde . ' 00:00:00', $fechaHasta . ' 23:59:59'])
            ->when($comercioId, fn ($q) => $q->whereHas('sucursal', fn ($sq) => $sq->where('comercio_id', $comercioId)))
            ->when(!$esJefe, fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('fecha_apertura')
            ->get()
            ->map(function ($t) {
                $facturado = (float) Venta::where('turno_caja_id', $t->id)->sum('total');
                $montoCierre = (float) ($t->monto_cierre ?? 0);
                $montoApertura = (float) ($t->monto_apertura ?? 0);
                $diferencia = $t->estado === 'Cerrado' ? round($montoCierre - $montoApertura - $facturado, 2) : 0;
                return [
                    'id' => $t->id,
                    'caja' => $t->caja?->nombre ?? '—',
                    'sucursal' => $t->sucursal?->nombre ?? '—',
                    'cajero' => $t->cajero?->name ?? '—',
                    'monto_apertura' => $montoApertura,
                    'facturado' => $facturado,
                    'monto_cierre' => $montoCierre,
                    'diferencia' => $diferencia,
                    'estado' => $t->estado,
                    'fecha_apertura' => $t->fecha_apertura?->format('d/m H:i') ?? '—',
                    'fecha_cierre' => $t->fecha_cierre?->format('d/m H:i') ?? '—',
                ];
            });

        if ($request->get('exportar') === 'excel') {
            return $this->exportarCierreExcel($turnos, $tituloPeriodo);
        }

        return Inertia::render('Cajas/CierreDiario', [
            'turnos' => $turnos,
            'fecha' => $fechaBase,
            'periodo' => $periodo,
            'tituloPeriodo' => $tituloPeriodo,
        ]);
    }

    public function exportarCierreExcel($turnos, string $titulo)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Cierre {$titulo}");

        $sheet->setCellValue('A1', "Cierre — {$titulo}");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->mergeCells('A1:H1');

        $headers = ['Sucursal', 'Caja', 'Cajero', 'Apertura', 'Facturado', 'Cierre', 'Diferencia', 'Estado'];
        $col = 'A';
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($col . '3', $h);
            $sheet->getStyle($col . '3')->getFont()->setBold(true);
            $col++;
        }

        $row = 4;
        foreach ($turnos as $t) {
            $sheet->setCellValue("A{$row}", $t['sucursal']);
            $sheet->setCellValue("B{$row}", $t['caja']);
            $sheet->setCellValue("C{$row}", $t['cajero']);
            $sheet->setCellValue("D{$row}", $t['monto_apertura']);
            $sheet->setCellValue("E{$row}", $t['facturado']);
            $sheet->setCellValue("F{$row}", $t['monto_cierre']);
            $sheet->setCellValue("G{$row}", $t['diferencia']);
            $sheet->setCellValue("H{$row}", $t['estado']);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'TOTALES');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->setCellValue("D{$row}", collect($turnos)->sum('monto_apertura'));
        $sheet->setCellValue("E{$row}", collect($turnos)->sum('facturado'));
        $sheet->setCellValue("F{$row}", collect($turnos)->sum('monto_cierre'));
        $sheet->setCellValue("G{$row}", collect($turnos)->sum('diferencia'));

        foreach (range('A', 'H') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "cierre_{$titulo}.xlsx";
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }
}