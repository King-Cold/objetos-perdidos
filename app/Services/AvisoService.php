<?php

namespace App\Services;

use App\Models\Aviso;
use App\Models\Devolucion;
use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

class AvisoService
{
    /**
     * Genera un aviso automático al autor cuando su reporte/publicación es aprobada.
     */
    public static function generarAvisoPublicacionAprobada(Publicacion $publicacion, ?string $motivo = null): ?Aviso
    {
        try {
            if (empty($publicacion->id) || empty($publicacion->user_id)) {
                return null;
            }

            $version = (int) ($publicacion->version ?? 1);
            $claveDuplicado = "pub_aprobada_{$publicacion->id}_v{$version}";
            $nombre = self::obtenerNombreObjeto($publicacion);
            $folio = $publicacion->folio ?? 'Sin folio';
            $motivoTexto = ! empty($motivo) ? trim($motivo) : 'Publicación autorizada y vigente para el muro institucional.';

            return self::registrarAvisoSeguro([
                'user_id' => $publicacion->user_id,
                'publicacion_id' => $publicacion->id,
                'devolucion_id' => null,
                'tipo_evento' => Aviso::TIPO_PUBLICACION_APROBADA,
                'titulo' => 'Publicación aprobada en el muro institucional',
                'folio_reporte' => $folio,
                'mensaje' => "Tu reporte «{$nombre}» (Folio: {$folio}) ha sido aprobado por el personal de biblioteca y ya se encuentra visible en el muro institucional de objetos perdidos.",
                'motivo_autorizado' => $motivoTexto,
                'enlace_destino' => "/reportes/{$publicacion->id}",
            ], $claveDuplicado);
        } catch (\Throwable $e) {
            Log::error('Error general al preparar aviso de publicación aprobada: ' . $e->getMessage(), [
                'publicacion_id' => $publicacion->id ?? null,
                'user_id' => $publicacion->user_id ?? null,
                'exception_class' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Genera un aviso automático al autor cuando se solicita corrección de su reporte.
     */
    public static function generarAvisoPublicacionCorreccion(Publicacion $publicacion, string $motivo): ?Aviso
    {
        try {
            if (empty($publicacion->id) || empty($publicacion->user_id)) {
                return null;
            }

            $version = (int) ($publicacion->version ?? 1);
            $claveDuplicado = "pub_correccion_{$publicacion->id}_v{$version}";
            $nombre = self::obtenerNombreObjeto($publicacion);
            $folio = $publicacion->folio ?? 'Sin folio';
            $motivoTexto = trim($motivo);

            return self::registrarAvisoSeguro([
                'user_id' => $publicacion->user_id,
                'publicacion_id' => $publicacion->id,
                'devolucion_id' => null,
                'tipo_evento' => Aviso::TIPO_PUBLICACION_CORRECCION,
                'titulo' => 'Corrección requerida para tu reporte',
                'folio_reporte' => $folio,
                'mensaje' => "El personal de biblioteca revisó tu reporte «{$nombre}» (Folio: {$folio}) y requiere que realices correcciones en la información para continuar con el proceso.",
                'motivo_autorizado' => $motivoTexto,
                'enlace_destino' => "/reportes/{$publicacion->id}",
            ], $claveDuplicado);
        } catch (\Throwable $e) {
            Log::error('Error general al preparar aviso de corrección de publicación: ' . $e->getMessage(), [
                'publicacion_id' => $publicacion->id ?? null,
                'user_id' => $publicacion->user_id ?? null,
                'exception_class' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Genera un aviso automático al autor cuando su reporte es cancelado administrativamente.
     */
    public static function generarAvisoPublicacionCancelada(Publicacion $publicacion, string $motivo): ?Aviso
    {
        try {
            if (empty($publicacion->id) || empty($publicacion->user_id)) {
                return null;
            }

            // Una cancelación administrativa es definitiva para esa publicación
            $claveDuplicado = "pub_cancelada_{$publicacion->id}";
            if (Aviso::where('publicacion_id', $publicacion->id)->where('tipo_evento', Aviso::TIPO_PUBLICACION_CANCELADA)->exists()) {
                Log::info("Aviso de cancelación administrativa omitido porque ya existe un registro previo.", [
                    'publicacion_id' => $publicacion->id,
                    'user_id' => $publicacion->user_id,
                ]);

                return null;
            }

            $nombre = self::obtenerNombreObjeto($publicacion);
            $folio = $publicacion->folio ?? 'Sin folio';
            $motivoTexto = trim($motivo);

            return self::registrarAvisoSeguro([
                'user_id' => $publicacion->user_id,
                'publicacion_id' => $publicacion->id,
                'devolucion_id' => null,
                'tipo_evento' => Aviso::TIPO_PUBLICACION_CANCELADA,
                'titulo' => 'Publicación cancelada administrativamente',
                'folio_reporte' => $folio,
                'mensaje' => "Tu reporte «{$nombre}» (Folio: {$folio}) ha sido cancelado administrativamente por el personal de biblioteca.",
                'motivo_autorizado' => $motivoTexto,
                'enlace_destino' => "/reportes/{$publicacion->id}",
            ], $claveDuplicado);
        } catch (\Throwable $e) {
            Log::error('Error general al preparar aviso de publicación cancelada: ' . $e->getMessage(), [
                'publicacion_id' => $publicacion->id ?? null,
                'user_id' => $publicacion->user_id ?? null,
                'exception_class' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Genera un aviso automático al autor cuando la devolución física es aprobada/verificada.
     */
    public static function generarAvisoDevolucionAprobada(Devolucion $devolucion, ?string $motivo = null): ?Aviso
    {
        try {
            if (empty($devolucion->id)) {
                return null;
            }

            $publicacion = $devolucion->publicacion ?? Publicacion::find($devolucion->publicacion_id);
            $destinatarioId = $publicacion?->user_id ?? $devolucion->user_id;

            if (empty($destinatarioId)) {
                return null;
            }

            // La aprobación de devolución física es un evento final definitivo
            $claveDuplicado = "dev_aprobada_{$devolucion->id}";
            if (Aviso::where('devolucion_id', $devolucion->id)->where('tipo_evento', Aviso::TIPO_DEVOLUCION_APROBADA)->exists()) {
                Log::info("Aviso de devolución aprobada omitido porque ya existe un registro previo.", [
                    'devolucion_id' => $devolucion->id,
                    'destinatario_id' => $destinatarioId,
                ]);

                return null;
            }

            $nombre = $publicacion ? self::obtenerNombreObjeto($publicacion) : 'Objeto extraviado';
            $folio = $publicacion?->folio ?? 'Sin folio';
            $motivoTexto = ! empty($motivo) ? trim($motivo) : 'Devolución física verificada y aprobada tras validación institucional de entrega y evidencia.';

            return self::registrarAvisoSeguro([
                'user_id' => $destinatarioId,
                'publicacion_id' => $publicacion?->id,
                'devolucion_id' => $devolucion->id,
                'tipo_evento' => Aviso::TIPO_DEVOLUCION_APROBADA,
                'titulo' => 'Devolución física verificada y confirmada',
                'folio_reporte' => $folio,
                'mensaje' => "La entrega física del objeto «{$nombre}» (Folio: {$folio}) ha sido verificada y confirmada exitosamente por el personal de biblioteca. El proceso ha concluido.",
                'motivo_autorizado' => $motivoTexto,
                'enlace_destino' => "/devoluciones/{$devolucion->id}",
            ], $claveDuplicado);
        } catch (\Throwable $e) {
            Log::error('Error general al preparar aviso de devolución aprobada: ' . $e->getMessage(), [
                'devolucion_id' => $devolucion->id ?? null,
                'exception_class' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Genera un aviso automático al autor cuando se solicitan correcciones en la devolución física.
     */
    public static function generarAvisoDevolucionCorreccion(Devolucion $devolucion, string $motivo): ?Aviso
    {
        try {
            if (empty($devolucion->id)) {
                return null;
            }

            $publicacion = $devolucion->publicacion ?? Publicacion::find($devolucion->publicacion_id);
            $destinatarioId = $publicacion?->user_id ?? $devolucion->user_id;

            if (empty($destinatarioId)) {
                return null;
            }

            $token = $devolucion->fecha_resolucion?->timestamp
                ?? $devolucion->fecha_envio_revision?->timestamp
                ?? $devolucion->updated_at?->timestamp
                ?? substr(md5(trim($motivo)), 0, 8);
            $claveDuplicado = "dev_correccion_{$devolucion->id}_{$token}";

            $nombre = $publicacion ? self::obtenerNombreObjeto($publicacion) : 'Objeto extraviado';
            $folio = $publicacion?->folio ?? 'Sin folio';
            $motivoTexto = trim($motivo);

            return self::registrarAvisoSeguro([
                'user_id' => $destinatarioId,
                'publicacion_id' => $publicacion?->id,
                'devolucion_id' => $devolucion->id,
                'tipo_evento' => Aviso::TIPO_DEVOLUCION_CORRECCION,
                'titulo' => 'Corrección requerida en entrega física',
                'folio_reporte' => $folio,
                'mensaje' => "Se requieren correcciones o aclaraciones en los datos o evidencia de la entrega del objeto «{$nombre}» (Folio: {$folio}).",
                'motivo_autorizado' => $motivoTexto,
                'enlace_destino' => "/devoluciones/{$devolucion->id}",
            ], $claveDuplicado);
        } catch (\Throwable $e) {
            Log::error('Error general al preparar aviso de corrección de devolución: ' . $e->getMessage(), [
                'devolucion_id' => $devolucion->id ?? null,
                'exception_class' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Genera un aviso automático al autor cuando el periodo ordinario de vigencia (14 días) ha vencido
     * para que entregue el objeto bajo resguardo en biblioteca.
     */
    public static function generarAvisoPublicacionVencida(Publicacion $publicacion): ?Aviso
    {
        try {
            if (empty($publicacion->id) || empty($publicacion->user_id)) {
                return null;
            }

            $claveDuplicado = "vencimiento_pub_{$publicacion->id}";

            // Evitar duplicados: Si ya existe un aviso de vencimiento para esta publicación, no generar otro
            if (Aviso::where('publicacion_id', $publicacion->id)->where('tipo_evento', Aviso::TIPO_PUBLICACION_VENCIDA)->exists()) {
                Log::info("Aviso de vencimiento omitido porque ya existe un registro previo.", [
                    'publicacion_id' => $publicacion->id,
                    'user_id' => $publicacion->user_id,
                ]);

                return null;
            }

            $nombre = self::obtenerNombreObjeto($publicacion);
            $folio = $publicacion->folio ?? 'Sin folio';
            $motivoTexto = 'Término del periodo ordinario de vigencia de 14 días naturales en el muro institucional.';

            return self::registrarAvisoSeguro([
                'user_id' => $publicacion->user_id,
                'publicacion_id' => $publicacion->id,
                'devolucion_id' => null,
                'tipo_evento' => Aviso::TIPO_PUBLICACION_VENCIDA,
                'titulo' => 'Periodo ordinario concluido: entrega en biblioteca',
                'folio_reporte' => $folio,
                'mensaje' => "El periodo ordinario de vigencia de 14 días naturales para tu reporte «{$nombre}» (Folio: {$folio}) ha concluido. Conforme al reglamento institucional, por favor acude a entregar el objeto en resguardo directo de la biblioteca del campus.",
                'motivo_autorizado' => $motivoTexto,
                'enlace_destino' => "/reportes/{$publicacion->id}",
            ], $claveDuplicado);
        } catch (\Throwable $e) {
            Log::error('Error general al preparar aviso de publicación vencida: ' . $e->getMessage(), [
                'publicacion_id' => $publicacion->id ?? null,
                'user_id' => $publicacion->user_id ?? null,
                'exception_class' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Revisa todas las publicaciones aprobadas cuya vigencia ha expirado y genera los avisos pendientes.
     * Retorna el número de avisos generados.
     */
    public static function procesarVencimientosPendientes(): int
    {
        $ahora = Carbon::now(Publicacion::TIMEZONE);
        $generados = 0;

        // Publicaciones con fecha de vencimiento cumplida, que no estén bajo resguardo confirmado ni canceladas/devueltas
        $publicacionesVencidas = Publicacion::whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<=', $ahora)
            ->whereNotIn('estado', [Publicacion::ESTADO_CANCELADO, Publicacion::ESTADO_DEVUELTO, 'Cancelada'])
            ->where(function ($q) {
                $q->whereNull('situacion_resguardo')
                    ->orWhere('situacion_resguardo', '!=', Publicacion::RESGUARDO_CONFIRMADO);
            })
            ->get();

        foreach ($publicacionesVencidas as $publicacion) {
            $aviso = self::generarAvisoPublicacionVencida($publicacion);
            if ($aviso !== null) {
                $generados++;
            }
        }

        return $generados;
    }

    /**
     * Registra un aviso institucional de forma atómica y segura, aplicando deduplicación
     * tanto a nivel de aplicación como por restricción de unicidad en base de datos.
     * Desacopla cualquier fallo para no interrumpir el flujo principal del sistema.
     *
     * @param array<string, mixed> $datos
     */
    protected static function registrarAvisoSeguro(array $datos, string $claveDuplicado): ?Aviso
    {
        try {
            // 1. Verificación previa a nivel de aplicación (previene inserciones redundantes)
            if (! empty($claveDuplicado) && Aviso::where('clave_duplicado', $claveDuplicado)->exists()) {
                Log::info("Aviso institucional duplicado omitido por verificación de clave [{$claveDuplicado}].", [
                    'tipo_evento' => $datos['tipo_evento'] ?? null,
                    'publicacion_id' => $datos['publicacion_id'] ?? null,
                    'devolucion_id' => $datos['devolucion_id'] ?? null,
                    'user_id' => $datos['user_id'] ?? null,
                    'folio_reporte' => $datos['folio_reporte'] ?? null,
                ]);

                return null;
            }

            $datos['clave_duplicado'] = $claveDuplicado;
            $datos['leido'] = false;

            return Aviso::create($datos);
        } catch (UniqueConstraintViolationException $e) {
            // 2. Colisión concurrente resuelta por restricción UNIQUE de base de datos
            Log::info("Aviso institucional duplicado omitido por restricción UNIQUE en base de datos [{$claveDuplicado}].", [
                'tipo_evento' => $datos['tipo_evento'] ?? null,
                'publicacion_id' => $datos['publicacion_id'] ?? null,
                'devolucion_id' => $datos['devolucion_id'] ?? null,
                'user_id' => $datos['user_id'] ?? null,
                'folio_reporte' => $datos['folio_reporte'] ?? null,
            ]);

            return null;
        } catch (QueryException $e) {
            // 3. Verificación de códigos SQLSTATE 23000 (integridad / duplicado) en diversos motores
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                Log::info("Aviso institucional duplicado omitido por error de integridad [{$claveDuplicado}].", [
                    'tipo_evento' => $datos['tipo_evento'] ?? null,
                    'publicacion_id' => $datos['publicacion_id'] ?? null,
                    'devolucion_id' => $datos['devolucion_id'] ?? null,
                    'user_id' => $datos['user_id'] ?? null,
                ]);

                return null;
            }

            Log::error("Error de base de datos al generar aviso institucional [{$claveDuplicado}]: " . $e->getMessage(), [
                'tipo_evento' => $datos['tipo_evento'] ?? null,
                'publicacion_id' => $datos['publicacion_id'] ?? null,
                'devolucion_id' => $datos['devolucion_id'] ?? null,
                'user_id' => $datos['user_id'] ?? null,
                'exception_class' => get_class($e),
            ]);

            return null;
        } catch (\Throwable $e) {
            // 4. Captura general desacoplada: garantiza que fallos imprevistos nunca rompan el proceso principal
            Log::error("Error inesperado al generar aviso institucional [{$claveDuplicado}]: " . $e->getMessage(), [
                'tipo_evento' => $datos['tipo_evento'] ?? null,
                'publicacion_id' => $datos['publicacion_id'] ?? null,
                'devolucion_id' => $datos['devolucion_id'] ?? null,
                'user_id' => $datos['user_id'] ?? null,
                'exception_class' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Obtiene el nombre identificable del objeto para el mensaje del aviso.
     */
    protected static function obtenerNombreObjeto(Publicacion $publicacion): string
    {
        $nombre = trim((string) ($publicacion->nombre ?? $publicacion->titulo ?? ''));

        return $nombre !== '' ? $nombre : 'Objeto reportado';
    }
}
