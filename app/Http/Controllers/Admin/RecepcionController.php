<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecepcionRequest;
use App\Models\DecisionModeracion;
use App\Models\Incidencia;
use App\Models\Publicacion;
use App\Models\Recepcion;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RecepcionController extends Controller
{
    public function __construct()
    {
        $this->ensureRecuperacionSchema();
    }

    private function ensureRecuperacionSchema(): void
    {
        if (Schema::hasTable('incidencias')) {
            Schema::table('incidencias', function (Blueprint $table) {
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
    }

    /**
     * Muestra el cotejo físico previo y el formulario de recepción por vencimiento o por recuperación física de incidencia.
     */
    public function create(int|string $id): View|RedirectResponse
    {
        $reporte = Publicacion::with(['user', 'fotografias', 'incidencias'])->findOrFail($id);

        if (! $reporte->esElegibleParaRecepcionFisica()) {
            return redirect()->route('admin.moderacion.show', $reporte->id)
                ->with('error', 'El reporte no cumple las condiciones para registrar recepción física por vencimiento.');
        }

        $incidenciaRecuperacion = $reporte->incidencias
            ->first(fn (Incidencia $inc) => $inc->estado === Incidencia::ESTADO_ESPERA_RECUPERACION && ! $inc->hasRecuperacionFisicaConfirmada());

        $fotografiaPrincipal = $reporte->fotografias->where('es_principal', true)->first()
            ?? $reporte->fotografias->first();
        $fotografiasAdicionales = $reporte->fotografias->where('id', '!=', $fotografiaPrincipal?->id);

        $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);
        $fechaRecepcionDefault = $ahoraMerida->format('Y-m-d');
        $horaRecepcionDefault = $ahoraMerida->format('H:i');

        return view('admin.recepciones.create', compact(
            'reporte',
            'incidenciaRecuperacion',
            'fotografiaPrincipal',
            'fotografiasAdicionales',
            'fechaRecepcionDefault',
            'horaRecepcionDefault'
        ));
    }

    /**
     * Registra la recepción física del objeto bajo resguardo en biblioteca.
     */
    public function store(StoreRecepcionRequest $request, int|string $id): RedirectResponse
    {
        $reporte = Publicacion::with('incidencias')->findOrFail($id);

        if (! $reporte->esElegibleParaRecepcionFisica()) {
            return redirect()->route('admin.moderacion.show', $reporte->id)
                ->withErrors(['recepcion' => 'El reporte no cumple las condiciones para registrar recepción física por vencimiento.'])
                ->with('error', 'El reporte no cumple las condiciones para registrar recepción física por vencimiento.');
        }

        $admin = $request->user();
        $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);

        $fechaRecepcion = $request->validated('fecha_recepcion');
        $horaRecepcion = substr((string) $request->validated('hora_recepcion'), 0, 5);
        $condicionObjeto = $request->validated('condicion_objeto');
        $ubicacionResguardo = $request->validated('ubicacion_resguardo');
        $observaciones = $request->validated('observaciones');
        $incidenciaVinculadaId = null;

        try {
            DB::transaction(function () use (
                $id,
                $admin,
                $ahoraMerida,
                $fechaRecepcion,
                $horaRecepcion,
                $condicionObjeto,
                $ubicacionResguardo,
                $observaciones,
                &$incidenciaVinculadaId
            ) {
                // Bloqueo pesimista para evitar concurrencia y doble envío
                $lockedReporte = Publicacion::where('id', $id)->lockForUpdate()->firstOrFail();

                if (! $lockedReporte->esElegibleParaRecepcionFisica()) {
                    throw new \DomainException('El reporte ya no es elegible para registrar recepción física.');
                }

                $incidenciaRecuperacion = Incidencia::where('publicacion_id', $lockedReporte->id)
                    ->where('estado', Incidencia::ESTADO_ESPERA_RECUPERACION)
                    ->where(function ($q) {
                        $q->whereNull('recuperacion_confirmada')
                            ->orWhere('recuperacion_confirmada', false)
                            ->orWhereNull('recepcion_id');
                    })
                    ->latest('id')
                    ->first();

                // 1. Registrar la recepción física
                $recepcion = Recepcion::create([
                    'publicacion_id' => $lockedReporte->id,
                    'incidencia_id' => $incidenciaRecuperacion?->id,
                    'user_id' => $admin->id,
                    'fecha_recepcion' => $fechaRecepcion,
                    'hora_recepcion' => $horaRecepcion,
                    'fecha_captura' => $ahoraMerida,
                    'condicion_objeto' => $condicionObjeto,
                    'ubicacion_resguardo' => $ubicacionResguardo,
                    'observaciones' => $observaciones,
                    'confirmacion_cotejo' => true,
                    'fecha_limite_anual' => Recepcion::calcularFechaLimiteAnual($fechaRecepcion),
                ]);

                // Si la recepción corresponde a la recuperación física de una entrega incorrecta confirmada,
                // se vincula a la incidencia conservando intactos los antecedentes y evidencias de la entrega anterior.
                if ($incidenciaRecuperacion) {
                    $incidenciaVinculadaId = $incidenciaRecuperacion->id;
                    $incidenciaRecuperacion->update([
                        'recepcion_id' => $recepcion->id,
                        'recuperacion_confirmada' => true,
                        'fecha_recuperacion' => $ahoraMerida,
                        'resultado' => 'Recuperación física confirmada mediante recepción en biblioteca',
                    ]);
                }

                // 2. Actualizar el estado y situación del reporte (permanece fuera del muro, sin marcar como correcta la entrega anterior)
                $lockedReporte->situacion_resguardo = 'En biblioteca';
                $lockedReporte->disponible = false;
                $lockedReporte->save();

                // 3. Registrar en historial de decisiones de moderación
                $motivoDecision = $incidenciaRecuperacion
                    ? "Recuperación física confirmada mediante recepción en biblioteca (vinculada a incidencia #{$incidenciaRecuperacion->id}). Ubicación asignada: {$ubicacionResguardo}."
                        . (! empty($condicionObjeto) ? " Condición: {$condicionObjeto}." : '')
                    : "Recepción física registrada en biblioteca. Ubicación asignada: {$ubicacionResguardo}."
                        . (! empty($condicionObjeto) ? " Condición: {$condicionObjeto}." : '');

                DecisionModeracion::create([
                    'publicacion_id' => $lockedReporte->id,
                    'user_id' => $admin->id,
                    'tipo_decision' => 'recepcion_biblioteca',
                    'motivo' => $motivoDecision,
                    'fecha_decision' => $ahoraMerida,
                ]);
            });
        } catch (\DomainException $de) {
            return redirect()->route('admin.moderacion.show', $reporte->id)
                ->withErrors(['recepcion' => $de->getMessage()])
                ->with('error', $de->getMessage());
        } catch (\Throwable $e) {
            Log::error('Error al registrar recepción física por vencimiento: ' . $e->getMessage(), [
                'publicacion_id' => $reporte->id,
                'admin_id' => $admin->id,
                'exception' => $e,
            ]);

            return redirect()->route('admin.moderacion.show', $reporte->id)
                ->withErrors(['recepcion' => 'Ocurrió un error inesperado al registrar la recepción. Intenta nuevamente.'])
                ->with('error', 'Ocurrió un error inesperado al registrar la recepción. Intenta nuevamente.');
        }

        if ($incidenciaVinculadaId && $request->boolean('from_incidencia')) {
            return redirect()->route('admin.incidencias.show', $incidenciaVinculadaId)
                ->with('status', 'La recepción física en biblioteca se registró correctamente y quedó vinculada a la incidencia como confirmación de recuperación.');
        }

        return redirect()->route('admin.moderacion.show', $reporte->id)
            ->with('status', 'La recepción del objeto se registró correctamente');
    }
}

