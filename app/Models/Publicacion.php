<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Publicacion extends Model
{
    use HasFactory;

    public const TIMEZONE = 'America/Merida';

    public const ESTADO_PENDIENTE = 'Pendiente de aprobación';
    public const ESTADO_APROBADA = 'Aprobada';
    public const ESTADO_REQUIERE_CORRECCION = 'Requiere corrección';
    public const ESTADO_CORRECCION_SOLICITADA = 'Corrección solicitada';
    public const ESTADO_CANCELADO = 'Cancelado';
    public const ESTADO_DEVUELTO = 'Devuelto';

    public const RESGUARDO_AUTOR = 'En posesión del autor';
    public const RESGUARDO_CONFIRMADO = 'Bajo resguardo confirmado de biblioteca';

    public const DIAS_VIGENCIA = 14;

    /**
     * Calcula la fecha límite de vigencia a partir de la primera fecha de aprobación.
     * Suma 14 días naturales conservando la misma hora en la zona America/Merida.
     */
    public static function calcularFechaVencimiento(\DateTimeInterface|string $fechaPrimeraAprobacion): Carbon
    {
        $fecha = $fechaPrimeraAprobacion instanceof Carbon
            ? $fechaPrimeraAprobacion->copy()->setTimezone(self::TIMEZONE)
            : Carbon::parse($fechaPrimeraAprobacion, self::TIMEZONE);

        return $fecha->addDays(self::DIAS_VIGENCIA);
    }

    protected $table = 'publicaciones';

    protected $fillable = [
        'user_id',
        'folio',
        'nombre',
        'titulo',
        'descripcion',
        'fecha_hallazgo',
        'hora_hallazgo',
        'area_zona',
        'ubicacion_especifica',
        'fecha_captura',
        'fecha_envio_revision',
        'fecha_primera_aprobacion',
        'fecha_aprobacion',
        'fecha_vencimiento',
        'categoria',
        'lugar_hallazgo',
        'estado',
        'observaciones',
        'folio_referencia_duplicado',
        'version',
        'disponible',
        'devolucion_en_proceso',
        'incidencia_abierta',
        'motivo_correccion',
        'observaciones_admin',
        'situacion_resguardo',
        'motivo_cancelacion',
        'fecha_cancelacion',
        'estado_devolucion',
        'tiene_incidencia',
    ];

    protected function casts(): array
    {
        return [
            'disponible' => 'boolean',
            'devolucion_en_proceso' => 'boolean',
            'incidencia_abierta' => 'boolean',
            'fecha_hallazgo' => 'date:Y-m-d',
            'fecha_captura' => 'datetime',
            'fecha_envio_revision' => 'datetime',
            'fecha_primera_aprobacion' => 'datetime',
            'fecha_aprobacion' => 'datetime',
            'fecha_vencimiento' => 'datetime',
            'version' => 'integer',
            'fecha_cancelacion' => 'datetime',
            'tiene_incidencia' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Publicacion $publicacion) {
            if (empty($publicacion->folio)) {
                $publicacion->folio = static::generarFolio();
            }

            if (empty($publicacion->fecha_captura)) {
                $publicacion->fecha_captura = Carbon::now(self::TIMEZONE);
            }

            if (empty($publicacion->fecha_envio_revision)) {
                $publicacion->fecha_envio_revision = $publicacion->fecha_captura
                    ?? Carbon::now(self::TIMEZONE);
            }

            if (empty($publicacion->titulo) && ! empty($publicacion->nombre)) {
                $publicacion->titulo = $publicacion->nombre;
            } elseif (empty($publicacion->nombre) && ! empty($publicacion->titulo)) {
                $publicacion->nombre = $publicacion->titulo;
            }
        });
    }

    /**
     * Genera un folio con formato OBJ-YYYYMMDD-XXXX.
     */
    public static function generarFolio(): string
    {
        $fechaPrefijo = Carbon::now(self::TIMEZONE)->format('Ymd');
        $prefix = "OBJ-{$fechaPrefijo}-";

        $ultimo = static::where('folio', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $secuencia = 1;

        if ($ultimo && preg_match('/-(\d{4})$/', (string) $ultimo->folio, $matches)) {
            $secuencia = ((int) $matches[1]) + 1;
        }

        do {
            $folio = sprintf('%s%04d', $prefix, $secuencia);
            $existe = static::where('folio', $folio)->exists();

            if ($existe) {
                $secuencia++;
            }
        } while ($existe);

        return $folio;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fotografias(): HasMany
    {
        return $this->hasMany(Fotografia::class);
    }

    public function decisiones(): HasMany
    {
        return $this->hasMany(DecisionModeracion::class)
            ->orderBy('id', 'desc');
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class)
            ->orderBy('id', 'desc');
    }

    public function recepcion(): HasOne
    {
        return $this->hasOne(Recepcion::class);
    }

    public function recepciones(): HasMany
    {
        return $this->hasMany(Recepcion::class)
            ->orderBy('id', 'desc');
    }

    public function devolucionActiva(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Devolucion::class)
            ->whereIn('estado', [
                Devolucion::ESTADO_PENDIENTE, 
                Devolucion::ESTADO_VERIFICADA,
                'Aprobada',
                Devolucion::ESTADO_REQUIERE_CORRECCION,
            ])
            ->latestOfMany();
    }

    public function isCorreccionSolicitada(): bool
    {
        return $this->estado === self::ESTADO_CORRECCION_SOLICITADA;
    }

    public function isCancelado(): bool
    {
        return in_array(
            $this->estado,
            [self::ESTADO_CANCELADO, 'Cancelada'],
            true
        );
    }

    public function isCancelable(): bool
    {
        return ! $this->isCancelado()
            && $this->estado !== self::ESTADO_DEVUELTO;
    }

    public function isVencida(): bool
    {
        if ($this->fecha_vencimiento === null) {
            return false;
        }

        $vencimiento = $this->fecha_vencimiento instanceof Carbon
            ? $this->fecha_vencimiento->copy()->setTimezone(self::TIMEZONE)
            : Carbon::parse($this->fecha_vencimiento, self::TIMEZONE);

        return Carbon::now(self::TIMEZONE)
            ->greaterThanOrEqualTo($vencimiento);
    }

    /**
     * Determina si la publicación vencida requiere entrega física a biblioteca institucional (HU-16 / Tarea 2).
     *
     * Criterios:
     * 1. La publicación debe estar vencida (isVencida()).
     * 2. El objeto continúa bajo resguardo del autor (no confirmado en biblioteca).
     * 3. No existe una devolución activa ni confirmada:
     *    - devolucion_en_proceso es false.
     *    - estado_devolucion no está en 'Pendiente de verificación', 'Verificada' ni 'Devuelto'.
     *    - no existe devolucionActiva con estado pendiente, verificada, aprobada o en corrección.
     *    - Si hubo una devolución anterior cancelada o rechazada, se comprueba el resguardo vigente.
     */
    public function correspondeEntregaBiblioteca(): bool
    {
        if (! $this->isVencida()) {
            return false;
        }

        // Si el objeto ya está bajo resguardo confirmado de biblioteca o recibido, no corresponde entrega pendiente
        if ($this->hasRecepcionFisicaConfirmada() 
            || $this->situacion_resguardo === self::RESGUARDO_CONFIRMADO
            || $this->situacion_resguardo === 'En biblioteca'
            || ($this->relationLoaded('recepcion') ? $this->recepcion !== null : $this->recepcion()->exists())
        ) {
            return false;
        }

        // Si ya fue devuelto o el estado general es devuelto
        if ($this->estado === self::ESTADO_DEVUELTO || $this->estado_devolucion === 'Devuelto') {
            return false;
        }

        // Si hay una devolución en proceso o pendiente de verificación
        if ((bool) $this->devolucion_en_proceso) {
            return false;
        }

        if (in_array($this->estado_devolucion, [
            'Pendiente de verificación',
            'Verificada',
            'Aprobada',
            'Requiere corrección',
        ], true)) {
            return false;
        }

        // Si existe relación devolucionActiva cargada o existente con estado activo
        if ($this->relationLoaded('devolucionActiva')) {
            if ($this->devolucionActiva !== null) {
                return false;
            }
        } elseif ($this->devolucionActiva()->exists()) {
            return false;
        }

        if ($this->devoluciones()->where('estado', '!=', 'Cancelada')->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Determina si el reporte cumple todas las condiciones para registrar recepción física por vencimiento (HU-16 / Tarea 1).
     */
    public function esElegibleParaRecepcionFisica(): bool
    {
        return $this->correspondeEntregaBiblioteca();
    }

    /**
     * Obtiene la disponibilidad en texto institucional (HU-16 / Tarea 2).
     * Muestra «Expirada» si la publicación está vencida.
     */
    public function getDisponibilidadTextoAttribute(): string
    {
        if ($this->isVencida()) {
            return 'Expirada';
        }

        return $this->isDisponible() ? 'Disponible en el muro' : 'Fuera del muro';
    }

    /**
     * Obtiene el seguimiento de la publicación conforme a su estado (HU-16 / Tarea 2).
     * Muestra «Pendiente de entrega a biblioteca» cuando vence y el objeto sigue con el autor.
     */
    public function getSeguimientoTextoAttribute(): ?string
    {
        if ($this->correspondeEntregaBiblioteca()) {
            return 'Pendiente de entrega a biblioteca';
        }

        if ((bool) $this->devolucion_en_proceso || $this->estado_devolucion === 'Pendiente de verificación') {
            return 'Devolución en verificación';
        }

        if ($this->estado_devolucion === 'Requiere corrección') {
            return 'Devolución en corrección';
        }

        if ($this->estado === self::ESTADO_DEVUELTO || $this->estado_devolucion === 'Devuelto' || $this->estado_devolucion === 'Verificada') {
            return 'Devolución confirmada';
        }

        return null;
    }

    /**
     * Plazo institucional para entrega a biblioteca posterior al vencimiento (HU-16 / Tarea 2).
     */
    public function getPlazoEntregaBibliotecaTextoAttribute(): string
    {
        return 'Dispones de dos días de atención de biblioteca posteriores al vencimiento, conforme al horario institucional, para llevar el objeto.';
    }

    /**
     * Genera de manera idempotente el aviso interno institucional de vencimiento para el autor (HU-16 / Tarea 4).
     *
     * Reglas:
     * - Genera un solo aviso por reporte y vencimiento cuando corresponde «Pendiente de entrega a biblioteca».
     * - Incluye el plazo de dos días de atención de biblioteca posteriores al vencimiento.
     * - Emite el evento PublicacionVencidaPendienteEntrega para integración con el bus de eventos y HU-14.
     * - Si el mecanismo de aviso falla o lanza una excepción, esta se aísla para garantizar que
     *   la publicación permanezca fuera de la consulta pública de forma estricta.
     */
    public function despacharAvisoVencimientoSiCorresponde(): bool
    {
        if (! $this->correspondeEntregaBiblioteca()) {
            return false;
        }

        // Evitar avisos duplicados por ejecución repetida
        $cacheKey = "aviso_vencimiento_enviado_publicacion_{$this->id}_{$this->fecha_vencimiento?->timestamp}";
        if (cache()->has($cacheKey)) {
            return false;
        }

        try {
            if ($this->user) {
                event(new \App\Events\PublicacionVencidaPendienteEntrega(
                    $this,
                    $this->user,
                    $this->plazo_entrega_biblioteca_texto
                ));
            }
            cache()->put($cacheKey, true, now()->addDays(30));
            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Fallo en la generación o despacho del aviso de vencimiento (HU-16): ' . $e->getMessage(), [
                'publicacion_id' => $this->id,
                'folio' => $this->folio,
                'exception' => $e,
            ]);
            // Garantizar que un fallo en el mecanismo de aviso no impida la exclusión del muro ni rompa la consulta
            return false;
        }
    }

    public function hasRestriccionPublicacion(): bool
    {
        return $this->isVencida()
            || (bool) $this->devolucion_en_proceso
            || (bool) $this->incidencia_abierta
            || (bool) $this->tiene_incidencia
            || in_array($this->estado_devolucion, ['Pendiente de verificación', 'Verificada', 'En incidencia', 'Devuelto'], true)
            || $this->isCancelado()
            || $this->estado === self::ESTADO_DEVUELTO;
    }

    public function isDisponible(): bool
    {
        return (bool) $this->disponible
            && $this->estado === self::ESTADO_APROBADA
            && ! $this->hasRestriccionPublicacion();
    }

    public function scopeDisponiblesMuro($query)
    {
        // En base de datos SQLite / MySQL, fecha_vencimiento se almacena en UTC según la convención del sistema
        $ahoraUtc = Carbon::now(self::TIMEZONE)->setTimezone(config('app.timezone', 'UTC'));

        return $query->where('disponible', true)
            ->where('estado', self::ESTADO_APROBADA)
            ->where(function ($q) use ($ahoraUtc) {
                $q->whereNull('fecha_vencimiento')
                    ->orWhere('fecha_vencimiento', '>', $ahoraUtc);
            })
            ->where('devolucion_en_proceso', false)
            ->where('incidencia_abierta', false)
            ->where(function ($q) {
                $q->whereNull('tiene_incidencia')
                    ->orWhere('tiene_incidencia', false);
            })
            ->where(function ($q) {
                $q->whereNull('estado_devolucion')
                    ->orWhereNotIn('estado_devolucion', [
                        'Pendiente de verificación',
                        'Verificada',
                        'En incidencia',
                        'Devuelto',
                    ]);
            });
    }

    /**
     * Orden cronológico institucional:
     * Por fecha y hora de hallazgo (desde la más reciente a la más antigua).
     * Si no hay hora registrada de hallazgo, esas aparecen primero para dicha fecha.
     * En caso de empate, desempate por folio en orden descendente.
     */
    public function scopeOrdenCronologicoMuro($query)
    {
        return $query->orderByRaw('COALESCE(fecha_hallazgo, DATE(created_at)) DESC')
            ->orderByRaw("CASE WHEN hora_hallazgo IS NULL OR TRIM(hora_hallazgo) = '' THEN 0 ELSE 1 END ASC")
            ->orderByDesc('hora_hallazgo')
            ->orderByDesc('folio');
    }

    /**
     * Scope para filtrar publicaciones por coincidencia de texto en nombre y descripción (HU-10 / Tarea 10.2).
     *
     * - Consulta exclusivamente el nombre del objeto y su descripción completa.
     * - Normaliza el texto: elimina espacios externos y trata secuencias de espacios como separadores.
     * - Aplica condición AND entre términos (todos los términos deben coincidir).
     * - Cada término puede coincidir en el nombre O en la descripción (o distribuirse entre ambos).
     * - Ignora mayúsculas, minúsculas y acentos.
     * - Admite coincidencias parciales de palabras.
     * - Escapa caracteres especiales de LIKE (%, _, \) para evitar inyecciones y tratarlos como texto literal.
     * - Si el texto está vacío o compuesto únicamente por espacios, no aplica ningún filtro.
     */
    public function scopeBuscarTexto($query, ?string $texto)
    {
        $raw = trim((string) $texto);
        if ($raw === '') {
            return $query;
        }

        $terminos = preg_split('/\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($terminos)) {
            return $query;
        }

        return $query->where(function ($q) use ($terminos) {
            foreach ($terminos as $termino) {
                $escaped = str_replace(
                    ['\\', '%', '_'],
                    ['\\\\', '\\%', '\\_'],
                    $termino
                );
                $param = "%{$escaped}%";

                $q->where(function ($sub) use ($param) {
                    $sub->where('nombre', 'like', $param)
                        ->orWhere('titulo', 'like', $param)
                        ->orWhere('descripcion', 'like', $param);
                });
            }
        });
    }

    /**
     * Scope para filtrar publicaciones por la fecha exacta del hallazgo (HU-10 / Tarea 10.3).
     *
     * - Compara exclusivamente contra el campo 'fecha_hallazgo'.
     * - No utiliza fecha de captura, aprobación, actualización ni reenvío a revisión.
     * - Compara la fecha institucional sin exigir horas específicas.
     */
    public function scopeFiltrarFechaHallazgo($query, ?string $fecha)
    {
        if (empty($fecha)) {
            return $query;
        }

        return $query->whereDate('fecha_hallazgo', '=', $fecha);
    }

    public function isExpirado(): bool
    {
        return $this->isVencida();
    }

    /**
     * Fecha de publicación correspondiente al inicio del periodo autorizado (HU-09.1).
     * No corresponde necesariamente a la fecha de creación original del reporte.
     */
    public function getFechaPublicacionAttribute(): ?Carbon
    {
        $fecha = $this->fecha_primera_aprobacion
            ?? $this->fecha_aprobacion
            ?? $this->created_at;

        if ($fecha === null) {
            return null;
        }

        return $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);
    }

    public function getVigenciaTextoAttribute(): string
    {
        if (! $this->fecha_primera_aprobacion) {
            return 'Aún no inicia su vigencia';
        }

        if ($this->fecha_vencimiento) {
            if ($this->isExpirado()) {
                return 'Vencido el '
                    . $this->fecha_vencimiento->format('d/m/Y');
            }

            return 'Vence el '
                . $this->fecha_vencimiento->format('d/m/Y');
        }

        return 'En revisión';
    }

    public function getFechaRegistroTextoAttribute(): string
    {
        if (! empty($this->fecha_captura)) {
            $raw = $this->getRawOriginal('fecha_captura');

            if ($raw) {
                $formatted = Carbon::parse($raw)->format('d/m/Y • h:i a');

                return str_replace(
                    ['am', 'pm'],
                    ['a. m.', 'p. m.'],
                    $formatted
                );
            }

            $formatted = $this->fecha_captura->format('d/m/Y • h:i a');

            return str_replace(
                ['am', 'pm'],
                ['a. m.', 'p. m.'],
                $formatted
            );
        }

        if (! empty($this->created_at)) {
            $formatted = $this->created_at
                ->timezone(self::TIMEZONE)
                ->format('d/m/Y • h:i a');

            return str_replace(
                ['am', 'pm'],
                ['a. m.', 'p. m.'],
                $formatted
            );
        }

        return '';
    }

    public function getFechaCancelacionTextoAttribute(): string
    {
        if (! $this->fecha_cancelacion) {
            return '';
        }

        $formatted = $this->fecha_cancelacion
            ->timezone(self::TIMEZONE)
            ->format('d/m/Y • h:i a');

        return str_replace(
            ['am', 'pm'],
            ['a. m.', 'p. m.'],
            $formatted
        );
    }

    public function getFechaHallazgoTextoAttribute(): string
    {
        if (! $this->fecha_hallazgo) {
            return 'No especificada';
        }

        $fechaStr = $this->fecha_hallazgo instanceof Carbon
            ? $this->fecha_hallazgo->format('d/m/Y')
            : Carbon::parse($this->fecha_hallazgo)->format('d/m/Y');

        if (! empty($this->hora_hallazgo)) {
            try {
                $horaRaw = Carbon::parse($this->hora_hallazgo)
                    ->format('h:i a');

                $horaStr = str_replace(
                    ['am', 'pm'],
                    ['a. m.', 'p. m.'],
                    $horaRaw
                );

                return "{$fechaStr} • {$horaStr}";
            } catch (\Throwable $e) {
                return "{$fechaStr} • {$this->hora_hallazgo}";
            }
        }

        return $fechaStr;
    }

    public function isEnCorreccion(): bool
    {
        return in_array(
            $this->estado,
            [self::ESTADO_REQUIERE_CORRECCION, self::ESTADO_CORRECCION_SOLICITADA],
            true
        );
    }

    public function puedeEditarse(): bool
    {
        if (
            $this->estado === self::ESTADO_CANCELADO
            || $this->estado === self::ESTADO_DEVUELTO
        ) {
            return false;
        }

        if ($this->isExpirado()) {
            return false;
        }

        if (
            $this->tiene_incidencia
            || in_array(
                $this->estado_devolucion,
                ['Pendiente de verificación', 'Verificada', 'En incidencia'],
                true
            )
        ) {
            return false;
        }

        if ($this->situacion_resguardo === self::RESGUARDO_CONFIRMADO) {
            return false;
        }

        if (
            in_array(
                $this->estado,
                [
                    self::ESTADO_PENDIENTE,
                    self::ESTADO_REQUIERE_CORRECCION,
                    self::ESTADO_CORRECCION_SOLICITADA,
                ],
                true
            )
        ) {
            return true;
        }

        if (
            $this->estado === self::ESTADO_APROBADA
            && (bool) $this->disponible
        ) {
            return true;
        }

        return false;
    }

    public function puedeCancelarse(): bool
    {
        if (
            ! in_array(
                $this->estado,
                [
                    self::ESTADO_PENDIENTE,
                    self::ESTADO_REQUIERE_CORRECCION,
                    self::ESTADO_CORRECCION_SOLICITADA,
                ],
                true
            )
        ) {
            return false;
        }

        if ($this->tiene_incidencia || ! empty($this->estado_devolucion)) {
            return false;
        }

        if ($this->situacion_resguardo === self::RESGUARDO_CONFIRMADO) {
            return false;
        }

        return true;
    }

    /**
     * Determina si la publicación fue cancelada administrativamente por personal de moderación (HU-07 / HU-11).
     */
    public function isCanceladoAdministrativamente(): bool
    {
        if ($this->decisiones()->where('tipo_decision', DecisionModeracion::TIPO_CANCELADA)->exists()) {
            return true;
        }

        if (! empty($this->folio_referencia_duplicado)) {
            return true;
        }

        if (static::where('folio_referencia_duplicado', $this->folio)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Comprueba si la publicación cuenta con devoluciones, incidencias, registros de resguardo
     * u otros antecedentes que deban conservarse institucionalmente (HU-11 / Subtarea 2).
     */
    public function hasProcesosOAntecedentesProtegidos(): bool
    {
        // 1. Procesos o registros de devolución
        if (
            (bool) $this->devolucion_en_proceso
            || ! empty($this->estado_devolucion)
            || $this->estado === self::ESTADO_DEVUELTO
            || ($this->relationLoaded('devoluciones') ? $this->devoluciones->isNotEmpty() : $this->devoluciones()->exists())
        ) {
            return true;
        }

        // 2. Incidencias abiertas o registradas
        if (
            (bool) $this->incidencia_abierta
            || (bool) $this->tiene_incidencia
            || ($this->relationLoaded('decisiones')
                ? $this->decisiones->contains(fn ($d) => str_contains(mb_strtolower((string) $d->tipo_decision), 'incidencia'))
                : $this->decisiones()->where('tipo_decision', 'like', '%incidencia%')->exists())
        ) {
            return true;
        }

        // 3. Situación de resguardo físico confirmada en biblioteca
        if (
            $this->situacion_resguardo === self::RESGUARDO_CONFIRMADO
            || $this->situacion_resguardo === 'En biblioteca'
            || str_contains(mb_strtolower((string) $this->situacion_resguardo), 'biblioteca')
            || str_contains(mb_strtolower((string) $this->situacion_resguardo), 'confirmado')
            || ($this->relationLoaded('recepcion') ? $this->recepcion !== null : $this->recepcion()->exists())
            || ($this->relationLoaded('decisiones')
                ? $this->decisiones->contains(fn ($d) => str_contains(mb_strtolower((string) $d->tipo_decision), 'recepcion'))
                : $this->decisiones()->where('tipo_decision', 'like', '%recepcion%')->exists())
        ) {
            return true;
        }

        // 4. Cancelación administrativa u obligación de conservación por duplicados / decisiones
        if ($this->isCanceladoAdministrativamente()) {
            return true;
        }

        return false;
    }

    /**
     * Obtiene el mensaje explicativo de la restricción que impide la eliminación (HU-11 / Subtarea 2).
     */
    public function getMotivoRestriccionEliminacion(): ?string
    {
        if (! $this->isCancelado()) {
            return 'El estado del reporte cambió. Consulta su información actual antes de continuar';
        }

        if (
            (bool) $this->devolucion_en_proceso
            || ! empty($this->estado_devolucion)
            || $this->estado === self::ESTADO_DEVUELTO
            || ($this->relationLoaded('devoluciones') ? $this->devoluciones->isNotEmpty() : $this->devoluciones()->exists())
        ) {
            return 'No se puede eliminar la publicación porque cuenta con un proceso o registro de devolución que debe conservarse.';
        }

        if (
            (bool) $this->incidencia_abierta
            || (bool) $this->tiene_incidencia
            || ($this->relationLoaded('decisiones')
                ? $this->decisiones->contains(fn ($d) => str_contains(mb_strtolower((string) $d->tipo_decision), 'incidencia'))
                : $this->decisiones()->where('tipo_decision', 'like', '%incidencia%')->exists())
        ) {
            return 'No se puede eliminar la publicación porque tiene una incidencia asociada o en investigación que debe conservarse.';
        }

        if (
            $this->situacion_resguardo === self::RESGUARDO_CONFIRMADO
            || str_contains(mb_strtolower((string) $this->situacion_resguardo), 'confirmado')
            || ($this->relationLoaded('decisiones')
                ? $this->decisiones->contains(fn ($d) => str_contains(mb_strtolower((string) $d->tipo_decision), 'recepcion'))
                : $this->decisiones()->where('tipo_decision', 'like', '%recepcion%')->exists())
        ) {
            return 'No se puede eliminar la publicación porque el objeto cuenta con registro de resguardo físico en biblioteca institucional.';
        }

        if ($this->isCanceladoAdministrativamente()) {
            return 'No se puede eliminar la publicación porque fue cancelada administrativamente y su historial debe conservarse como antecedente institucional.';
        }

        return null;
    }

    /**
     * Determina si la publicación cumple todas las reglas para ser eliminada permanentemente (HU-11 / Subtarea 2).
     */
    public function puedeEliminarsePermanentemente(): bool
    {
        return $this->isCancelado() && ! $this->hasProcesosOAntecedentesProtegidos();
    }

    public function getFotoPrincipalAttribute(): ?Fotografia
    {
        return $this->fotografias->firstWhere('es_principal', true)
            ?? $this->fotografias->first();
    }

    public function isBajoResguardoBiblioteca(): bool
    {
        $lugar = mb_strtolower((string) ($this->lugar_hallazgo ?? ''));
        $area = mb_strtolower((string) ($this->area_zona ?? ''));

        return str_contains($lugar, 'biblioteca')
            || str_contains($area, 'biblioteca');
    }

    /**
     * Determina si la publicación cuenta con recepción física confirmada en biblioteca (HU-09.3).
     * No se considera confirmada si la recepción está pendiente o en solicitud.
     */
    public function hasRecepcionFisicaConfirmada(): bool
    {
        if (! empty($this->situacion_resguardo)) {
            $sit = mb_strtolower(trim($this->situacion_resguardo));
            // Si la recepción o solicitud está pendiente, no es recepción física confirmada
            if (str_contains($sit, 'pendiente') || str_contains($sit, 'solicitud')) {
                return false;
            }
            if ($this->situacion_resguardo === self::RESGUARDO_CONFIRMADO || str_contains($sit, 'confirmado')) {
                return true;
            }

            // Si explícitamente está en posesión del autor y no es un hallazgo ocurrido en biblioteca
            if (($this->situacion_resguardo === self::RESGUARDO_AUTOR || str_contains($sit, 'posesión del autor') || str_contains($sit, 'autor'))
                && ! $this->isBajoResguardoBiblioteca()
            ) {
                return false;
            }
        }

        return $this->isBajoResguardoBiblioteca();
    }

    /**
     * Obtiene el teléfono vigente y válido (10 dígitos) del autor del reporte (HU-09.3).
     */
    public function getTelefonoAutorValidoAttribute(): ?string
    {
        return $this->user?->telefono_valido;
    }

    public function scopePendientesRevision($query)
    {
        return $query->whereIn('estado', [
            self::ESTADO_PENDIENTE,
            'Pendiente de revisión',
            'Pendiente',
        ]);
    }

    public function isPendienteRevision(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_PENDIENTE,
            'Pendiente de revisión',
            'Pendiente',
        ], true);
    }

    public function reenviarARevision(): void
    {
        $this->update([
            'estado' => self::ESTADO_PENDIENTE,
            'fecha_envio_revision' => Carbon::now(self::TIMEZONE),
        ]);
    }

    /**
     * Determina si la publicación cumple las condiciones para que su autor original
     * registre una devolución física (HU-11 / CA-01, CA-02).
     */
    public function puedeRegistrarDevolucion(): bool
    {
        // 1. Debe estar aprobada
        if ($this->estado !== self::ESTADO_APROBADA) {
            return false;
        }

        // 2. Debe estar disponible
        if (! (bool) $this->disponible) {
            return false;
        }

        // 3. Debe estar vigente (no vencida)
        if ($this->isVencida()) {
            return false;
        }

        // 4. Debe continuar bajo resguardo del autor (no bajo resguardo confirmado de biblioteca)
        if ($this->situacion_resguardo === self::RESGUARDO_CONFIRMADO) {
            return false;
        }

        // 5. No debe existir otra devolución activa o en proceso
        if ((bool) $this->devolucion_en_proceso || in_array($this->estado_devolucion, ['Pendiente de verificación', 'Verificada', 'En proceso', 'Devuelto'], true)) {
            return false;
        }

        // 6. No debe existir una incidencia que impida realizar la operación
        if ((bool) $this->incidencia_abierta || (bool) $this->tiene_incidencia || $this->estado_devolucion === 'En incidencia') {
            return false;
        }

        return true;
    }

    /**
     * Determina si la publicación cumple las condiciones para registrar devolución desde biblioteca (CA-01, CA-11).
     *
     * Condiciones:
     * 1. Debe existir recepción física confirmada en biblioteca (hasRecepcionFisicaConfirmada() o situacion_resguardo confirmado/En biblioteca con registro de recepción).
     * 2. El objeto debe continuar bajo resguardo de biblioteca (no haber sido devuelto ni tener salida física).
     * 3. No debe estar devuelto (estado !== 'Devuelto' y estado_devolucion !== 'Devuelto' y estado_devolucion !== 'Verificada').
     * 4. Aplica incluso si la publicación ha expirado o finalizó el periodo anual de resguardo.
     */
    public function puedeRegistrarDevolucionDesdeBiblioteca(): bool
    {
        // 1. Recepción física confirmada existente
        $tieneRecepcion = $this->relationLoaded('recepcion')
            ? $this->recepcion !== null
            : $this->recepcion()->exists();

        if (! $tieneRecepcion) {
            return false;
        }

        // 2. Situación de resguardo confirmada o en biblioteca
        $sit = mb_strtolower(trim((string) $this->situacion_resguardo));
        $esEnBiblioteca = $this->situacion_resguardo === self::RESGUARDO_CONFIRMADO
            || $this->situacion_resguardo === 'En biblioteca'
            || str_contains($sit, 'biblioteca')
            || str_contains($sit, 'confirmado');

        if (! $esEnBiblioteca) {
            return false;
        }

        // 3. No debe tener salida ya registrada ni estar devuelto
        if ($this->estado === self::ESTADO_DEVUELTO) {
            return false;
        }

        if (in_array($this->estado_devolucion, ['Devuelto', 'Verificada'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Obtiene el listado completo de eventos históricos del objeto en orden cronológico ascendente.
     * Incluye creación, aprobación, corrección, cancelación, vencimiento, recepción, devolución e incidencia.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getEventosHistorial(): \Illuminate\Support\Collection
    {
        $eventos = collect();

        // 1. EVENTO: CREACIÓN
        $fechaCreacion = $this->fecha_captura ?? $this->created_at ?? Carbon::now(self::TIMEZONE);
        if (! $fechaCreacion instanceof Carbon) {
            $fechaCreacion = Carbon::parse($fechaCreacion, self::TIMEZONE);
        }

        $eventos->push([
            'tipo' => 'Creación',
            'tipo_slug' => 'creacion',
            'fecha' => $fechaCreacion,
            'fecha_hora_texto' => $this->formatearFechaHora($fechaCreacion),
            'responsable' => $this->user ? $this->user->name : 'Autor del reporte',
            'motivo' => ! empty($this->descripcion)
                ? "Registro inicial del objeto reportado por el autor con folio {$this->folio}. Descripción: {$this->descripcion}"
                : "Registro inicial del reporte de hallazgo con folio {$this->folio}.",
            'color' => 'wine',
            'orden_prioridad' => 10,
        ]);

        // 2. EVENTOS: DECISIONES DE MODERACIÓN (Aprobaciones, Correcciones, Cancelaciones, Devoluciones, Incidencias)
        $hasAprobacionEnDecisiones = false;
        $hasCancelacionEnDecisiones = false;
        $hasRecepcionEnDecisiones = false;

        if ($this->relationLoaded('decisiones') || $this->decisiones()->exists()) {
            foreach ($this->decisiones as $decision) {
                $fechaDecision = $decision->fecha_decision ?? $decision->created_at ?? Carbon::now(self::TIMEZONE);
                if (! $fechaDecision instanceof Carbon) {
                    $fechaDecision = Carbon::parse($fechaDecision, self::TIMEZONE);
                }

                $responsableDecision = $decision->user ? $decision->user->name : 'Administrador/bibliotecario';
                $tipoDecision = (string) $decision->tipo_decision;

                if ($tipoDecision === DecisionModeracion::TIPO_APROBADA || $tipoDecision === 'aprobada') {
                    $hasAprobacionEnDecisiones = true;
                    $eventos->push([
                        'tipo' => 'Aprobación',
                        'tipo_slug' => 'aprobacion',
                        'fecha' => $fechaDecision,
                        'fecha_hora_texto' => $this->formatearFechaHora($fechaDecision),
                        'responsable' => $responsableDecision,
                        'motivo' => ! empty($decision->motivo)
                            ? $decision->motivo
                            : 'Publicación autorizada y vigente para el muro institucional.',
                        'color' => 'emerald',
                        'orden_prioridad' => 20,
                    ]);
                } elseif ($tipoDecision === DecisionModeracion::TIPO_CORRECCION_SOLICITADA || $tipoDecision === 'correccion_solicitada') {
                    $eventos->push([
                        'tipo' => 'Solicitud de corrección',
                        'tipo_slug' => 'correccion',
                        'fecha' => $fechaDecision,
                        'fecha_hora_texto' => $this->formatearFechaHora($fechaDecision),
                        'responsable' => $responsableDecision,
                        'motivo' => ! empty($decision->motivo)
                            ? $decision->motivo
                            : ($this->motivo_correccion ?? 'Se requiere corrección de datos para autorizar el reporte.'),
                        'color' => 'amber',
                        'orden_prioridad' => 30,
                    ]);
                } elseif ($tipoDecision === DecisionModeracion::TIPO_CANCELADA || $tipoDecision === 'cancelada') {
                    $hasCancelacionEnDecisiones = true;
                    $eventos->push([
                        'tipo' => 'Cancelación',
                        'tipo_slug' => 'cancelacion',
                        'fecha' => $fechaDecision,
                        'fecha_hora_texto' => $this->formatearFechaHora($fechaDecision),
                        'responsable' => $responsableDecision,
                        'motivo' => ! empty($decision->motivo)
                            ? $decision->motivo
                            : ($this->motivo_cancelacion ?? 'Publicación cancelada administrativamente.'),
                        'color' => 'slate',
                        'orden_prioridad' => 70,
                    ]);
                } elseif ($tipoDecision === DecisionModeracion::TIPO_DEVOLUCION_APROBADA || $tipoDecision === 'devolucion_aprobada' || $tipoDecision === DecisionModeracion::TIPO_DEVOLUCION_BIBLIOTECA || $tipoDecision === 'devolucion_biblioteca') {
                    $eventos->push([
                        'tipo' => 'Devolución desde biblioteca',
                        'tipo_slug' => 'devolucion',
                        'fecha' => $fechaDecision,
                        'fecha_hora_texto' => $this->formatearFechaHora($fechaDecision),
                        'responsable' => $responsableDecision,
                        'motivo' => ! empty($decision->motivo)
                            ? $decision->motivo
                            : 'Devolución física y salida del resguardo registradas directamente desde biblioteca.',
                        'color' => 'emerald',
                        'orden_prioridad' => 50,
                    ]);
                } elseif ($tipoDecision === DecisionModeracion::TIPO_DEVOLUCION_CORRECCION || $tipoDecision === 'devolucion_correccion') {
                    $eventos->push([
                        'tipo' => 'Corrección de devolución',
                        'tipo_slug' => 'correccion',
                        'fecha' => $fechaDecision,
                        'fecha_hora_texto' => $this->formatearFechaHora($fechaDecision),
                        'responsable' => $responsableDecision,
                        'motivo' => ! empty($decision->motivo)
                            ? $decision->motivo
                            : 'Se solicitó corrección en la documentación o evidencia de la devolución.',
                        'color' => 'orange',
                        'orden_prioridad' => 55,
                    ]);
                } elseif (str_contains(mb_strtolower($tipoDecision), 'recepcion')) {
                    $hasRecepcionEnDecisiones = true;
                    $eventos->push([
                        'tipo' => 'Recepción en biblioteca',
                        'tipo_slug' => 'recepcion',
                        'fecha' => $fechaDecision,
                        'fecha_hora_texto' => $this->formatearFechaHora($fechaDecision),
                        'responsable' => $responsableDecision,
                        'motivo' => ! empty($decision->motivo)
                            ? $decision->motivo
                            : 'Objeto recibido físicamente en biblioteca bajo resguardo institucional confirmado.',
                        'color' => 'blue',
                        'orden_prioridad' => 35,
                    ]);
                } elseif (str_contains(mb_strtolower($tipoDecision), 'ubicacion')) {
                    $eventos->push([
                        'tipo' => 'Cambio de ubicación en biblioteca',
                        'tipo_slug' => 'cambio_ubicacion',
                        'fecha' => $fechaDecision,
                        'fecha_hora_texto' => $this->formatearFechaHora($fechaDecision),
                        'responsable' => $responsableDecision,
                        'motivo' => ! empty($decision->motivo)
                            ? $decision->motivo
                            : 'Actualización de ubicación física de resguardo en biblioteca.',
                        'color' => 'indigo',
                        'orden_prioridad' => 37,
                    ]);
                } elseif (str_contains(mb_strtolower($tipoDecision), 'incidencia')) {
                    $eventos->push([
                        'tipo' => 'Incidencia',
                        'tipo_slug' => 'incidencia',
                        'fecha' => $fechaDecision,
                        'fecha_hora_texto' => $this->formatearFechaHora($fechaDecision),
                        'responsable' => $responsableDecision,
                        'motivo' => ! empty($decision->motivo)
                            ? $decision->motivo
                            : 'Incidencia registrada en el proceso del objeto.',
                        'color' => 'rose',
                        'orden_prioridad' => 60,
                    ]);
                }
            }
        }

        // 3. EVENTO: APROBACIÓN POR FECHA / ESTADO (si no provino de la colección de decisiones)
        if (! $hasAprobacionEnDecisiones && ($this->fecha_primera_aprobacion || $this->fecha_aprobacion || $this->estado === self::ESTADO_APROBADA)) {
            $fechaAprob = $this->fecha_primera_aprobacion ?? $this->fecha_aprobacion ?? $fechaCreacion->copy()->addMinutes(30);
            if (! $fechaAprob instanceof Carbon) {
                $fechaAprob = Carbon::parse($fechaAprob, self::TIMEZONE);
            }
            $eventos->push([
                'tipo' => 'Aprobación',
                'tipo_slug' => 'aprobacion',
                'fecha' => $fechaAprob,
                'fecha_hora_texto' => $this->formatearFechaHora($fechaAprob),
                'responsable' => 'Personal de biblioteca',
                'motivo' => 'Publicación autorizada y vigente para el muro institucional.',
                'color' => 'emerald',
                'orden_prioridad' => 20,
            ]);
        }

        // 4. EVENTO: CORRECCIÓN POR ATRIBUTOS (si no provino de la colección de decisiones)
        if ($eventos->where('tipo_slug', 'correccion')->isEmpty() && ($this->isEnCorreccion() || ! empty($this->motivo_correccion) || in_array($this->estado, [self::ESTADO_CORRECCION_SOLICITADA, self::ESTADO_REQUIERE_CORRECCION], true))) {
            $fechaCorr = $this->updated_at ?? $fechaCreacion->copy()->addMinutes(15);
            if (! $fechaCorr instanceof Carbon) {
                $fechaCorr = Carbon::parse($fechaCorr, self::TIMEZONE);
            }
            $eventos->push([
                'tipo' => 'Solicitud de corrección',
                'tipo_slug' => 'correccion',
                'fecha' => $fechaCorr,
                'fecha_hora_texto' => $this->formatearFechaHora($fechaCorr),
                'responsable' => 'Personal de biblioteca',
                'motivo' => $this->motivo_correccion ?? $this->observaciones_admin ?? $this->observaciones ?? 'Se requiere corrección de datos para autorizar el reporte.',
                'color' => 'amber',
                'orden_prioridad' => 30,
            ]);
        }

        // 5. EVENTO: RECEPCIÓN FÍSICA EN BIBLIOTECA
        if (! $hasRecepcionEnDecisiones && ($this->hasRecepcionFisicaConfirmada() || $this->situacion_resguardo === self::RESGUARDO_CONFIRMADO)) {
            $fechaRecep = $this->updated_at ?? $fechaCreacion;
            if (! $fechaRecep instanceof Carbon) {
                $fechaRecep = Carbon::parse($fechaRecep, self::TIMEZONE);
            }
            $eventos->push([
                'tipo' => 'Recepción en biblioteca',
                'tipo_slug' => 'recepcion',
                'fecha' => $fechaRecep,
                'fecha_hora_texto' => $this->formatearFechaHora($fechaRecep),
                'responsable' => 'Personal de biblioteca',
                'motivo' => 'Objeto entregado físicamente en biblioteca bajo resguardo institucional confirmado.',
                'color' => 'blue',
                'orden_prioridad' => 35,
            ]);
        }

        // 6. EVENTO: VENCIMIENTO (si la fecha de vencimiento ya transcurrió)
        if ($this->fecha_vencimiento) {
            $fechaVenc = $this->fecha_vencimiento instanceof Carbon
                ? $this->fecha_vencimiento
                : Carbon::parse($this->fecha_vencimiento, self::TIMEZONE);

            if (Carbon::now(self::TIMEZONE)->greaterThanOrEqualTo($fechaVenc)) {
                $eventos->push([
                    'tipo' => 'Vencimiento',
                    'tipo_slug' => 'vencimiento',
                    'fecha' => $fechaVenc,
                    'fecha_hora_texto' => $this->formatearFechaHora($fechaVenc),
                    'responsable' => 'Sistema institucional',
                    'motivo' => 'Término del periodo ordinario de vigencia de 14 días naturales en el muro institucional.',
                    'color' => 'slate',
                    'orden_prioridad' => 40,
                ]);

                // 6.1 EVENTO: PENDIENTE DE ENTREGA A BIBLIOTECA (HU-16 / Tarea 2)
                // Se genera un solo evento cuando vence, sigue bajo resguardo del autor y no hay devolución activa
                if ($this->correspondeEntregaBiblioteca()) {
                    $eventos->push([
                        'tipo' => 'Pendiente de entrega a biblioteca',
                        'tipo_slug' => 'pendiente_entrega_biblioteca',
                        'fecha' => $fechaVenc,
                        'fecha_hora_texto' => $this->formatearFechaHora($fechaVenc),
                        'responsable' => 'Sistema institucional',
                        'motivo' => 'Publicación expirada. Objeto pendiente de entrega física a biblioteca institucional. ' . $this->plazo_entrega_biblioteca_texto,
                        'color' => 'amber',
                        'orden_prioridad' => 42,
                    ]);
                }
            }
        }

        // 7. EVENTOS: DEVOLUCIONES FÍSICAS (cada entrega independiente)
        if ($this->relationLoaded('devoluciones') || $this->devoluciones()->exists()) {
            foreach ($this->devoluciones as $dev) {
                $fechaDev = $this->obtenerFechaHoraDevolucion($dev);
                $respDev = $dev->user ? $dev->user->name : ($this->user ? $this->user->name : 'Autor del reporte');

                $partesObs = [];
                if (! empty($dev->receptor_nombre)) {
                    $idTxt = ! empty($dev->receptor_identificador) ? " ({$dev->receptor_identificador})" : '';
                    $partesObs[] = "Receptor: {$dev->receptor_nombre}{$idTxt}";
                }
                if (! empty($dev->explicacion_propiedad)) {
                    $partesObs[] = "Comprobación de propiedad: {$dev->explicacion_propiedad}";
                }
                if (! empty($dev->estado)) {
                    $partesObs[] = "Estado de revisión: {$dev->estado}";
                }
                $motivoDev = ! empty($partesObs) ? implode(' • ', $partesObs) : 'Devolución física registrada.';

                $eventos->push([
                    'tipo' => 'Devolución',
                    'tipo_slug' => 'devolucion',
                    'fecha' => $fechaDev,
                    'fecha_hora_texto' => $this->formatearFechaHora($fechaDev),
                    'responsable' => $respDev,
                    'motivo' => $motivoDev,
                    'color' => 'emerald',
                    'orden_prioridad' => 45,
                    'devolucion' => $dev,
                ]);
            }
        }

        // 8. EVENTO: INCIDENCIA (si está abierta o registrada y no provino de decisiones)
        if (($this->incidencia_abierta || $this->tiene_incidencia || $this->estado_devolucion === 'En incidencia') && $eventos->where('tipo_slug', 'incidencia')->isEmpty()) {
            $fechaInc = $this->updated_at ?? Carbon::now(self::TIMEZONE);
            if (! $fechaInc instanceof Carbon) {
                $fechaInc = Carbon::parse($fechaInc, self::TIMEZONE);
            }
            $eventos->push([
                'tipo' => 'Incidencia',
                'tipo_slug' => 'incidencia',
                'fecha' => $fechaInc,
                'fecha_hora_texto' => $this->formatearFechaHora($fechaInc),
                'responsable' => 'Personal de biblioteca / Administración',
                'motivo' => 'Incidencia abierta: entrega física o resguardo bajo aclaración e investigación institucional.',
                'color' => 'rose',
                'orden_prioridad' => 60,
            ]);
        }

        // 9. EVENTO: CANCELACIÓN DIRECTA (si no provino de decisiones)
        if (! $hasCancelacionEnDecisiones && ($this->isCancelado() || $this->fecha_cancelacion)) {
            $fechaCanc = $this->fecha_cancelacion ?? $this->updated_at ?? Carbon::now(self::TIMEZONE);
            if (! $fechaCanc instanceof Carbon) {
                $fechaCanc = Carbon::parse($fechaCanc, self::TIMEZONE);
            }
            $eventos->push([
                'tipo' => 'Cancelación',
                'tipo_slug' => 'cancelacion',
                'fecha' => $fechaCanc,
                'fecha_hora_texto' => $this->formatearFechaHora($fechaCanc),
                'responsable' => $this->user ? $this->user->name : 'Autor del reporte',
                'motivo' => ! empty($this->motivo_cancelacion)
                    ? $this->motivo_cancelacion
                    : 'Reporte cancelado.',
                'color' => 'slate',
                'orden_prioridad' => 70,
            ]);
        }

        // Ordenamiento cronológico ascendente (del más antiguo al más reciente)
        return $eventos->sort(function ($a, $b) {
            $tsA = $a['fecha']->timestamp;
            $tsB = $b['fecha']->timestamp;

            if ($tsA !== $tsB) {
                return $tsA <=> $tsB;
            }

            return ($a['orden_prioridad'] ?? 50) <=> ($b['orden_prioridad'] ?? 50);
        })->values();
    }

    /**
     * Formatea una fecha y hora en formato legible en español.
     */
    public function formatearFechaHora(Carbon $fecha): string
    {
        $fechaStr = $fecha->format('d/m/Y');
        $horaRaw = $fecha->format('h:i a');
        $horaStr = str_replace(['am', 'pm'], ['a. m.', 'p. m.'], $horaRaw);

        return "{$fechaStr} • {$horaStr}";
    }

    /**
     * Obtiene la fecha y hora precisa combinada para una devolución.
     */
    public function obtenerFechaHoraDevolucion(Devolucion $dev): Carbon
    {
        if ($dev->fecha_entrega) {
            $fechaBase = $dev->fecha_entrega instanceof Carbon
                ? $dev->fecha_entrega->format('Y-m-d')
                : Carbon::parse($dev->fecha_entrega)->format('Y-m-d');

            $horaStr = ! empty($dev->hora_entrega) ? (string) $dev->hora_entrega : '12:00:00';
            try {
                return Carbon::parse("{$fechaBase} {$horaStr}", self::TIMEZONE);
            } catch (\Throwable $e) {
                return Carbon::parse($fechaBase, self::TIMEZONE);
            }
        }

        if ($dev->fecha_captura) {
            return $dev->fecha_captura instanceof Carbon
                ? $dev->fecha_captura
                : Carbon::parse($dev->fecha_captura, self::TIMEZONE);
        }

        return $dev->created_at ? Carbon::parse($dev->created_at, self::TIMEZONE) : Carbon::now(self::TIMEZONE);
    }
}
