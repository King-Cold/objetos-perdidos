<x-app-layout>
    <div class="min-h-screen bg-[#F5F6F8] py-6 sm:py-8 text-[#1F2937]"
         x-data="{
             modalAprobarOpen: false,
             modalCorreccionOpen: {{ $errors->has('motivo') ? 'true' : 'false' }},
             confirmacionRevision: true,
             motivoTexto: '{{ old('motivo', '') }}',
             motivoError: '',
             validarMotivo() {
                 if (!this.motivoTexto || this.motivoTexto.trim().length === 0) {
                     this.motivoError = 'El motivo de corrección es obligatorio y no puede estar vacío ni contener solo espacios en blanco.';
                     return false;
                 }
                 this.motivoError = '';
                 return true;
             }
         }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <!-- 1. ENCABEZADO Y NAVEGACIÓN COMPACTA -->
            <div class="space-y-1.5">
                <div>
                    <a href="{{ route('admin.devoluciones.index') }}"
                       class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#64748B] hover:text-[#691C32] transition-colors focus:outline-none focus:underline">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Volver a la cola de devoluciones</span>
                    </a>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-0.5">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 border border-emerald-300/80 text-[10px] font-black uppercase tracking-wider text-emerald-900">
                                Panel Administrativo &bull; Biblioteca
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full bg-tecnm-wine/10 text-[10px] font-bold text-tecnm-wine">
                                Verificación
                            </span>
                        </div>
                        <div class="flex flex-wrap items-baseline gap-2 sm:gap-3">
                            <h1 class="text-xl sm:text-2xl font-black text-[#1F2937] tracking-tight leading-tight">
                                Revisar devolución
                            </h1>
                            <span class="text-slate-300 hidden sm:inline">&bull;</span>
                            <span class="text-xs sm:text-sm font-bold text-slate-800">
                                {{ $publicacion ? ($publicacion->nombre ?? $publicacion->titulo) : 'Objeto' }}
                            </span>
                            <span class="font-mono text-xs font-extrabold text-[#691C32] bg-rose-50 px-2.5 py-0.5 rounded-lg border border-rose-200">
                                Folio: {{ $publicacion ? $publicacion->folio : 'S/F' }}
                            </span>
                        </div>
                    </div>

                    <!-- Acciones principales (HU-13 / Acceso al historial e incidencias desde gestión) -->
                    <div class="flex items-center gap-2.5 shrink-0">
                        @if ($publicacion)
                            <a href="{{ route('admin.reportes.historial', $publicacion->id) }}"
                               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 hover:border-slate-400 text-slate-800 hover:text-tecnm-wine text-xs font-bold shadow hover:shadow-md active:translate-y-px transition-all cursor-pointer">
                                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Ver historial</span>
                            </a>

                            @php
                                $incidenciaExistente = $publicacion->incidencias()->first();
                            @endphp
                            @if ($incidenciaExistente)
                                <a href="{{ route('admin.incidencias.show', $incidenciaExistente->id) }}"
                                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover active:bg-tecnm-wine-dark text-white text-xs font-bold shadow hover:shadow-md active:translate-y-px transition-all cursor-pointer">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Ver incidencia #{{ $incidenciaExistente->id }}</span>
                                </a>
                            @elseif ($publicacion)
                                <a href="{{ route('admin.incidencias.create', ['publicacionId' => $publicacion->id, 'devolucion_id' => $devolucion->id]) }}"
                                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover active:bg-tecnm-wine-dark text-white text-xs font-bold shadow hover:shadow-md active:translate-y-px transition-all cursor-pointer"
                                   title="Canalizar contradicciones o incidencias de entrega">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <span>Abrir incidencia</span>
                                </a>
                            @endif
                        @endif
                    </div>
                </div>

                <p class="text-xs text-[#64748B]">
                    Comprueba y contrasta los datos del hallazgo original con la evidencia de entrega física registrada.
                </p>
            </div>

            <!-- AVISOS DE ESTADO Y ERRORES -->
            @if (session('status'))
                <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs sm:text-sm font-semibold text-emerald-800 flex items-center gap-2.5 shadow-2xs" role="alert">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-xs sm:text-sm font-semibold text-rose-900 flex items-center gap-2.5 shadow-2xs" role="alert">
                    <svg class="w-4 h-4 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1 shadow-2xs" role="alert">
                    <span class="font-bold block">Por favor corrige los siguientes errores:</span>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- FILA 1: DOS COLUMNAS EQUILIBRADAS (INFORMACIÓN DEL HALLAZGO VS INFORMACIÓN DE LA DEVOLUCIÓN) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">

                <!-- TARJETA A (IZQUIERDA): INFORMACIÓN DEL HALLAZGO -->
                <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                Información del hallazgo
                            </h2>
                        </div>
                        <span class="font-mono text-xs font-bold text-[#691C32] bg-rose-50 px-2 py-0.5 rounded-lg border border-rose-200">
                            {{ $publicacion ? $publicacion->folio : 'S/F' }}
                        </span>
                    </div>

                    <!-- Nombre del objeto -->
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">
                            Nombre del objeto
                        </span>
                        <h3 class="text-sm sm:text-base font-black text-slate-900 leading-snug">
                            {{ $publicacion ? ($publicacion->nombre ?? $publicacion->titulo) : 'Objeto no disponible' }}
                        </h3>
                    </div>

                    <!-- Descripción original compacta -->
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">
                            Descripción original
                        </span>
                        <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-200 text-xs text-slate-700 leading-relaxed font-medium whitespace-pre-line">
                            {{ $publicacion ? $publicacion->descripcion : 'Sin descripción' }}
                        </div>
                    </div>

                    <!-- Cuadrícula 2 columnas para metadatos del hallazgo -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                        <div class="p-2.5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-0.5">
                            <span class="text-slate-400 font-semibold block text-[10px] uppercase tracking-wide">Fecha y hora del hallazgo:</span>
                            <span class="font-bold text-slate-800 block text-xs">
                                {{ $publicacion ? $publicacion->fecha_hora_hallazgo_texto : 'No especificada' }}
                            </span>
                        </div>

                        <div class="p-2.5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-0.5">
                            <span class="text-slate-400 font-semibold block text-[10px] uppercase tracking-wide">Lugar / Área:</span>
                            <span class="font-bold text-slate-800 block text-xs truncate">
                                {{ $publicacion ? ($publicacion->area_zona ?? $publicacion->lugar_hallazgo ?? 'Campus Poniente') : 'Campus Poniente' }}
                            </span>
                        </div>

                        <div class="p-2.5 bg-slate-50/80 rounded-xl border border-slate-200 sm:col-span-2 space-y-0.5">
                            <span class="text-slate-400 font-semibold block text-[10px] uppercase tracking-wide">Autor original:</span>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                <span class="font-bold text-slate-900 text-xs">
                                    {{ $publicacion && $publicacion->user ? $publicacion->user->name : 'No registrado' }}
                                </span>
                                @if ($publicacion && $publicacion->user)
                                    <span class="text-slate-500 font-mono text-[11px] break-all">
                                        {{ $publicacion->user->email }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Fotografías del hallazgo -->
                    <div class="space-y-2 pt-2 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
                                Fotografías del hallazgo
                            </span>
                            <span class="text-[11px] font-semibold text-slate-400">
                                {{ $publicacion && $publicacion->fotografias ? $publicacion->fotografias->count() : 0 }} foto(s)
                            </span>
                        </div>

                        @if ($publicacion && $publicacion->fotografias && $publicacion->fotografias->count() > 0)
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                                @foreach ($publicacion->fotografias as $foto)
                                    <div class="relative group rounded-xl overflow-hidden border border-slate-200 bg-slate-100 aspect-square">
                                        <img src="{{ route('fotografias.show', $foto->id) }}"
                                             alt="Fotografía del hallazgo"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                                             loading="lazy" />
                                        <a href="{{ route('fotografias.show', $foto->id) }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-[10px] font-bold transition-opacity">
                                            Ampliar
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-3 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-center text-xs text-slate-400">
                                No se adjuntaron fotografías al registrar el hallazgo.
                            </div>
                        @endif
                    </div>
                </div>

                <!-- TARJETA B (DERECHA): INFORMACIÓN DE LA DEVOLUCIÓN -->
                <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                Información de la devolución
                            </h2>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                            @if ($devolucion->estado === 'Pendiente de verificación') bg-amber-50 text-amber-900 border border-amber-300
                            @elseif ($devolucion->estado === 'Verificada' || $devolucion->estado === 'Aprobada') bg-emerald-50 text-emerald-900 border border-emerald-300
                            @elseif ($devolucion->estado === 'Requiere corrección') bg-orange-50 text-orange-900 border border-orange-300
                            @else bg-slate-100 text-slate-800 border border-slate-300 @endif">
                            <span class="w-1.5 h-1.5 rounded-full @if ($devolucion->estado === 'Pendiente de verificación') bg-amber-500 animate-pulse @elseif ($devolucion->estado === 'Requiere corrección') bg-orange-500 @else bg-emerald-500 @endif"></span>
                            <span>{{ $devolucion->estado }}</span>
                        </span>
                    </div>

                    <!-- Datos del Receptor en cuadrícula de 2 columnas -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                        <div class="p-2.5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-0.5">
                            <span class="text-slate-400 font-semibold block text-[10px] uppercase tracking-wide">Nombre completo del receptor:</span>
                            <span class="font-extrabold text-slate-900 text-xs sm:text-sm block">
                                {{ $devolucion->receptor_nombre }}
                            </span>
                        </div>

                        <div class="p-2.5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-0.5">
                            <span class="text-slate-400 font-semibold block text-[10px] uppercase tracking-wide">Identificador institucional:</span>
                            <span class="font-mono font-bold text-slate-900 text-xs sm:text-sm block">
                                {{ $devolucion->receptor_identificador }}
                            </span>
                        </div>
                    </div>

                    <!-- CRONOLOGÍA DE ENTREGA Y REGISTRO: Bloque compacto simplificado -->
                    <div class="p-3 bg-emerald-50/60 rounded-2xl border border-emerald-200/80 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-950 flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                <span>Cronología de entrega y registro</span>
                            </span>
                            @if ($devolucion->fecha_entrega_texto === $devolucion->fecha_captura_texto)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-300">
                                    <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Registro simultáneo</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                    <span>Momentos diferenciados</span>
                                </span>
                            @endif
                        </div>

                        @if ($devolucion->fecha_entrega_texto === $devolucion->fecha_captura_texto)
                            <!-- Bloque único compacto cuando coinciden -->
                            <div class="p-2.5 bg-white rounded-xl border border-emerald-200/90 shadow-2xs space-y-1">
                                <div class="text-xs sm:text-sm font-black text-slate-900">
                                    {{ $devolucion->fecha_entrega_texto }}
                                </div>
                                <span class="text-[11px] text-slate-500 block leading-tight">
                                    Momento único: la entrega física del objeto y su captura en el sistema se realizaron simultáneamente.
                                </span>
                                {{-- Compatibilidad semántica para pruebas y lectores de pantalla --}}
                                <div class="sr-only">
                                    <span>Fecha de entrega física: {{ $devolucion->fecha_entrega_texto }}</span>
                                    <span>Fecha de registro: {{ $devolucion->fecha_captura_texto }}</span>
                                </div>
                            </div>
                        @else
                            <!-- Bloque desglosado si difieren -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                <div class="p-2.5 bg-white rounded-xl border border-emerald-200/90 shadow-2xs space-y-0.5">
                                    <span class="text-emerald-900 font-bold block text-[10px] uppercase tracking-wide">
                                        Fecha de entrega física
                                    </span>
                                    <span class="font-extrabold text-slate-900 text-xs block">
                                        {{ $devolucion->fecha_entrega_texto }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 block leading-tight">
                                        Momento real de entrega física.
                                    </span>
                                </div>

                                <div class="p-2.5 bg-white rounded-xl border border-emerald-200/90 shadow-2xs space-y-0.5">
                                    <span class="text-slate-600 font-bold block text-[10px] uppercase tracking-wide">
                                        Fecha de registro
                                    </span>
                                    <span class="font-extrabold text-slate-900 text-xs block">
                                        {{ $devolucion->fecha_captura_texto }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 block leading-tight">
                                        Captura en sistema.
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Explicación de comprobación de propiedad -->
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">
                            Explicación de comprobación de propiedad
                        </span>
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-800 leading-relaxed font-medium whitespace-pre-line">
                            {{ $devolucion->explicacion_propiedad }}
                        </div>
                    </div>

                    <!-- Motivo de corrección si se encuentra activo -->
                    @if ($devolucion->isRequiereCorreccion() && !empty($devolucion->motivo_correccion))
                        <div class="p-3 bg-orange-50/90 rounded-xl border border-orange-200 space-y-1 text-xs">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-orange-950 block">
                                Motivo vigente de corrección solicitado:
                            </span>
                            <p class="text-xs text-orange-900 leading-relaxed font-medium">
                                {{ $devolucion->motivo_correccion }}
                            </p>
                            @if ($devolucion->admin)
                                <span class="text-[10px] text-orange-700 block">
                                    Solicitado por: <strong>{{ $devolucion->admin->name }}</strong>
                                </span>
                            @endif
                        </div>
                    @endif

                    <!-- Autor responsable de la entrega y bibliotecario que documentó -->
                    <div class="pt-2 border-t border-slate-100 space-y-2 text-xs">
                        <div class="flex items-center justify-between text-slate-500">
                            <span>Autor responsable de la entrega:</span>
                            <strong class="text-slate-800 font-bold">
                                {{ $devolucion->user ? $devolucion->user->name : 'Autor original' }}
                            </strong>
                        </div>

                        @if ($devolucion->isRegistroExtemporaneo())
                            <div class="p-3 bg-amber-50/80 rounded-xl border border-amber-200 space-y-1.5">
                                <div class="flex items-center justify-between text-amber-900">
                                    <span class="font-bold text-[10px] uppercase tracking-wide">Bibliotecario responsable del registro tardío:</span>
                                    <strong class="text-amber-950 font-bold">
                                        {{ $devolucion->registradoPor ? $devolucion->registradoPor->name : 'Personal de biblioteca' }}
                                    </strong>
                                </div>

                                @if (! empty($devolucion->motivo_registro_tardio))
                                    <div class="pt-1.5 border-t border-amber-200/70 space-y-0.5">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-900 block">
                                            Motivo del registro tardío:
                                        </span>
                                        <p class="text-xs text-amber-950 leading-relaxed font-medium whitespace-pre-line">
                                            {{ $devolucion->motivo_registro_tardio }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- FILA 2: DOS COLUMNAS INDEPENDIENTES (FOTOGRAFÍA DE EVIDENCIA VS HISTORIAL DE DECISIONES) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">

                <!-- TARJETA C (IZQUIERDA): FOTOGRAFÍA DE EVIDENCIA -->
                <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-2xs space-y-3">
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
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                            Acceso Privado Protegido
                        </span>
                    </div>

                    @if ($devolucion->hasEvidenciaDisponible())
                        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 p-3 bg-slate-50/80 rounded-2xl border border-slate-200">
                            <div class="relative group rounded-xl overflow-hidden border border-slate-200 bg-white shrink-0 shadow-2xs">
                                <img src="{{ route('devoluciones.evidencia', $devolucion->id) }}"
                                     alt="Fotografía de evidencia de entrega física"
                                     class="w-32 h-32 sm:w-36 sm:h-36 object-cover"
                                     loading="lazy" />
                            </div>

                            <div class="flex-1 space-y-2 text-center sm:text-left min-w-0">
                                <div>
                                    <span class="text-xs font-bold text-slate-900 block truncate max-w-full" title="{{ $devolucion->fotografia_nombre_original ?? 'evidencia_entrega.jpg' }}">
                                        {{ $devolucion->fotografia_nombre_original ?? 'evidencia_entrega.jpg' }}
                                    </span>
                                    <span class="text-[11px] text-slate-400 block mt-0.5">
                                        {{ $devolucion->fotografia_tamano_bytes ? round($devolucion->fotografia_tamano_bytes / 1024, 1) . ' KB' : 'Formato JPG/PNG' }}
                                    </span>
                                </div>

                                <p class="text-[11px] text-slate-500 leading-relaxed font-medium">
                                    Fotografía capturada al momento del intercambio físico para constancia institucional.
                                </p>

                                <div class="pt-1">
                                    <a href="{{ route('devoluciones.evidencia', $devolucion->id) }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold transition-all shadow-2xs">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                        <span>Ver fotografía en tamaño completo</span>
                                    </a>
                                </div>
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

                <!-- TARJETA D (DERECHA): HISTORIAL DE DECISIONES DE MODERACIÓN -->
                <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/90 shadow-2xs space-y-3">
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
                            {{ $publicacion && $publicacion->decisiones ? $publicacion->decisiones->count() : 0 }} evento(s)
                        </span>
                    </div>

                    @if ($publicacion && $publicacion->decisiones && $publicacion->decisiones->isNotEmpty())
                        <div class="divide-y divide-slate-100">
                            @foreach ($publicacion->decisiones as $decision)
                                <div class="py-2.5 first:pt-0 last:pb-0 text-xs space-y-1">
                                    <!-- Línea 1: Decisión y fecha -->
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5">
                                        <span class="font-extrabold text-slate-900">
                                            @if ($decision->tipo_decision === 'devolucion_correccion')
                                                Solicitud de corrección
                                            @elseif ($decision->tipo_decision === 'devolucion_aprobada')
                                                Aprobación de devolución
                                            @else
                                                {{ ucfirst(str_replace('_', ' ', $decision->tipo_decision)) }}
                                            @endif
                                        </span>
                                        <span class="text-[11px] font-mono text-slate-500">
                                            {{ $decision->fecha_decision_texto }}
                                        </span>
                                    </div>

                                    <!-- Línea 2: Responsable -->
                                    @if ($decision->user)
                                        <div class="text-[11px] text-slate-500">
                                            Responsable: <strong class="text-slate-700">{{ $decision->user->name }}</strong>
                                        </div>
                                    @endif

                                    <!-- Línea 3: Motivo completo sin recortes -->
                                    @if (!empty($decision->motivo))
                                        <div class="text-[11px] text-slate-700 font-medium pt-0.5 leading-relaxed break-words whitespace-pre-line bg-slate-50/70 p-2 rounded-lg border border-slate-100">
                                            Motivo registrado: <span class="italic text-slate-800">«{{ $decision->motivo }}»</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-4 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-center text-xs text-slate-400">
                            No se han registrado decisiones previas para este reporte.
                        </div>
                    @endif
                </div>
            </div>

            <!-- 3. BARRA DE ACCIONES ADMINISTRATIVAS COMPACTA -->
            <div class="bg-white rounded-3xl p-4 sm:p-5 border border-slate-200/90 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <a href="{{ route('admin.devoluciones.index') }}"
                       class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Volver a la cola</span>
                    </a>
                </div>

                @if ($devolucion->isPendiente())
                    <!-- Botones de decisión activos -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                        <!-- Botón: Solicitar corrección -->
                        <button type="button"
                                @click="modalCorreccionOpen = true"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-colors shadow-2xs cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span>Solicitar corrección</span>
                        </button>

                        <!-- Botón: Aprobar devolución -->
                        <button type="button"
                                @click="modalAprobarOpen = true"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold transition-colors shadow-2xs cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Aprobar devolución</span>
                        </button>
                    </div>
                @else
                    <!-- Estado de resolución informativa o acción de corrección -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                        @if ($devolucion->isAprobada())
                            <span class="text-xs font-bold text-emerald-700">✓ Devolución aprobada institucionalmente. El objeto se encuentra en estado «Devuelto».</span>
                        @elseif ($devolucion->isRequiereCorreccion())
                            @if ($devolucion->isRegistroExtemporaneo())
                                <a href="{{ route('admin.devoluciones-extemporaneas.edit', $devolucion->id) }}"
                                   class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-colors shadow-2xs">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    <span>Corregir y reenviar devolución</span>
                                </a>
                            @else
                                <span class="text-xs font-bold text-orange-700">⚠ Se solicitó corrección al autor. Esperando atención y nuevo envío a revisión.</span>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            <!-- 4. MODAL: CONFIRMAR APROBACIÓN DE DEVOLUCIÓN -->
            <div x-show="modalAprobarOpen"
                 x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="modal-aprobar-titulo">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
                     @click="modalAprobarOpen = false"></div>

                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200"
                         @click.stop>
                        <form method="POST" action="{{ route('admin.devoluciones.aprobar', $devolucion->id) }}">
                            @csrf

                            <div class="p-6 sm:p-7 space-y-4">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto sm:mx-0">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>

                                <div class="space-y-1">
                                    <h3 id="modal-aprobar-titulo" class="text-lg font-black text-slate-900 tracking-tight">
                                        Confirmar aprobación de devolución
                                    </h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">
                                        La devolución quedará aprobada y el objeto pasará a estado <strong>«Devuelto»</strong>, manteniéndose fuera del muro institucional y de las búsquedas públicas de forma definitiva.
                                    </p>
                                </div>

                                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500 font-semibold">Folio:</span>
                                        <span class="font-mono font-bold text-slate-900">{{ $publicacion ? $publicacion->folio : 'S/F' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500 font-semibold">Receptor:</span>
                                        <span class="font-bold text-slate-900">{{ $devolucion->receptor_nombre }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500 font-semibold">Fecha de entrega física:</span>
                                        <span class="font-bold text-slate-900">{{ $devolucion->fecha_entrega_texto }}</span>
                                    </div>
                                </div>

                                <label class="flex items-start gap-3 p-3 bg-emerald-50/70 border border-emerald-200 rounded-xl cursor-pointer">
                                    <input type="checkbox"
                                           name="confirmacion_revision"
                                           value="1"
                                           x-model="confirmacionRevision"
                                           class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                           required />
                                    <span class="text-xs text-emerald-950 font-medium leading-relaxed">
                                        Confirmo que he revisado visualmente la fotografía de evidencia, contrastado los datos del receptor y verificado la coherencia de la entrega.
                                    </span>
                                </label>
                            </div>

                            <div class="p-4 sm:px-7 sm:py-4 bg-slate-50 border-t border-slate-100 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5">
                                <button type="button"
                                        @click="modalAprobarOpen = false"
                                        class="inline-flex justify-center px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-100 transition-colors">
                                    Volver
                                </button>
                                <button type="submit"
                                        :disabled="!confirmacionRevision"
                                        class="inline-flex justify-center px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-xs font-bold text-white transition-colors shadow-xs">
                                    Confirmar aprobación
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- 5. MODAL: SOLICITAR CORRECCIÓN DE DEVOLUCIÓN -->
            <div x-show="modalCorreccionOpen"
                 x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="modal-correccion-titulo">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
                     @click="modalCorreccionOpen = false"></div>

                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200"
                         @click.stop>
                        <form method="POST" action="{{ route('admin.devoluciones.solicitar-correccion', $devolucion->id) }}">
                            @csrf

                            <div class="p-6 sm:p-7 space-y-4">
                                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto sm:mx-0">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </div>

                                <div class="space-y-1">
                                    <h3 id="modal-correccion-titulo" class="text-lg font-black text-slate-900 tracking-tight">
                                        Solicitar corrección de devolución
                                    </h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">
                                        Explica de forma clara y respetuosa qué información, datos del receptor o evidencia fotográfica debe ser corregida o reemplazada por el autor.
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <label for="motivo" class="text-xs font-bold text-slate-700 block">
                                        Motivo de la corrección <span class="text-rose-600">*</span>
                                    </label>
                                    <textarea id="motivo"
                                              name="motivo"
                                              rows="4"
                                              x-model="motivoTexto"
                                              @input="motivoError = ''"
                                              placeholder="Escribe el motivo detallado de la corrección requerida..."
                                              class="w-full text-xs rounded-2xl border-slate-200 focus:border-amber-500 focus:ring-amber-500 p-3.5 placeholder:text-slate-400"
                                              required></textarea>
                                    <p x-show="motivoError" x-text="motivoError" class="text-xs text-rose-600 font-semibold"></p>
                                    @error('motivo')
                                        <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-[11px] text-slate-500 leading-relaxed space-y-1">
                                    <p>Al confirmar, la devolución pasará a <strong>«Requiere corrección»</strong> y saldrá de la cola de pendientes. Los datos capturados y la fotografía actual se conservarán como historial.</p>
                                    <p class="text-amber-800 font-medium">Nota: Si existen contradicciones sobre el receptor o la propiedad, o si la entrega física ocurrió después del vencimiento, canaliza el caso abriendo una <strong>incidencia administrativa</strong>.</p>
                                </div>
                            </div>

                            <div class="p-4 sm:px-7 sm:py-4 bg-slate-50 border-t border-slate-100 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5">
                                <button type="button"
                                        @click="modalCorreccionOpen = false"
                                        class="inline-flex justify-center px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-100 transition-colors">
                                    Volver
                                </button>
                                <button type="submit"
                                        @click="if(!validarMotivo()) $event.preventDefault()"
                                        class="inline-flex justify-center px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-xs font-bold text-white transition-colors shadow-xs">
                                    Confirmar solicitud de corrección
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
