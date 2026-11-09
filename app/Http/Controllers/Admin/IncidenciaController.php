<?php

namespace App\Http\Controllers\Admin;

use App\Events\DevolucionAprobada;
use App\Events\DevolucionCorreccionSolicitada;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecepcionRequest;
use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Incidencia;
use App\Models\IncidenciaObservacion;
use App\Models\Publicacion;
use App\Models\Recepcion;
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
                if (!Schema::hasColumn('incidencias', 'recepcion_id')) {
                    $table->foreignId('recepcion_id')->nullable()->after('devolucion_id')->constrained('recepciones')->nullOnDelete();
                }
                if (!Schema::hasColumn('incidencias', 'recuperacion_confirmada')) {
                    $table->boolean('recuperacion_confirmada')->default(false)->after('solicitud_recuperacion');
                }
                if (!Schema::hasColumn('incidencias', 'fecha_recuperacion')) {
                    $table->dateTime('fecha_recuperacion')->nullable()->after('recuperacion_confirmada');
                }
                if (!Schema::hasColumn('incidencias', 'responsable_canalizacion')) {
                    $table->string('responsable_canalizacion')->nullable()->after('fecha_recuperacion');
                }
                if (!Schema::hasColumn('incidencias', 'observaciones_seguimiento')) {
                    $table->text('observaciones_seguimiento')->nullable()->after('responsable_canalizacion');
                }
                if (!Schema::hasColumn('incidencias', 'fecha_revision')) {
                    $table->dateTime('fecha_revision')->nullable()->after('fecha_apertura');
                }
                if (!Schema::hasColumn('incidencias', 'fecha_cierre')) {
                    $table->dateTime('fecha_cierre')->nullable()->after('fecha_revision');
                }
            });
        }

        if (Schema::hasTable('recepciones') && !Schema::hasColumn('recepciones', 'incidencia_id')) {
            Schema::table('recepciones', function (Blueprint $table) {
                $table->foreignId('incidencia_id')->nullable()->after('publicacion_id')->constrained('incidencias')->nullOnDelete();
            });
        }

        if (Schema::hasTable('publicaciones')) {
            Schema::table('publicaciones', function (Blueprint $table) {
                if (!Schema::hasColumn('publicaciones', 'publicacion_excepcional_autorizada')) {
                    $table->boolean('publicacion_excepcional_autorizada')->default(false)->after('tiene_incidencia');
                }
                if (!Schema::hasColumn('publicaciones', 'fecha_publicacion_excepcional')) {
                    $table->dateTime('fecha_publicacion_excepcional')->nullable()->after('publicacion_excepcional_autorizada');
                }
                if (!Schema::hasColumn('publicaciones', 'fecha_vencimiento_excepcional')) {
                    $table->dateTime('fecha_vencimiento_excepcional')->nullable()->after('fecha_publicacion_excepcional');
                }
                if (!Schema::hasColumn('publicaciones', 'autorizada_excepcional_por_id')) {
                    $table->foreignId('autorizada_excepcional_por_id')->nullable()->after('fecha_vencimiento_excepcional')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('publicaciones', 'motivo_publicacion_excepcional')) {
                    $table->text('motivo_publicacion_excepcional')->nullable()->after('autorizada_excepcional_por_id');
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

        $conteoPorEstado = Incidencia::select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $contadores = [
            'todas' => $totalIncidencias,
            'activas' => $totalActivas,
            'abiertas' => (int) ($conteoPorEstado[Incidencia::ESTADO_ABIERTA] ?? 0),
            'correccion' => (int) ($conteoPorEstado[Incidencia::ESTADO_ESPERA_CORRECCION] ?? 0),
            'recuperacion' => (int) ($conteoPorEstado[Incidencia::ESTADO_ESPERA_RECUPERACION] ?? 0),
            'canalizadas' => (int) ($conteoPorEstado[Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA] ?? 0),
            'cerradas' => (int) ($conteoPorEstado[Incidencia::ESTADO_CERRADA] ?? 0),
        ];

        return view('admin.incidencias.index', compact(
            'incidencias',
            'filtroEstado',
            'busqueda',
            'totalIncidencias',
            'totalActivas',
            'totalCerradas',
            'contadores'
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
            'publicacion.recepcion.user',
            'devolucion.user',
            'devolucion.admin',
            'recepcion.user',
            'user',
            'responsableRevision',
            'cerradaPor',
            'observaciones.user',
        ])->findOrFail($id);

        $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);
        $fechaRecepcionDefault = $ahoraMerida->format('Y-m-d');
        $horaRecepcionDefault = $ahoraMerida->format('H:i');

        return view('admin.incidencias.show', compact(
            'incidencia',
            'fechaRecepcionDefault',
            'horaRecepcionDefault'
        ));
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
            'cerrar_incidencia' => $this->cerrarIncidencia($request, $id),
            'canalizar_sin_resolver' => $this->canalizarSinResolver($request, $id),
            'autorizar_publicacion_excepcional' => $this->autorizarPublicacionExcepcional($request, $id),
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

    /**
     * CA-08: Confirmación de recuperación mediante recepción física en biblioteca.
     * Completa la recuperación física vinculando la recepción con la incidencia y el reporte original,
     * conservando intactos los antecedentes y evidencias de la entrega incorrecta anterior,
     * sin marcar como correcta esa entrega y sin publicar automáticamente el objeto.
     */
    public function registrarRecuperacionFisica(StoreRecepcionRequest $request, $id): RedirectResponse
    {
        $incidencia = Incidencia::with(['publicacion', 'devolucion'])->findOrFail($id);

        if (!$incidencia->isActiva()) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'No es posible registrar recuperación física porque la incidencia ya no está activa.');
        }

        if ($incidencia->estado !== Incidencia::ESTADO_ESPERA_RECUPERACION) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'La recepción física por recuperación únicamente aplica para incidencias en estado «En espera de recuperación».');
        }

        if ($incidencia->hasRecuperacionFisicaConfirmada()) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'La recuperación física de este objeto en biblioteca ya fue registrada previamente.');
        }

        $publicacion = $incidencia->publicacion;
        if (!$publicacion) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'No se encontró el reporte original vinculado a la incidencia.');
        }

        $ahora = Carbon::now(Publicacion::TIMEZONE);
        $fechaRecepcion = $request->validated('fecha_recepcion');
        $horaRecepcion = substr((string) $request->validated('hora_recepcion'), 0, 5);
        $condicionObjeto = $request->validated('condicion_objeto');
        $ubicacionResguardo = $request->validated('ubicacion_resguardo');
        $observaciones = $request->validated('observaciones');

        DB::transaction(function () use (
            $incidencia,
            $publicacion,
            $ahora,
            $fechaRecepcion,
            $horaRecepcion,
            $condicionObjeto,
            $ubicacionResguardo,
            $observaciones
        ) {
            // 1. Registrar la recepción física en biblioteca vinculada a la incidencia y al reporte original
            $recepcion = Recepcion::create([
                'publicacion_id' => $publicacion->id,
                'incidencia_id' => $incidencia->id,
                'user_id' => Auth::id(),
                'fecha_recepcion' => $fechaRecepcion,
                'hora_recepcion' => $horaRecepcion,
                'fecha_captura' => $ahora,
                'condicion_objeto' => $condicionObjeto,
                'ubicacion_resguardo' => $ubicacionResguardo,
                'observaciones' => $observaciones,
                'confirmacion_cotejo' => true,
                'fecha_limite_anual' => Recepcion::calcularFechaLimiteAnual($fechaRecepcion, $horaRecepcion),
            ]);

            // 2. Vincular la recepción en la incidencia y confirmar su recuperación física
            $incidencia->update([
                'recepcion_id' => $recepcion->id,
                'recuperacion_confirmada' => true,
                'fecha_recuperacion' => $ahora,
                'resultado' => 'Recuperación física confirmada mediante recepción en biblioteca',
            ]);

            // 3. Actualizar la situación de resguardo del objeto sin publicarlo automáticamente
            // y conservando el antecedente y la evidencia de la devolución incorrecta sin marcarla como correcta
            $publicacion->update([
                'situacion_resguardo' => 'En biblioteca',
                'disponible' => false,
                'estado_devolucion' => 'Recuperado en biblioteca',
            ]);

            // 4. Registrar observación de resultado obtenido y decisión en el historial
            IncidenciaObservacion::create([
                'incidencia_id' => $incidencia->id,
                'user_id' => Auth::id(),
                'tipo' => IncidenciaObservacion::TIPO_RESULTADO,
                'medio_contacto' => 'Recepción presencial en biblioteca',
                'descripcion' => "Recepción física del objeto en biblioteca (Recepción #{$recepcion->id}). Ubicación asignada: {$ubicacionResguardo}. Condición: {$condicionObjeto}.",
                'resultado_obtenido' => 'Recuperación física confirmada en biblioteca; se conservan antecedentes y evidencias de la entrega anterior.',
                'fecha_registro' => $ahora,
            ]);

            DecisionModeracion::create([
                'publicacion_id' => $publicacion->id,
                'user_id' => Auth::id(),
                'tipo_decision' => 'recepcion_biblioteca',
                'motivo' => "Recuperación física confirmada mediante recepción en biblioteca (Incidencia #{$incidencia->id}, Recepción #{$recepcion->id}). Ubicación: {$ubicacionResguardo}.",
                'fecha_decision' => $ahora,
            ]);
        });

        return redirect()
            ->route('admin.incidencias.show', $incidencia->id)
            ->with('status', 'Se confirmó la recuperación física del objeto mediante su recepción en biblioteca, conservando los antecedentes y evidencias de la entrega anterior.');
    }

    /**
     * CA-07, CA-08 y CA-13: Cierre de incidencia con motivo, resultado, fecha y responsable.
     * - Si existió entrega incorrecta («En espera de recuperación»), únicamente permite cerrar la incidencia
     *   después de confirmar la recuperación mediante recepción física en biblioteca (una promesa no es recuperación).
     * - Si solo existió prueba incompleta («En espera de corrección»), verifica que la devolución esté completa y la aprueba.
     * - Conserva siempre los antecedentes y evidencias privadas.
     */
    public function cerrarIncidencia(Request $request, $id): RedirectResponse
    {
        $incidencia = Incidencia::with(['publicacion.devoluciones', 'publicacion.recepcion', 'devolucion', 'recepcion'])->findOrFail($id);

        if (!$incidencia->isActiva()) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'Esta incidencia ya fue resuelta o cerrada previamente.');
        }

        // Si el caso está en espera de corrección, delegar a la aprobación de entrega correcta tras validar evidencia
        if ($incidencia->estado === Incidencia::ESTADO_ESPERA_CORRECCION) {
            return $this->confirmarEntregaCorrecta($request, $id);
        }

        // Si existió entrega incorrecta, exigir que la recuperación física en biblioteca esté confirmada
        $recepcionVinculada = $incidencia->recepcion ?? $incidencia->publicacion?->recepcion;
        $recuperacionConfirmada = $incidencia->hasRecuperacionFisicaConfirmada() || $recepcionVinculada !== null;

        if ($incidencia->estado === Incidencia::ESTADO_ESPERA_RECUPERACION && !$recuperacionConfirmada) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->withErrors([
                    'recuperacion' => 'No es posible cerrar la incidencia porque se confirmó una entrega incorrecta y aún no se ha registrado la recepción física del objeto en biblioteca. Una promesa de entrega no se considera recuperación física.',
                ])
                ->with('error', 'No es posible cerrar la incidencia sin confirmar primero la recepción física del objeto en biblioteca. Una promesa de devolverlo no se considera recuperación.');
        }

        // Si la incidencia sigue en estado «Abierta» y no tiene recepción física, verificar si se trata de entrega correcta
        if ($incidencia->estado === Incidencia::ESTADO_ABIERTA && !$recuperacionConfirmada) {
            return $this->confirmarEntregaCorrecta($request, $id);
        }

        $motivoLimpio = trim((string) $request->input('motivo', ''));
        $resultadoLimpio = trim((string) $request->input(
            'resultado',
            'Objeto recuperado físicamente en biblioteca — Incidencia cerrada'
        ));

        $request->merge([
            'motivo' => $motivoLimpio,
            'resultado' => $resultadoLimpio !== '' ? $resultadoLimpio : 'Objeto recuperado físicamente en biblioteca — Incidencia cerrada',
        ]);

        $request->validate(
            [
                'motivo' => ['required', 'string', 'min:3', 'max:1500'],
                'resultado' => ['required', 'string', 'min:3', 'max:255'],
            ],
            [
                'motivo.required' => 'El motivo de cierre de la incidencia es obligatorio y no puede estar vacío ni compuesto solo por espacios.',
                'motivo.min' => 'El motivo de cierre debe tener al menos 3 caracteres.',
                'resultado.required' => 'Debes indicar el resultado de la resolución.',
            ]
        );

        $publicacion = $incidencia->publicacion;
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        DB::transaction(function () use ($incidencia, $publicacion, $recepcionVinculada, $motivoLimpio, $request, $ahora) {
            $incidencia->update([
                'recepcion_id' => $incidencia->recepcion_id ?? $recepcionVinculada?->id,
                'recuperacion_confirmada' => true,
                'fecha_recuperacion' => $incidencia->fecha_recuperacion ?? $ahora,
                'estado' => Incidencia::ESTADO_CERRADA,
                'resultado' => $request->input('resultado'),
                'motivo_resolucion' => $motivoLimpio,
                'responsable_revision_id' => $incidencia->responsable_revision_id ?? Auth::id(),
                'cerrada_por_id' => Auth::id(),
                'fecha_revision' => $incidencia->fecha_revision ?? $ahora,
                'fecha_cierre' => $ahora,
            ]);

            // El objeto recuperado permanece bajo resguardo en biblioteca y fuera del muro público,
            // salvo que ya cuente con su publicación excepcional vigente de 14 días autorizada
            if ($publicacion) {
                $esExcepcionalVigente = $publicacion->isPublicacionExcepcionalVigente();

                $publicacion->update([
                    'estado' => $esExcepcionalVigente ? Publicacion::ESTADO_DISPONIBLE_BIBLIOTECA : 'En resguardo',
                    'situacion_resguardo' => 'En biblioteca',
                    'disponible' => $esExcepcionalVigente,
                    'devolucion_en_proceso' => false,
                    'incidencia_abierta' => false,
                    'tiene_incidencia' => true,
                    'estado_devolucion' => 'Recuperado en biblioteca',
                ]);

                DecisionModeracion::create([
                    'publicacion_id' => $publicacion->id,
                    'user_id' => Auth::id(),
                    'tipo_decision' => 'incidencia_cerrada_recuperacion',
                    'motivo' => "Incidencia #{$incidencia->id} cerrada tras recuperación física en biblioteca ({$request->input('resultado')}): {$motivoLimpio}",
                    'fecha_decision' => $ahora,
                ]);
            }
        });

        return redirect()
            ->route('admin.incidencias.show', $incidencia->id)
            ->with('status', 'La incidencia se cerró correctamente tras confirmar la recuperación física en biblioteca, conservando los antecedentes del caso.');
    }

    /**
     * CA-09: Caso sin solución («Sin resolver y canalizada»).
     * Si no es posible aclarar el caso ni recuperar el objeto, permite marcar la incidencia como
     * «Sin resolver y canalizada», registrando motivo, fecha, responsable/destino institucional y observaciones
     * de seguimiento. El objeto no se marca como «Devuelto» ni se ofrece como «Disponible», y no impone sanciones automáticas.
     */
    public function canalizarSinResolver(Request $request, $id): RedirectResponse
    {
        $incidencia = Incidencia::with(['publicacion', 'devolucion'])->findOrFail($id);

        if (!$incidencia->isActiva()) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'Esta incidencia ya fue cerrada o canalizada previamente.');
        }

        $motivoLimpio = trim((string) $request->input('motivo', ''));
        $destinoLimpio = trim((string) $request->input('responsable_canalizacion', ''));
        $observacionesLimpias = trim((string) $request->input('observaciones_seguimiento', ''));

        $request->merge([
            'motivo' => $motivoLimpio,
            'responsable_canalizacion' => $destinoLimpio,
            'observaciones_seguimiento' => $observacionesLimpias,
        ]);

        $request->validate(
            [
                'motivo' => ['required', 'string', 'min:3', 'max:1500'],
                'responsable_canalizacion' => ['required', 'string', 'min:3', 'max:255'],
                'observaciones_seguimiento' => ['required', 'string', 'min:3', 'max:2000'],
            ],
            [
                'motivo.required' => 'El motivo por el que el caso no pudo resolverse ni recuperarse el objeto es obligatorio y no puede contener solo espacios.',
                'motivo.min' => 'El motivo de canalización debe tener al menos 3 caracteres.',
                'responsable_canalizacion.required' => 'Debes indicar el destino o instancia institucional a la que se canaliza el caso.',
                'responsable_canalizacion.min' => 'El destino institucional debe tener al menos 3 caracteres.',
                'observaciones_seguimiento.required' => 'Las observaciones de seguimiento para la canalización son obligatorias y no pueden contener solo espacios.',
                'observaciones_seguimiento.min' => 'Las observaciones de seguimiento deben tener al menos 3 caracteres.',
            ]
        );

        $publicacion = $incidencia->publicacion;
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        DB::transaction(function () use ($incidencia, $publicacion, $motivoLimpio, $destinoLimpio, $observacionesLimpias, $ahora) {
            // 1. Marcar la incidencia como «Sin resolver y canalizada» con motivo, fecha, responsable y observaciones
            $incidencia->update([
                'estado' => Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA,
                'resultado' => 'Sin resolver y canalizada',
                'motivo_resolucion' => $motivoLimpio,
                'responsable_canalizacion' => $destinoLimpio,
                'observaciones_seguimiento' => $observacionesLimpias,
                'responsable_revision_id' => $incidencia->responsable_revision_id ?? Auth::id(),
                'cerrada_por_id' => Auth::id(),
                'fecha_revision' => $incidencia->fecha_revision ?? $ahora,
                'fecha_cierre' => $ahora,
            ]);

            // 2. Registrar en las observaciones de la incidencia la canalización institucional
            IncidenciaObservacion::create([
                'incidencia_id' => $incidencia->id,
                'user_id' => Auth::id(),
                'tipo' => IncidenciaObservacion::TIPO_RESULTADO,
                'medio_contacto' => "Canalización institucional: {$destinoLimpio}",
                'descripcion' => "Caso marcado como «Sin resolver y canalizada». Motivo: {$motivoLimpio}",
                'resultado_obtenido' => $observacionesLimpias,
                'fecha_registro' => $ahora,
            ]);

            // 3. El objeto NO debe marcarse como «Devuelto» ni ofrecerse como «Disponible», y no se imponen sanciones automáticas
            if ($publicacion) {
                $publicacion->update([
                    'estado' => Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA,
                    'estado_devolucion' => Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA,
                    'disponible' => false,
                    'devolucion_en_proceso' => false,
                    'incidencia_abierta' => true,
                    'tiene_incidencia' => true,
                ]);

                DecisionModeracion::create([
                    'publicacion_id' => $publicacion->id,
                    'user_id' => Auth::id(),
                    'tipo_decision' => 'incidencia_canalizada',
                    'motivo' => "Incidencia #{$incidencia->id} marcada como «Sin resolver y canalizada» (Destino: {$destinoLimpio}). Motivo: {$motivoLimpio} • Observaciones: {$observacionesLimpias}",
                    'fecha_decision' => $ahora,
                ]);
            }
        });

        return redirect()
            ->route('admin.incidencias.show', $incidencia->id)
            ->with('status', 'La incidencia se marcó como «Sin resolver y canalizada» con su motivo, destino institucional, fecha, responsable y observaciones de seguimiento.');
    }

    /**
     * CA-10 y CA-11: Autoriza una sola publicación excepcional de 14 días naturales como «Disponible en biblioteca»
     * después de confirmar la recepción física en biblioteca y revisar el contenido del reporte.
     * - Mantiene el mismo folio original.
     * - Registra fecha especial de publicación y vencimiento excepcional (+14 días) independiente de fecha_primera_aprobacion.
     * - Mantiene intacto el año de resguardo calculado desde la recepción en biblioteca.
     * - Solo permite autorizar una única publicación excepcional por reporte recuperado.
     */
    public function autorizarPublicacionExcepcional(Request $request, $id): RedirectResponse
    {
        $incidencia = Incidencia::with([
            'publicacion.recepcion',
            'publicacion.incidencias',
            'recepcion',
            'devolucion',
        ])->findOrFail($id);

        $publicacion = $incidencia->publicacion;
        if (!$publicacion) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'No se encontró el reporte original vinculado a la incidencia.');
        }

        if ($incidencia->estado === Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->with('error', 'No es posible autorizar una publicación excepcional para un caso marcado como «Sin resolver y canalizada».');
        }

        // 1. Verificar que el objeto haya sido recibido físicamente en biblioteca tras entrega incorrecta confirmada
        $recepcion = $incidencia->recepcion ?? $publicacion->recepcion;
        if (!$incidencia->hasRecuperacionFisicaConfirmada() || !$recepcion) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->withErrors([
                    'publicacion_excepcional' => 'Únicamente se puede autorizar la publicación excepcional después de haber confirmado la recepción física del objeto en biblioteca.',
                ])
                ->with('error', 'Únicamente se puede autorizar la publicación excepcional después de haber confirmado la recepción física del objeto en biblioteca.');
        }

        // 2. Verificar que no se haya autorizado previamente la única publicación excepcional permitida
        if ((bool) $publicacion->publicacion_excepcional_autorizada) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->withErrors([
                    'publicacion_excepcional' => 'Este reporte ya utilizó su única publicación excepcional de 14 días como «Disponible en biblioteca». No se permite autorizar una segunda publicación excepcional.',
                ])
                ->with('error', 'Este reporte ya utilizó su única publicación excepcional de 14 días como «Disponible en biblioteca». No se permite autorizar una segunda publicación excepcional.');
        }

        // 3. Verificar que el objeto no haya sido devuelto ya desde biblioteca
        if ($publicacion->estado === Publicacion::ESTADO_DEVUELTO || in_array($publicacion->estado_devolucion, ['Devuelto', 'Verificada'], true)) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->withErrors([
                    'publicacion_excepcional' => 'No es posible autorizar la publicación excepcional porque el objeto ya fue devuelto.',
                ])
                ->with('error', 'No es posible autorizar la publicación excepcional porque el objeto ya fue devuelto.');
        }

        // 4. Verificar que el año de resguardo calculado desde la recepción siga vigente
        if ($recepcion->isAnualVencido()) {
            return redirect()
                ->route('admin.incidencias.show', $incidencia->id)
                ->withErrors([
                    'publicacion_excepcional' => 'No es posible autorizar la publicación excepcional porque el año de resguardo en biblioteca calculado desde la recepción ya venció.',
                ])
                ->with('error', 'No es posible autorizar la publicación excepcional porque el año de resguardo en biblioteca calculado desde la recepción ya venció.');
        }

        $motivoLimpio = trim((string) $request->input('motivo', ''));
        $nombreLimpio = $request->has('nombre') ? trim((string) $request->input('nombre', '')) : trim((string) ($publicacion->nombre ?? $publicacion->titulo ?? ''));
        $descripcionLimpia = $request->has('descripcion') ? trim((string) $request->input('descripcion', '')) : trim((string) ($publicacion->descripcion ?? ''));

        $request->merge([
            'motivo' => $motivoLimpio,
            'nombre' => $nombreLimpio,
            'descripcion' => $descripcionLimpia,
        ]);

        $request->validate(
            [
                'confirmacion_revision_contenido' => ['required', 'accepted'],
                'motivo' => ['required', 'string', 'min:3', 'max:1500'],
                'nombre' => ['required', 'string', 'min:3', 'max:120'],
                'descripcion' => ['required', 'string', 'min:5', 'max:2000'],
            ],
            [
                'confirmacion_revision_contenido.required' => 'Debes confirmar que revisaste el contenido de la publicación antes de autorizar su difusión excepcional.',
                'confirmacion_revision_contenido.accepted' => 'Debes marcar la confirmación de revisión de contenido para autorizar la publicación excepcional.',
                'motivo.required' => 'El motivo o dictamen de autorización de la publicación excepcional es obligatorio y no puede contener solo espacios.',
                'motivo.min' => 'El motivo de autorización debe tener al menos 3 caracteres.',
                'nombre.required' => 'El nombre del objeto revisado no puede estar vacío.',
                'descripcion.required' => 'La descripción revisada del objeto no puede estar vacía.',
            ]
        );

        $ahora = Carbon::now(Publicacion::TIMEZONE);
        $vencimientoExcepcional = Publicacion::calcularFechaVencimiento($ahora);

        DB::transaction(function () use (
            $incidencia,
            $publicacion,
            $recepcion,
            $motivoLimpio,
            $nombreLimpio,
            $descripcionLimpia,
            $ahora,
            $vencimientoExcepcional
        ) {
            // 1. Autorizar la única publicación excepcional de 14 días como «Disponible en biblioteca»
            // conservando el mismo folio, sin alterar fecha_primera_aprobacion ni el año de resguardo de la recepción
            $publicacion->update([
                'nombre' => $nombreLimpio,
                'titulo' => $nombreLimpio,
                'descripcion' => $descripcionLimpia,
                'estado' => Publicacion::ESTADO_DISPONIBLE_BIBLIOTECA,
                'situacion_resguardo' => 'En biblioteca',
                'disponible' => true,
                'devolucion_en_proceso' => false,
                'incidencia_abierta' => false,
                'tiene_incidencia' => true,
                'estado_devolucion' => 'Recuperado en biblioteca',
                'publicacion_excepcional_autorizada' => true,
                'fecha_publicacion_excepcional' => $ahora,
                'fecha_vencimiento_excepcional' => $vencimientoExcepcional,
                'autorizada_excepcional_por_id' => Auth::id(),
                'motivo_publicacion_excepcional' => $motivoLimpio,
            ]);

            // 2. Si la incidencia seguía activa en «En espera de recuperación», cerrarla registrando la resolución
            if ($incidencia->isActiva()) {
                $incidencia->update([
                    'recepcion_id' => $incidencia->recepcion_id ?? $recepcion->id,
                    'recuperacion_confirmada' => true,
                    'fecha_recuperacion' => $incidencia->fecha_recuperacion ?? $ahora,
                    'estado' => Incidencia::ESTADO_CERRADA,
                    'resultado' => 'Objeto recuperado en biblioteca — Publicación excepcional autorizada (14 días)',
                    'motivo_resolucion' => $motivoLimpio,
                    'responsable_revision_id' => $incidencia->responsable_revision_id ?? Auth::id(),
                    'cerrada_por_id' => Auth::id(),
                    'fecha_revision' => $incidencia->fecha_revision ?? $ahora,
                    'fecha_cierre' => $ahora,
                ]);
            }

            // 3. Registrar observación y decisión administrativa en el historial
            $resumenFechas = "Folio conservado: {$publicacion->folio} • Fecha especial de publicación: {$ahora->format('d/m/Y h:i a')} • Vencimiento excepcional (14 días): {$vencimientoExcepcional->format('d/m/Y h:i a')} • Límite de 1 año de resguardo desde recepción: {$recepcion->fecha_limite_anual_texto}";

            IncidenciaObservacion::create([
                'incidencia_id' => $incidencia->id,
                'user_id' => Auth::id(),
                'tipo' => IncidenciaObservacion::TIPO_RESULTADO,
                'medio_contacto' => 'Revisión administrativa de contenido en biblioteca',
                'descripcion' => "Se autorizó la única publicación excepcional de 14 días naturales como «Disponible en biblioteca». Motivo: {$motivoLimpio}",
                'resultado_obtenido' => $resumenFechas,
                'fecha_registro' => $ahora,
            ]);

            DecisionModeracion::create([
                'publicacion_id' => $publicacion->id,
                'user_id' => Auth::id(),
                'tipo_decision' => DecisionModeracion::TIPO_PUBLICACION_EXCEPCIONAL_BIBLIOTECA,
                'motivo' => "Publicación excepcional única de 14 días autorizada como «Disponible en biblioteca» (Incidencia #{$incidencia->id}): {$motivoLimpio} • {$resumenFechas}",
                'fecha_decision' => $ahora,
            ]);
        });

        return redirect()
            ->route('admin.incidencias.show', $incidencia->id)
            ->with('status', "Se autorizó la publicación excepcional única de 14 días como «Disponible en biblioteca» para el folio {$publicacion->folio}, conservando su fecha de primera aprobación y el año de resguardo calculado desde la recepción.");
    }
}
