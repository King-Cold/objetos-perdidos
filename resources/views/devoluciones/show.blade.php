<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                        Seguimiento Privado
                    </span>
                    <span class="text-xs text-slate-400">&bull;</span>
                    <span class="text-xs font-semibold text-slate-500">Folio: {{ $devolucion->publicacion->folio }}</span>
                </div>
                <h2 class="font-black text-xl sm:text-2xl text-slate-800 leading-tight">
                    Seguimiento de Devolución
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Consulta privada de los datos registrados y evidencia de entrega física
                </p>
            </div>
            <div>
                @if (Auth::user()->isAdmin())
                    <a href="{{ route('admin.moderacion.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                        <span aria-hidden="true">&larr;</span>
                        <span>Volver a Moderación</span>
                    </a>
                @else
                    <a href="{{ route('reportes.mis-reportes') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                        <span aria-hidden="true">&larr;</span>
                        <span>Volver a Mis Reportes</span>
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8 bg-slate-50/50 min-h-[calc(100vh-140px)]">
        {{-- Contenedor adaptable centrado de 1100–1200 px (max-w-6xl = 1152 px) --}}
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5 sm:space-y-6">

            {{-- Avisos de sesión / flash messages --}}
            @if (session('status'))
                <div class="p-3.5 sm:p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs sm:text-sm font-semibold text-emerald-800 flex items-center gap-3 shadow-2xs" role="alert">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="p-3.5 sm:p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs sm:text-sm font-semibold text-rose-900 flex items-center gap-3 shadow-2xs" role="alert">
                    <svg class="w-5 h-5 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if (session('warning'))
                <div class="p-3.5 sm:p-4 rounded-2xl bg-amber-50 border border-amber-200 text-xs sm:text-sm font-semibold text-amber-900 flex items-center gap-3 shadow-2xs" role="alert">
                    <svg class="w-5 h-5 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('warning') }}</span>
                </div>
            @endif

            <!-- 1. FRANJA SUPERIOR COMPACTA: ESTADO, CONDICIÓN FUERA DEL MURO Y FOLIO -->
            <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-5 border border-slate-200/90 shadow-2xs">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider
                            @if ($devolucion->estado === 'Pendiente de verificación') bg-amber-50 text-amber-900 border border-amber-300 ring-2 ring-amber-100
                            @elseif ($devolucion->estado === 'Verificada' || $devolucion->estado === 'Aprobada') bg-emerald-50 text-emerald-900 border border-emerald-300
                            @elseif ($devolucion->estado === 'Requiere corrección') bg-orange-50 text-orange-900 border border-orange-300 ring-2 ring-orange-100
                            @else bg-slate-100 text-slate-700 @endif"
                        >
                            <span class="w-2 h-2 rounded-full @if ($devolucion->estado === 'Pendiente de verificación') bg-amber-500 animate-pulse @elseif ($devolucion->estado === 'Requiere corrección') bg-orange-500 @else bg-emerald-500 @endif"></span>
                            <span>{{ $devolucion->estado }}</span>
                        </span>

                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                            Fuera del muro público
                        </span>
                    </div>

                    <div class="flex items-center gap-2 self-start sm:self-auto">
                        @if ($devolucion->publicacion)
                            <a href="{{ route('reportes.historial', $devolucion->publicacion->id) }}" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 hover:text-[#691C32] text-xs font-bold transition-all shadow-2xs">
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Ver historial</span>
                            </a>
                        @endif
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            Folio:
                        </span>
                        <span class="text-xs sm:text-sm font-black text-[#691C32] tracking-wider bg-rose-50 px-2.5 py-0.5 rounded-lg border border-rose-200 font-mono">
                            {{ $devolucion->publicacion->folio }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- PANEL DE ATENCIÓN REQUERIDA (SOLICITUD DE CORRECCIÓN VIGENTE) -->
            @if ($devolucion->isRequiereCorreccion() && !empty($devolucion->motivo_correccion))
                <div class="p-4 sm:p-5 bg-orange-50/90 rounded-2xl sm:rounded-3xl border-2 border-orange-300/90 space-y-3 shadow-2xs">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </div>
                            <div>
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-orange-900 block">
                                    Atención requerida
                                </span>
                                <h3 class="text-sm sm:text-base font-black text-orange-950">
                                    Solicitud de corrección administrativa
                                </h3>
                            </div>
                        </div>

                        @if (Auth::id() === $devolucion->user_id)
                            <a href="{{ route('devoluciones.edit', $devolucion->id) }}"
                               class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-700 hover:to-amber-700 text-white text-xs font-black shadow-xs transition-all active:scale-[0.98]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                <span>Corregir devolución</span>
                            </a>
                        @endif
                    </div>

                    <div class="space-y-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-orange-900 block">
                            Motivo vigente de la corrección:
                        </span>
                        <div class="p-3 bg-white rounded-xl border border-orange-200 text-xs text-orange-950 font-medium whitespace-pre-line leading-relaxed shadow-2xs">
                            {{ $devolucion->motivo_correccion }}
                        </div>
                        @if ($devolucion->admin)
                            <span class="text-[10px] text-orange-800 block pt-0.5">
                                Observación registrada por: <strong>{{ $devolucion->admin->name }}</strong>
                            </span>
                        @endif
                    </div>

                    <p class="text-[11px] text-orange-900 leading-relaxed font-medium">
                        Por favor revisa la observación indicada. Puedes modificar los datos del receptor, ajustar la fecha y hora de entrega o reemplazar la fotografía de evidencia y reenviar nuevamente a revisión.
                    </p>
                </div>
            @endif

            <!-- 2. AVISO ADMINISTRATIVO A TODO EL ANCHO (CA-08: NO GARANTÍA AUTOMÁTICA) -->
            <div class="p-3.5 sm:p-4 rounded-2xl bg-amber-50/70 border border-amber-200/90 text-xs text-amber-900 space-y-1">
                <div class="flex items-center gap-2 font-bold text-amber-950">
                    <svg class="w-4 h-4 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Resultado provisional en revisión administrativa</span>
                </div>
                <p class="leading-relaxed">
                    La fotografía y el contacto previo no representan una garantía automática de propiedad. La devolución continuará en revisión por el personal de biblioteca sin marcar el objeto como devuelto hasta contar con resolución administrativa.
                </p>
            </div>

            <!-- 3. DISTRIBUCIÓN PRINCIPAL EN DOS COLUMNAS (~60% / ~40% EN ESCRITORIO) -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-5 sm:gap-6 items-start">

                <!-- COLUMNA IZQUIERDA (~60%): INFORMACIÓN DEL OBJETO Y DE LA ENTREGA -->
                <div class="lg:col-span-3 bg-white rounded-2xl sm:rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-2xs space-y-4">
                    
                    <!-- Objeto entregado: nombre y descripción completa -->
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">
                            Objeto entregado
                        </span>
                        <h3 class="text-base sm:text-lg font-black text-slate-900 leading-snug">
                            {{ $devolucion->publicacion->nombre ?? $devolucion->publicacion->titulo }}
                        </h3>
                        <div class="mt-2 p-3 bg-slate-50/80 rounded-xl border border-slate-200 text-xs text-slate-700 leading-relaxed font-medium whitespace-pre-line">
                            {{ $devolucion->publicacion->descripcion }}
                        </div>
                    </div>

                    <!-- Datos del receptor: nombre completo e identificador institucional en una misma fila -->
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1.5">
                            Datos del receptor
                        </span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                            <div class="p-2.5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-0.5">
                                <span class="text-slate-400 font-semibold block text-[10px] uppercase tracking-wide">
                                    Nombre completo:
                                </span>
                                <span class="font-extrabold text-slate-900 text-xs sm:text-sm block">
                                    {{ $devolucion->receptor_nombre }}
                                </span>
                            </div>

                            <div class="p-2.5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-0.5">
                                <span class="text-slate-400 font-semibold block text-[10px] uppercase tracking-wide">
                                    Identificador institucional:
                                </span>
                                <span class="font-mono font-bold text-slate-900 text-xs sm:text-sm block">
                                    {{ $devolucion->receptor_identificador }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Entrega y registro: fecha y hora con indicador de simultaneidad o diferido -->
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1.5">
                            Cronología de entrega física y registro
                        </span>

                        @if ($devolucion->fecha_entrega_texto === $devolucion->fecha_captura_texto)
                            <div class="p-3 bg-emerald-50/70 rounded-xl border border-emerald-200/90 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-950">
                                        Fecha de entrega física y registro
                                    </span>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-100/90 px-2 py-0.5 rounded-full border border-emerald-300">
                                        <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Registro simultáneo</span>
                                    </span>
                                </div>
                                <span class="font-extrabold text-slate-900 text-xs sm:text-sm block">
                                    {{ $devolucion->fecha_entrega_texto }}
                                </span>
                                <span class="text-[11px] text-slate-500 block leading-tight">
                                    La entrega física del objeto y su captura en el sistema ocurrieron en el mismo momento.
                                </span>
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                                <div class="p-2.5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-0.5">
                                    <span class="text-slate-400 font-semibold block text-[10px] uppercase tracking-wide">
                                        Fecha y hora real de entrega:
                                    </span>
                                    <span class="font-extrabold text-slate-900 text-xs sm:text-sm block">
                                        {{ $devolucion->fecha_entrega_texto }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 block leading-tight">
                                        Momento de entrega física.
                                    </span>
                                </div>

                                <div class="p-2.5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-0.5">
                                    <span class="text-slate-400 font-semibold block text-[10px] uppercase tracking-wide">
                                        Momento de captura en sistema:
                                    </span>
                                    <span class="font-extrabold text-slate-900 text-xs sm:text-sm block">
                                        {{ $devolucion->fecha_captura_texto }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 block leading-tight">
                                        Registro documental.
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Autor responsable de la entrega: integrado discretamente -->
                    <div class="p-2.5 bg-slate-50/80 rounded-xl border border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 text-xs">
                        <span class="text-slate-400 font-semibold text-[10px] uppercase tracking-wide">
                            Autor responsable que entregó:
                        </span>
                        <span class="font-bold text-slate-900 text-xs sm:text-sm">
                            {{ $devolucion->user ? $devolucion->user->name : 'Autor original' }}
                        </span>
                    </div>

                    <!-- Comprobación de propiedad: explicación completa -->
                    <div class="pt-2 border-t border-slate-100 space-y-1.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
                            Explicación de comprobación de propiedad
                        </span>
                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-800 leading-relaxed font-medium whitespace-pre-line">
                            {{ $devolucion->explicacion_propiedad }}
                        </div>
                    </div>
                </div>

                <!-- COLUMNA DERECHA (~40%): FOTOGRAFÍA DE EVIDENCIA -->
                <div class="lg:col-span-2 bg-white rounded-2xl sm:rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                Fotografía de evidencia
                            </h2>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                            Acceso Privado Protegido
                        </span>
                    </div>

                    <!-- Contenedor centrado y sin deformaciones -->
                    @if ($devolucion->hasEvidenciaDisponible())
                        <div class="flex flex-col items-center space-y-3">
                            <div class="relative group rounded-2xl overflow-hidden border border-slate-200 bg-slate-100 w-full max-w-[280px] aspect-square shadow-2xs">
                                <img
                                    src="{{ route('devoluciones.evidencia', $devolucion->id) }}"
                                    alt="Evidencia de devolución"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                                    loading="lazy"
                                />
                                <a href="{{ route('devoluciones.evidencia', $devolucion->id) }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs font-bold transition-opacity">
                                    Ver en tamaño completo
                                </a>
                            </div>

                            <!-- Metadatos de la imagen -->
                            <div class="w-full text-center space-y-1 px-1">
                                <span class="text-xs font-bold text-slate-900 block truncate max-w-full" title="{{ $devolucion->fotografia_nombre_original ?? 'evidencia_entrega.jpg' }}">
                                    {{ $devolucion->fotografia_nombre_original ?? 'evidencia_entrega.jpg' }}
                                </span>
                                <span class="text-[11px] text-slate-400 block font-mono">
                                    {{ $devolucion->fotografia_tamano_bytes ? round($devolucion->fotografia_tamano_bytes / 1024, 1) . ' KB' : 'Formato JPG/PNG' }}
                                </span>
                            </div>

                            <!-- Botón de apertura completa -->
                            <div class="w-full pt-1">
                                <a
                                    href="{{ route('devoluciones.evidencia', $devolucion->id) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold transition-all shadow-2xs"
                                >
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                    <span>Ver fotografía en tamaño completo</span>
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="p-6 rounded-2xl bg-rose-50/70 border border-rose-200/80 text-center space-y-2 text-rose-900">
                            <svg class="w-8 h-8 mx-auto text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="text-xs sm:text-sm font-bold">
                                No fue posible cargar la evidencia
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- 4. HISTORIAL DE DECISIONES DE MODERACIÓN A TODO EL ANCHO -->
            @if ($devolucion->publicacion && $devolucion->publicacion->decisiones->isNotEmpty())
                <div class="bg-white rounded-2xl sm:rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                Historial de decisiones de moderación
                            </h2>
                        </div>
                        <span class="text-[11px] font-semibold text-slate-400">
                            {{ $devolucion->publicacion->decisiones->count() }} evento(s)
                        </span>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @foreach ($devolucion->publicacion->decisiones as $decision)
                            <div class="py-2.5 first:pt-0 last:pb-0 text-xs space-y-1">
                                <!-- Línea 1: Decisión a la izquierda y fecha/hora a la derecha -->
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                    <div class="flex items-center gap-2">
                                        @if ($decision->tipo_decision === 'devolucion_correccion')
                                            <span class="w-2 h-2 rounded-full bg-orange-500 shrink-0"></span>
                                            <span class="font-extrabold text-orange-950">
                                                Solicitud de corrección administrativa
                                            </span>
                                        @elseif ($decision->tipo_decision === 'devolucion_aprobada')
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                                            <span class="font-extrabold text-emerald-950">
                                                Aprobación de devolución
                                            </span>
                                        @else
                                            <span class="w-2 h-2 rounded-full bg-slate-400 shrink-0"></span>
                                            <span class="font-extrabold text-slate-900">
                                                {{ ucfirst(str_replace('_', ' ', $decision->tipo_decision)) }}
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-[11px] font-mono text-slate-500">
                                        {{ $decision->fecha_decision_texto }}
                                    </span>
                                </div>

                                <!-- Línea 2: Responsable -->
                                @if ($decision->user)
                                    <div class="text-[11px] text-slate-500 pl-4 sm:pl-0">
                                        Responsable: <strong class="text-slate-700">{{ $decision->user->name }}</strong>
                                    </div>
                                @endif

                                <!-- Línea 3: Motivo completo sin recortes -->
                                @if (!empty($decision->motivo))
                                    <div class="text-[11px] text-slate-700 font-medium pt-0.5 leading-relaxed break-words whitespace-pre-line bg-slate-50/70 p-2.5 rounded-xl border border-slate-100">
                                        Motivo registrado: <span class="italic text-slate-800">«{{ $decision->motivo }}»</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- 5. PIE DE VISTA COMPACTO: REGRESO, ACCIÓN DE CORRECCIÓN Y LEYENDA DE PRIVACIDAD -->
            <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-5 border border-slate-200/90 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2.5">
                    @if (Auth::user()->isAdmin())
                        <a href="{{ route('admin.moderacion.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                            <span aria-hidden="true">&larr;</span>
                            <span>Volver a Moderación</span>
                        </a>
                    @else
                        <a href="{{ route('reportes.mis-reportes') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                            <span aria-hidden="true">&larr;</span>
                            <span>Volver a Mis Reportes</span>
                        </a>
                    @endif

                    @if (Auth::id() === $devolucion->user_id && $devolucion->isRequiereCorreccion())
                        <a href="{{ route('devoluciones.edit', $devolucion->id) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-700 hover:to-amber-700 text-white text-xs font-black shadow-xs transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span>Corregir devolución</span>
                        </a>
                    @endif
                </div>

                <span class="text-[11px] font-semibold text-slate-400">
                    Consulta privada exclusiva para autor y administración
                </span>
            </div>

        </div>
    </div>
</x-app-layout>
