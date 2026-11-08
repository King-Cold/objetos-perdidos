<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Incidencia;
use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IncidenciaController extends Controller
{
    /**
     * Listado administrativo de incidencias con folio, objeto, motivo, fecha y estado.
     */
    public function index(Request $request): View
    {
        $filtroEstado = trim((string) $request->query('estado', ''));
        $busqueda = trim((string) $request->query('q', ''));

        $query = Incidencia::with(['publicacion.user', 'devolucion', 'user'])
            ->orderByDesc('fecha_apertura')
            ->orderByDesc('id');

        if ($filtroEstado !== '' && $filtroEstado !== 'todas') {
            if ($filtroEstado === 'activas') {
                $query->whereNotIn('estado', [
                    Incidencia::ESTADO_CERRADA,
                    Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA,
                ]);
            } else {
                $query->where('estado', $filtroEstado);
            }
        }

        if ($busqueda !== '') {
            $query->where(function ($sub) use ($busqueda) {
                $sub->where('motivo', 'like', "%{$busqueda}%")
                    ->orWhereHas('publicacion', function ($pubQuery) use ($busqueda) {
                        $pubQuery->where('folio', 'like', "%{$busqueda}%")
                            ->orWhere('nombre', 'like', "%{$busqueda}%")
                            ->orWhere('titulo', 'like', "%{$busqueda}%");
                    });
            });
        }

        $incidencias = $query->get();

        $totalIncidencias = Incidencia::count();
        $totalActivas = Incidencia::whereNotIn('estado', [
            Incidencia::ESTADO_CERRADA,
            Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA,
        ])->count();
        $totalCerradas = Incidencia::whereIn('estado', [
            Incidencia::ESTADO_CERRADA,
            Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA,
        ])->count();

        return view('admin.incidencias.index', compact(
            'incidencias',
            'filtroEstado',
            'busqueda',
            'totalIncidencias',
            'totalActivas',
            'totalCerradas'
        ));
    }

    /**
     * Formulario administrativo para abrir una incidencia vinculada exclusivamente a objetos con estado «Devuelto»
     * y que no tengan ya una incidencia registrada.
     */
    public function create(Request $request, $publicacionId = null): View
    {
        $publicacion = null;
        $devolucionSeleccionada = null;

        $pubId = $publicacionId ?? $request->query('publicacion_id');
        $devId = $request->query('devolucion_id');

        if ($devId) {
            $devCandidata = Devolucion::with(['publicacion.user', 'publicacion.devoluciones', 'publicacion.incidencias'])->find($devId);
            if (
                $devCandidata
                && $devCandidata->publicacion
                && $devCandidata->publicacion->estado === Publicacion::ESTADO_DEVUELTO
                && $devCandidata->publicacion->incidencias->isEmpty()
            ) {
                $devolucionSeleccionada = $devCandidata;
                $publicacion = $devCandidata->publicacion;
            }
        } elseif ($pubId) {
            $pubCandidata = Publicacion::with(['user', 'devoluciones', 'incidencias'])
                ->where('estado', Publicacion::ESTADO_DEVUELTO)
                ->whereDoesntHave('incidencias')
                ->find($pubId);
            if ($pubCandidata) {
                $publicacion = $pubCandidata;
            }
        }

        // Mostrar únicamente objetos cuyo estado actual sea «Devuelto» y que no tengan ya una incidencia abierta/registrada
        $publicacionesDisponibles = Publicacion::with(['user', 'devoluciones'])
            ->where('estado', Publicacion::ESTADO_DEVUELTO)
            ->whereDoesntHave('incidencias')
            ->orderByDesc('id')
            ->get();

        return view('admin.incidencias.create', compact(
            'publicacion',
            'devolucionSeleccionada',
            'publicacionesDisponibles'
        ));
    }

    /**
     * Registra una nueva incidencia vinculada al reporte con estado «Devuelto» y a su evento de devolución,
     * impidiendo abrir dos incidencias del mismo objeto.
     */
    public function store(Request $request, $publicacionId = null): RedirectResponse
    {
        $pubId = $publicacionId ?? $request->input('publicacion_id');

        $motivoRaw = (string) $request->input('motivo', '');
        $motivoLimpio = trim($motivoRaw);

        $request->merge([
            'publicacion_id' => $pubId,
            'motivo' => $motivoLimpio,
        ]);

        $request->validate(
            [
                'publicacion_id' => ['required', 'integer', 'exists:publicaciones,id'],
                'devolucion_id' => ['nullable', 'integer', 'exists:devoluciones,id'],
                'motivo' => ['required', 'string', 'min:3', 'max:1500'],
            ],
            [
                'publicacion_id.required' => 'Debes seleccionar un objeto con estado «Devuelto» para abrir la incidencia.',
                'publicacion_id.exists' => 'El reporte seleccionado no existe.',
                'devolucion_id.exists' => 'El evento de devolución seleccionado no es válido.',
                'motivo.required' => 'El motivo de apertura de la incidencia es obligatorio y no puede estar vacío ni compuesto únicamente por espacios.',
                'motivo.min' => 'El motivo de la incidencia debe describir con claridad la duda de entrega (mínimo 3 caracteres).',
                'motivo.max' => 'El motivo de la incidencia no debe exceder 1500 caracteres.',
            ]
        );

        $publicacion = Publicacion::with(['devoluciones', 'incidencias'])->findOrFail($pubId);

        // 1. Validación en el backend: impedir la creación de incidencias sobre objetos que no tengan estado «Devuelto»
        if ($publicacion->estado !== Publicacion::ESTADO_DEVUELTO) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'publicacion_id' => 'Únicamente se pueden registrar incidencias sobre objetos cuyo estado actual sea «Devuelto».',
                ]);
        }

        // 2. Validación en el backend: impedir abrir 2 incidencias del mismo objeto
        if ($publicacion->incidencias->isNotEmpty() || Incidencia::where('publicacion_id', $publicacion->id)->exists() || (bool) $publicacion->incidencia_abierta) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'publicacion_id' => "No se permite abrir dos incidencias del mismo objeto. El reporte con folio {$publicacion->folio} ya cuenta con una incidencia registrada.",
                ]);
        }

        // 3. Identificar el evento de devolución correspondiente
        $devolucion = null;
        if ($request->filled('devolucion_id')) {
            $devolucion = Devolucion::where('id', $request->input('devolucion_id'))
                ->where('publicacion_id', $publicacion->id)
                ->first();

            if (!$devolucion) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors([
                        'devolucion_id' => 'El evento de devolución seleccionado no pertenece al reporte indicado.',
                    ]);
            }
        } else {
            // Vincular automáticamente con el evento de devolución verificado/aprobado más reciente del reporte
            $devolucion = $publicacion->devoluciones
                ->first(fn (Devolucion $d) => $d->isAprobada())
                ?? $publicacion->devoluciones->first();
        }

        $ahora = Carbon::now(Publicacion::TIMEZONE);
        $incidencia = null;

        DB::transaction(function () use ($publicacion, $devolucion, $motivoLimpio, $ahora, &$incidencia) {
            $incidencia = Incidencia::create([
                'publicacion_id' => $publicacion->id,
                'devolucion_id' => $devolucion?->id,
                'user_id' => Auth::id(),
                'motivo' => $motivoLimpio,
                'estado' => Incidencia::ESTADO_ABIERTA,
                'fecha_apertura' => $ahora,
            ]);

            // Mantener el objeto fuera del muro público y del detalle general sin cambiar su estado «Devuelto»
            // ni eliminar evidencias ni presumir culpabilidad del receptor.
            $publicacion->update([
                'disponible' => false,
                'incidencia_abierta' => true,
                'tiene_incidencia' => true,
            ]);

            $detalleVinculo = $devolucion
                ? " (vinculada al evento de devolución #{$devolucion->id})"
                : '';

            DecisionModeracion::create([
                'publicacion_id' => $publicacion->id,
                'user_id' => Auth::id(),
                'tipo_decision' => 'incidencia_abierta',
                'motivo' => "Incidencia abierta{$detalleVinculo}: {$motivoLimpio}",
                'fecha_decision' => $ahora,
            ]);
        });

        return redirect()
            ->route('admin.incidencias.show', $incidencia->id)
            ->with('status', "La incidencia #{$incidencia->id} para el reporte con folio {$publicacion->folio} se registró correctamente con estado «Abierta».");
    }

    /**
     * Atajo para abrir una incidencia directamente desde un evento de devolución elegible.
     */
    public function storeFromDevolucion(Request $request, $devolucionId): RedirectResponse
    {
        $devolucion = Devolucion::with('publicacion')->findOrFail($devolucionId);

        $request->merge([
            'publicacion_id' => $devolucion->publicacion_id,
            'devolucion_id' => $devolucion->id,
        ]);

        return $this->store($request, $devolucion->publicacion_id);
    }

    /**
     * Muestra el detalle informativo de una incidencia para el administrador/bibliotecario.
     */
    public function show($id): View
    {
        $incidencia = Incidencia::with([
            'publicacion.user',
            'publicacion.fotografias',
            'publicacion.recepcion',
            'devolucion.user',
            'devolucion.admin',
            'user',
        ])->findOrFail($id);

        return view('admin.incidencias.show', compact('incidencia'));
    }
}

