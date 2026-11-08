<?php

namespace App\Http\Controllers\Admin;

use App\Events\DevolucionAprobada;
use App\Events\DevolucionCorreccionSolicitada;
use App\Http\Controllers\Controller;
use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Incidencia;
use App\Models\IncidenciaObservacion;
use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class IncidenciaController extends Controller
{
    public function __construct()
    {
        $this->ensureIncidenciaSchema();
    }

    /**
     * Garantiza de forma idempotente que la tabla `incidencia_observaciones` y las columnas de revisión
     * existan en la base de datos activa antes de consultar o registrar observaciones/decisiones.
     */
    private function ensureIncidenciaSchema(): void
    {
        if (Schema::hasTable('incidencias')) {
            Schema::table('incidencias', function (Blueprint $table) {
                if (!Schema::hasColumn('incidencias', 'responsable_revision_id')) {
                    $table->foreignId('responsable_revision_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('incidencias', 'cerrada_por_id')) {
                    $table->foreignId('cerrada_por_id')->nullable()->after('responsable_revision_id')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('incidencias', 'resultado')) {
                    $table->string('resultado')->nullable()->after('estado');
                }
                if (!Schema::hasColumn('incidencias', 'motivo_resolucion')) {
                    $table->text('motivo_resolucion')->nullable()->after('resultado');
                }
                if (!Schema::hasColumn('incidencias', 'solicitud_recuperacion')) {
                    $table->text('solicitud_recuperacion')->nullable()->after('motivo_resolucion');
                }
                if (!Schema::hasColumn('incidencias', 'fecha_revision')) {
                    $table->dateTime('fecha_revision')->nullable()->after('fecha_apertura');
                }
                if (!Schema::hasColumn('incidencias', 'fecha_cierre')) {
                    $table->dateTime('fecha_cierre')->nullable()->after('fecha_revision');
                }
            });
        }

        if (!Schema::hasTable('incidencia_observaciones')) {
            Schema::create('incidencia_observaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('incidencia_id')->constrained('incidencias')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('tipo')->default('Aclaración');
                $table->string('medio_contacto')->nullable();
                $table->text('descripcion');
                $table->text('resultado_obtenido')->nullable();
                $table->dateTime('fecha_registro');
                $table->timestamps();
            });
        }
    }
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

        if ($publicacion->estado !== Publicacion::ESTADO_DEVUELTO) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'publicacion_id' => 'Únicamente se pueden registrar incidencias sobre objetos cuyo estado actual sea «Devuelto».',
                ]);
        }

        if ($publicacion->incidencias->isNotEmpty() || Incidencia::where('publicacion_id', $publicacion->id)->exists() || (bool) $publicacion->incidencia_abierta) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'publicacion_id' => "No se permite abrir dos incidencias del mismo objeto. El reporte con folio {$publicacion->folio} ya cuenta con una incidencia registrada.",
                ]);
        }

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
     * Muestra el detalle administrativo de una incidencia junto con sus observaciones y acciones de revisión.
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
            'responsableRevision',
            'cerradaPor',
            'observaciones.user',
        ])->findOrFail($id);

        return view('admin.incidencias.show', compact('incidencia'));
    }

    /**
     * Registra aclaraciones, contactos realizados por medios externos y resultados obtenidos en la incidencia (CA-03).
     * Identifica fecha y responsable de cada observación, sin alterar la disponibilidad ni considerar una promesa como recuperación física.
     */
    public function storeObservacion(Request $request, $id): RedirectResponse
    {
        $incidencia = Incidencia::with('publicacion')->findOrFail($id);

        if (!$incidencia->isActiva()) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'No es posible agregar nuevas observaciones porque la incidencia ya fue resuelta o cerrada.');
        }

        $descripcionLimpia = trim((string) $request->input('descripcion', ''));
        $resultadoLimpio = trim((string) $request->input('resultado_obtenido', ''));
        $medioLimpio = trim((string) $request->input('medio_contacto', ''));
        $tipoLimpio = trim((string) $request->input('tipo', IncidenciaObservacion::TIPO_ACLARACION));

        $request->merge([
            'descripcion' => $descripcionLimpia,
            'resultado_obtenido' => $resultadoLimpio !== '' ? $resultadoLimpio : null,
            'medio_contacto' => $medioLimpio !== '' ? $medioLimpio : null,
            'tipo' => $tipoLimpio !== '' ? $tipoLimpio : IncidenciaObservacion::TIPO_ACLARACION,
        ]);

        $request->validate(
            [
                'tipo' => ['required', 'string', 'max:100'],
                'medio_contacto' => ['nullable', 'string', 'max:120'],
                'descripcion' => ['required', 'string', 'min:3', 'max:2000'],
                'resultado_obtenido' => ['nullable', 'string', 'max:1500'],
            ],
            [
                'descripcion.required' => 'La descripción de la observación, aclaración o contacto externo es obligatoria y no puede estar vacía ni compuesta solo por espacios.',
                'descripcion.min' => 'La observación debe describir con claridad la aclaración, contacto o resultado (mínimo 3 caracteres).',
                'descripcion.max' => 'La descripción no debe exceder 2000 caracteres.',
            ]
        );

        $ahora = Carbon::now(Publicacion::TIMEZONE);

        DB::transaction(function () use ($incidencia, $request, $ahora) {
            IncidenciaObservacion::create([
                'incidencia_id' => $incidencia->id,
                'user_id' => Auth::id(),
                'tipo' => $request->input('tipo'),
                'medio_contacto' => $request->input('medio_contacto'),
                'descripcion' => $request->input('descripcion'),
                'resultado_obtenido' => $request->input('resultado_obtenido'),
                'fecha_registro' => $ahora,
            ]);

            if ($incidencia->publicacion) {
                $resumenObs = $request->input('descripcion');
                if ($request->filled('resultado_obtenido')) {
                    $resumenObs .= ' • Resultado: ' . $request->input('resultado_obtenido');
                }

                DecisionModeracion::create([
                    'publicacion_id' => $incidencia->publicacion_id,
                    'user_id' => Auth::id(),
                    'tipo_decision' => 'incidencia_observacion',
                    'motivo' => "Observación en incidencia #{$incidencia->id} ({$request->input('tipo')}): {$resumenObs}",
                    'fecha_decision' => $ahora,
                ]);
            }
        });

        return redirect()
            ->route('admin.incidencias.show', $incidencia->id)
            ->with('status', 'Se registró la observación del caso con fecha y responsable.');
    }

    /**
     * Despachador unificado de decisiones de revisión sobre la incidencia.
     */
    public function gestionarRevision(Request $request, $id): RedirectResponse
    {
        $decision = (string) $request->input('decision', '');

        return match ($decision) {
            'entrega_correcta' => $this->confirmarEntregaCorrecta($request, $id),
            'solicitar_correccion' => $this->solicitarCorreccion($request, $id),
            'entrega_incorrecta' => $this->confirmarEntregaIncorrecta($request, $id),
            default => redirect()
                ->route('admin.incidencias.show', $id)
                ->withErrors(['decision' => 'Debes seleccionar una decisión de revisión válida.']),
        };
    }

    /**
     * CA-05: Entrega aclarada como correcta.
     * Si la revisión confirma que el objeto fue entregado a la persona correcta y la evidencia cumple HU-12,
     * aprueba la devolución y cierra la incidencia registrando motivo, fecha y responsable, sin exigir recuperar
     * el objeto ni recibirlo en biblioteca.
     */
    public function confirmarEntregaCorrecta(Request $request, $id): RedirectResponse
    {
        $incidencia = Incidencia::with(['publicacion.devoluciones', 'devolucion'])->findOrFail($id);

        if (!$incidencia->isActiva()) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'Esta incidencia ya fue resuelta o cerrada previamente.');
        }

        $motivoLimpio = trim((string) $request->input('motivo', ''));
        $request->merge(['motivo' => $motivoLimpio]);

        $request->validate(
            [
                'motivo' => ['required', 'string', 'min:3', 'max:1500'],
            ],
            [
                'motivo.required' => 'El motivo para confirmar la entrega correcta y cerrar la incidencia es obligatorio y no puede estar vacío ni contener solo espacios.',
                'motivo.min' => 'El motivo de resolución debe tener al menos 3 caracteres.',
            ]
        );

        $publicacion = $incidencia->publicacion;
        $devolucion = $incidencia->devolucion
            ?? ($publicacion ? $publicacion->devoluciones->first() : null);

        if (!$devolucion) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'No existe un evento de devolución vinculado para aprobar.');
        }

        // Comprobar que los datos y la evidencia fotográfica de la devolución estén completos
        $datosCompletos = !empty(trim((string) $devolucion->receptor_nombre))
            && !empty(trim((string) $devolucion->receptor_identificador))
            && !empty($devolucion->fecha_entrega)
            && !empty($devolucion->hora_entrega)
            && !empty(trim((string) $devolucion->explicacion_propiedad))
            && !empty($devolucion->fotografia_ruta)
            && Storage::disk('local')->exists($devolucion->fotografia_ruta);

        if (!$datosCompletos) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'No es posible confirmar la entrega como correcta porque la devolución no cuenta con todos los datos obligatorios o su fotografía de evidencia.');
        }

        $ahora = Carbon::now(Publicacion::TIMEZONE);

        DB::transaction(function () use ($incidencia, $publicacion, $devolucion, $motivoLimpio, $ahora) {
            // 1. Aprobar/verificar la devolución conforme a HU-12
            $devolucion->update([
                'estado' => Devolucion::ESTADO_VERIFICADA,
                'admin_id' => Auth::id(),
                'fecha_resolucion' => $ahora,
                'motivo_correccion' => null,
            ]);

            // 2. Cerrar la incidencia registrando resultado, motivo, fecha y responsable
            $incidencia->update([
                'devolucion_id' => $devolucion->id,
                'estado' => Incidencia::ESTADO_CERRADA,
                'resultado' => 'Entrega aclarada como correcta',
                'motivo_resolucion' => $motivoLimpio,
                'responsable_revision_id' => Auth::id(),
                'cerrada_por_id' => Auth::id(),
                'fecha_revision' => $ahora,
                'fecha_cierre' => $ahora,
            ]);

            // 3. Mantener el objeto como Devuelto y fuera del muro sin exigir recuperación ni recepción en biblioteca
            if ($publicacion) {
                $publicacion->update([
                    'estado' => Publicacion::ESTADO_DEVUELTO,
                    'disponible' => false,
                    'devolucion_en_proceso' => false,
                    'incidencia_abierta' => false,
                    'estado_devolucion' => Devolucion::ESTADO_VERIFICADA,
                ]);

                DecisionModeracion::create([
                    'publicacion_id' => $publicacion->id,
                    'user_id' => Auth::id(),
                    'tipo_decision' => DecisionModeracion::TIPO_DEVOLUCION_APROBADA,
                    'motivo' => "Entrega aclarada como correcta y devolución aprobada (Incidencia #{$incidencia->id} cerrada): {$motivoLimpio}",
                    'fecha_decision' => $ahora,
                ]);
            }

            event(new DevolucionAprobada($devolucion, Auth::user()));
        });

        return redirect()
            ->route('admin.incidencias.show', $incidencia->id)
            ->with('status', 'Se confirmó la entrega como correcta, se aprobó la devolución y se cerró la incidencia.');
    }

    /**
     * Prueba incompleta (evidencia insuficiente sin sospecha).
     * Solicita la corrección de la devolución existente, cambiando la incidencia
     * a «En espera de corrección», sin crear otra entrega ni devolver el objeto al estado «Disponible».
     */
    public function solicitarCorreccion(Request $request, $id): RedirectResponse
    {
        $incidencia = Incidencia::with(['publicacion.devoluciones', 'devolucion'])->findOrFail($id);

        if (!$incidencia->isActiva()) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'Esta incidencia ya fue resuelta o cerrada previamente.');
        }

        $motivoLimpio = trim((string) $request->input('motivo', ''));
        $request->merge(['motivo' => $motivoLimpio]);

        $request->validate(
            [
                'motivo' => ['required', 'string', 'min:3', 'max:1500'],
            ],
            [
                'motivo.required' => 'El motivo para solicitar la corrección de la devolución es obligatorio y no puede estar vacío ni contener solo espacios.',
                'motivo.min' => 'El motivo de corrección debe tener al menos 3 caracteres.',
            ]
        );

        $publicacion = $incidencia->publicacion;
        $devolucion = $incidencia->devolucion
            ?? ($publicacion ? $publicacion->devoluciones->first() : null);

        if (!$devolucion) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'No existe una devolución vinculada para solicitar su corrección.');
        }

        $ahora = Carbon::now(Publicacion::TIMEZONE);

        DB::transaction(function () use ($incidencia, $publicacion, $devolucion, $motivoLimpio, $ahora) {
            // 1. Solicitar corrección sobre la MISMA devolución existente (sin crear otra entrega)
            $devolucion->update([
                'estado' => Devolucion::ESTADO_REQUIERE_CORRECCION,
                'motivo_correccion' => $motivoLimpio,
                'admin_id' => Auth::id(),
                'fecha_resolucion' => $ahora,
            ]);

            // 2. Actualizar la incidencia a «En espera de corrección»
            $incidencia->update([
                'devolucion_id' => $devolucion->id,
                'estado' => Incidencia::ESTADO_ESPERA_CORRECCION,
                'resultado' => 'Prueba incompleta — Solicitud de corrección de devolución',
                'motivo_resolucion' => $motivoLimpio,
                'responsable_revision_id' => Auth::id(),
                'fecha_revision' => $ahora,
            ]);

            // 3. Mantener el objeto fuera del muro sin devolverlo al estado «Disponible»
            if ($publicacion) {
                $publicacion->update([
                    'disponible' => false,
                    'devolucion_en_proceso' => true,
                    'incidencia_abierta' => true,
                    'tiene_incidencia' => true,
                    'estado_devolucion' => Devolucion::ESTADO_REQUIERE_CORRECCION,
                ]);

                DecisionModeracion::create([
                    'publicacion_id' => $publicacion->id,
                    'user_id' => Auth::id(),
                    'tipo_decision' => DecisionModeracion::TIPO_DEVOLUCION_CORRECCION,
                    'motivo' => "Solicitud de corrección de devolución por incidencia #{$incidencia->id}: {$motivoLimpio}",
                    'fecha_decision' => $ahora,
                ]);
            }

            event(new DevolucionCorreccionSolicitada($devolucion, Auth::user(), $motivoLimpio));
        });

        return redirect()
            ->route('admin.incidencias.show', $incidencia->id)
            ->with('status', 'Se solicitó la corrección de la devolución existente y la incidencia pasó a «En espera de corrección».');
    }

    /**
     * CA-07: Entrega incorrecta confirmada.
     * Registra la conclusión de que el receptor no era el dueño y la solicitud de llevar el objeto a biblioteca.
     * La incidencia pasa a «En espera de recuperación» y el objeto permanece fuera del muro.
     */
    public function confirmarEntregaIncorrecta(Request $request, $id): RedirectResponse
    {
        $incidencia = Incidencia::with(['publicacion', 'devolucion'])->findOrFail($id);

        if (!$incidencia->isActiva()) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'Esta incidencia ya fue resuelta o cerrada previamente.');
        }

        $motivoLimpio = trim((string) $request->input('motivo', ''));
        $solicitudLimpia = trim((string) $request->input(
            'solicitud_recuperacion',
            'Se solicita llevar y entregar físicamente el objeto en biblioteca institucional para confirmar su recuperación.'
        ));

        $request->merge([
            'motivo' => $motivoLimpio,
            'solicitud_recuperacion' => $solicitudLimpia,
        ]);

        $request->validate(
            [
                'motivo' => ['required', 'string', 'min:3', 'max:1500'],
                'solicitud_recuperacion' => ['required', 'string', 'min:3', 'max:1000'],
            ],
            [
                'motivo.required' => 'Debes registrar la conclusión obligatoria que confirma la entrega incorrecta.',
                'motivo.min' => 'La conclusión debe tener al menos 3 caracteres.',
                'solicitud_recuperacion.required' => 'Debes indicar la solicitud de llevar el objeto a biblioteca.',
            ]
        );

        $publicacion = $incidencia->publicacion;
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        DB::transaction(function () use ($incidencia, $publicacion, $motivoLimpio, $solicitudLimpia, $ahora) {
            $incidencia->update([
                'estado' => Incidencia::ESTADO_ESPERA_RECUPERACION,
                'resultado' => 'Entrega incorrecta confirmada — Solicitud de entrega física en biblioteca',
                'motivo_resolucion' => $motivoLimpio,
                'solicitud_recuperacion' => $solicitudLimpia,
                'responsable_revision_id' => Auth::id(),
                'fecha_revision' => $ahora,
            ]);

            if ($publicacion) {
                $publicacion->update([
                    'disponible' => false,
                    'incidencia_abierta' => true,
                    'tiene_incidencia' => true,
                    'estado_devolucion' => Incidencia::ESTADO_ESPERA_RECUPERACION,
                ]);

                DecisionModeracion::create([
                    'publicacion_id' => $publicacion->id,
                    'user_id' => Auth::id(),
                    'tipo_decision' => 'incidencia_espera_recuperacion',
                    'motivo' => "Entrega incorrecta confirmada en incidencia #{$incidencia->id} (En espera de recuperación en biblioteca): {$motivoLimpio} • {$solicitudLimpia}",
                    'fecha_decision' => $ahora,
                ]);
            }
        });

        return redirect()
            ->route('admin.incidencias.show', $incidencia->id)
            ->with('status', 'Se registró la conclusión de entrega incorrecta y la solicitud de llevar el objeto a biblioteca. La incidencia quedó «En espera de recuperación».');
    }
}
