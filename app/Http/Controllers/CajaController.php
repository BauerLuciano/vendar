<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CajaController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $comercioId = $user->comercio_id ?? $user->branch?->comercio_id;
        
        // 1. Verificamos si es un "Jefe" (SuperAdmin o Admin Global)
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);
        
        $query = Caja::with('sucursal');

        // Seguridad multi-tenant: siempre filtrar por el comercio del usuario
        if ($comercioId) {
            $query->whereHas('sucursal', fn ($q) => $q->where('comercio_id', $comercioId));
        }

        // Si NO es jefe y tiene sucursal, solo ve las cajas de su sucursal
        if (!$esJefe) {
            $sucursalId = session('sucursal_activa_id', $user->branch_id);
            if ($sucursalId) {
                $query->where('sucursal_id', $sucursalId);
            }
        }
        $cajas = $query->orderBy('estado', 'desc')->orderBy('id', 'desc')->get();   
             
        $sucursales = $esJefe 
            ? Sucursal::where('tipo', 'punto_de_venta')
                ->when($comercioId, fn ($q) => $q->where('comercio_id', $comercioId))
                ->get() 
            : Sucursal::where('id', session('sucursal_activa_id', $user->branch_id))->where('tipo', 'punto_de_venta')->get();

        return Inertia::render('Cajas/Index', [
            'cajas' => $cajas,
            'sucursales' => $sucursales
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $comercioId = $user->comercio_id ?? $user->branch?->comercio_id;

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'sucursal_id' => [
                'required',
                // Validamos que exista, sea un punto de venta y pertenezca al comercio del usuario
                Rule::exists('sucursales', 'id')->where(function ($query) use ($comercioId) {
                    return $query->where('tipo', 'punto_de_venta')
                        ->when($comercioId, fn ($q) => $q->where('comercio_id', $comercioId));
                }),
            ],
        ], [
            'sucursal_id.exists' => 'La sucursal seleccionada no es válida o es un depósito.' 
        ]);

        Caja::create($validated);
        return redirect()->back()->with('success', 'Caja creada correctamente.');
    }

    public function update(Request $request, Caja $caja)
    {
        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        if ($comercioId && $caja->sucursal->comercio_id !== $comercioId) {
            abort(403);
        }

        $validated = $request->validate(['nombre' => 'required|string|max:255']);
        $caja->update($validated);
        return redirect()->back()->with('success', 'Caja actualizada correctamente.');
    }

    public function toggleEstado(Caja $caja)
    {
        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        if ($comercioId && $caja->sucursal->comercio_id !== $comercioId) {
            abort(403);
        }

        $caja->update(['estado' => !$caja->estado]);
        $mensaje = $caja->estado ? 'reactivada' : 'inactivada';
        
        return redirect()->back()->with('success', "Caja {$mensaje} correctamente.");
    }

    public function destroy(Caja $caja)
    {
        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        if ($comercioId && $caja->sucursal->comercio_id !== $comercioId) {
            abort(403);
        }

        $caja->delete();
        return redirect()->back()->with('success', 'Caja eliminada correctamente.');
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