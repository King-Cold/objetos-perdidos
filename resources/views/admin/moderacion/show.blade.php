<x-app-layout>
    <div class="min-h-screen bg-[#F5F6F8] py-8 text-[#1F2937]"
         x-data="{
             activeImg: '{{ $fotografiaPrincipal ? route('fotografias.show', $fotografiaPrincipal->id) : '' }}',
             activeId: {{ $fotografiaPrincipal ? $fotografiaPrincipal->id : 'null' }},
             modalOpen: false,
             modalCorreccionOpen: {{ $errors->has('motivo') ? 'true' : 'false' }},
             modalAprobarOpen: false,
             modalCancelarOpen: false,
             motivoTexto: '{{ old('motivo', '') }}',
             motivoError: '',
             motivoCancelarTexto: '',
             folioDuplicadoTexto: '',
             motivoCancelarError: '',
             validarMotivo() {
                 if (!this.motivoTexto || this.motivoTexto.trim().length === 0) {
                     this.motivoError = 'El motivo de corrección es obligatorio y no puede estar vacío ni contener solo espacios en blanco.';
                     return false;
                 }
                 this.motivoError = '';
                 return true;
             },
             validarMotivoCancelar() {
                 if (!this.motivoCancelarTexto || this.motivoCancelarTexto.trim().length === 0) {
                     this.motivoCancelarError = 'El motivo de cancelación es obligatorio y no puede estar vacío ni contener solo espacios en blanco.';
                     return false;
                 }
                 this.motivoCancelarError = '';
                 return true;
             }
         }">
        <div class="max-w-[1120px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- 1. ENCABEZADO LIMPIO Y ALINEADO -->
            <div class="space-y-2">
                <!-- Enlace de regreso contextual -->
                <div>
                    <a href="{{ route('admin.moderacion.index') }}"
                       class="inline-flex items-center gap-1.5 text-xs sm:text-[13px] font-medium text-[#64748B] hover:text-[#722F37] transition-colors focus:outline-none focus:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Moderación de reportes</span>
                    </a>
                </div>

                <!-- Título principal, información secundaria e insignia de estado -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-2xl sm:text-[26px] font-bold text-[#1F2937] tracking-tight leading-tight">
                            Revisar reporte
                        </h1>
                        <p class="text-xs sm:text-[13px] font-medium text-[#64748B] mt-0.5">
                            <span class="text-[#1F2937] font-semibold">{{ $reporte->nombre ?? $reporte->titulo }}</span>
                            <span class="mx-1.5 text-slate-300">•</span>
                            <span>Folio: <strong class="font-mono text-[#1F2937]">{{ $reporte->folio }}</strong></span>
                        </p>
                    </div>

                    <!-- Insignia de estado -->
                    <div class="shrink-0">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $reporte->isCancelado() ? 'bg-rose-100 text-rose-900 border border-rose-300' : ($reporte->estado === 'Aprobada' ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : ($reporte->isPendienteRevision() ? 'bg-amber-100 text-amber-900 border border-amber-300' : ($reporte->isCorreccionSolicitada() ? 'bg-orange-100 text-orange-900 border border-orange-300' : 'bg-slate-100 text-slate-800'))) }}">
                            {{ $reporte->estado }}
                        </span>
                    </div>
                </div>

                <!-- Texto introductorio en una sola línea discreta -->
                <p class="text-xs sm:text-[13px] text-[#64748B] pt-0.5">
                    Verifica la información y las fotografías del reporte antes de tomar una decisión.
                </p>
            </div>

            <!-- MENSAJES DE ESTADO Y RETROALIMENTACIÓN -->
            @if (session('status'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-start gap-3 shadow-xs" role="alert">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-xs sm:text-sm font-semibold">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            <!-- ALERTA DE CONCURRENCIA O ERROR -->
            @if (session('error') || $errors->has('concurrencia') || $errors->has('cancelacion'))
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-300 text-rose-900 flex items-start gap-3 shadow-xs" role="alert">
                    <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="text-xs sm:text-sm font-semibold">
                        {{ session('error') ?? ($errors->first('concurrencia') ?? $errors->first('cancelacion')) }}
                    </div>
                </div>
            @endif

            <!-- AVISO DE ESTADO: Reporte ya procesado o no disponible para revisión -->
            @if (! $isDisponibleParaRevision)
                <div class="p-4 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 flex items-start gap-3 shadow-xs" role="alert">
                    <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="text-xs sm:text-sm">
                        <h2 class="font-semibold text-[#1F2937]">Este reporte ya no se encuentra en revisión pendiente</h2>
                        <p class="mt-0.5 text-amber-900">
                            Estado actual: <strong class="underline">{{ $reporte->estado }}</strong>. Las decisiones de aprobación o solicitud de corrección están deshabilitadas porque el reporte ya fue procesado o devuelto.
                        </p>
                    </div>
                </div>
            @endif

            <!-- 2. ORGANIZACIÓN GENERAL EN 2 COLUMNAS (38% fotografías, 62% información) -->
            <div class="flex flex-col lg:flex-row items-start gap-6">

                <!-- COLUMNA IZQUIERDA (38%): Fotografías -->
                <div class="w-full lg:w-[38%] shrink-0">
                    <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                        <!-- Título de tarjeta unificada -->
                        <div class="flex items-center justify-between border-b border-[#E2E8F0] pb-3">
                            <h2 class="text-base sm:text-[17px] font-semibold text-[#1F2937] flex items-center gap-2">
                                <span class="sr-only">Fotografía principal</span>
                                <span>Fotografías</span>
                            </h2>
                            <span class="text-xs text-[#64748B]">
                                {{ $reporte->fotografias->count() }} {{ $reporte->fotografias->count() === 1 ? 'imagen' : 'imágenes' }}
                            </span>
                        </div>

                        <!-- Visor de fotografía principal -->
                        <div class="relative w-full h-72 sm:h-80 rounded-lg overflow-hidden bg-[#F8FAFC] border border-[#E2E8F0] flex items-center justify-center">
                            @if ($fotografiaPrincipal)
                                <!-- Indicador visible de Fotografía Principal o Adicional -->
                                <div class="absolute top-2.5 left-2.5 z-10 pointer-events-none">
                                    <template x-if="activeId === {{ $fotografiaPrincipal->id }}">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white/95 text-xs font-semibold text-[#722F37] shadow-xs border border-[#E2E8F0]">
                                            <span class="w-2 h-2 rounded-full bg-[#722F37]"></span>
                                            <span>Fotografía principal</span>
                                        </span>
                                    </template>
                                    <template x-if="activeId !== {{ $fotografiaPrincipal->id }}">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white/95 text-xs font-medium text-[#64748B] shadow-xs border border-[#E2E8F0]">
                                            <span>Fotografía adicional</span>
                                        </span>
                                    </template>
                                </div>

                                <img :src="activeImg"
                                     alt="Fotografía principal de {{ $reporte->nombre ?? $reporte->titulo }}"
                                     class="w-full h-full object-contain p-2 cursor-zoom-in transition-transform duration-200 hover:scale-[1.02]"
                                     @click="modalOpen = true"
                                     title="Haz clic para ampliar la fotografía" />

                                <button type="button"
                                        @click="modalOpen = true"
                                        class="absolute bottom-2.5 right-2.5 inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-white/90 hover:bg-white text-xs font-medium text-[#1F2937] shadow-xs border border-[#E2E8F0] transition-colors"
                                        title="Ampliar fotografía">
                                    <svg class="w-3.5 h-3.5 text-[#64748B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                    </svg>
                                    <span>Ampliar</span>
                                </button>
                            @else
                                <div class="text-center p-6 text-[#64748B]">
                                    <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-xs font-medium">Sin fotografía adjunta</span>
                                </div>
                            @endif
                        </div>

                        <!-- Miniaturas de la galería -->
                        @if ($reporte->fotografias->count() > 1)
                            <div class="pt-2">
                                <span class="sr-only">Fotografías adicionales ({{ $fotografiasAdicionales->count() }})</span>
                                <div class="grid grid-cols-4 gap-2">
                                    @foreach ($reporte->fotografias as $foto)
                                        <button type="button"
                                                @click="activeImg = '{{ route('fotografias.show', $foto->id) }}'; activeId = {{ $foto->id }}"
                                                class="relative aspect-square rounded-md overflow-hidden bg-[#F8FAFC] border-2 transition-all cursor-pointer focus:outline-none"
                                                :class="activeId === {{ $foto->id }} ? 'border-[#722F37] ring-1 ring-[#722F37]' : 'border-[#E2E8F0] hover:border-slate-400'"
                                                title="{{ $loop->first ? 'Fotografía principal' : 'Fotografía adicional ' . $loop->iteration }}">
                                            <img src="{{ route('fotografias.show', $foto->id) }}"
                                                 alt="{{ $loop->first ? 'Fotografía principal' : 'Fotografía ' . $loop->iteration }} de {{ $reporte->nombre ?? $reporte->titulo }}"
                                                 class="w-full h-full object-cover" />
                                            @if ($loop->first)
                                                <span class="absolute bottom-1 left-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#722F37] text-white tracking-wider leading-none shadow-xs">
                                                    Principal
                                                </span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- COLUMNA DERECHA (62%): Información completa del reporte en una sola tarjeta -->
                <div class="w-full lg:flex-1 min-w-0">
                    <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-6 space-y-6">

                        <!-- OBSERVACIONES VIGENTES DE CORRECCIÓN (solo si existen) -->
                        @if (! empty($reporte->observaciones))
                            <div class="p-4 rounded-lg bg-amber-50/80 border border-amber-200 text-[#1F2937]" role="region" aria-label="Observaciones de corrección">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <svg class="w-4 h-4 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <h3 class="text-xs sm:text-[13px] font-semibold text-amber-900">
                                        Observaciones vigentes de corrección
                                    </h3>
                                </div>
                                <p class="text-xs sm:text-[13px] text-amber-950 leading-relaxed whitespace-pre-line">
                                    {{ $reporte->observaciones }}
                                </p>
                            </div>
                        @endif

                        <!-- HISTORIAL DE DECISIONES DE MODERACIÓN (HU-07 / Tarea 07.3) -->
                        @if ($reporte->decisiones && $reporte->decisiones->isNotEmpty())
                            <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 space-y-2">
                                <h3 class="text-xs font-semibold text-[#1F2937] flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-[#64748B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Historial de decisiones de moderación</span>
                                </h3>
                                <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                                    @foreach ($reporte->decisiones as $decision)
                                        <div class="text-xs p-2.5 rounded-md bg-white border border-[#E2E8F0]">
                                            <div class="flex items-center justify-between text-[11px] text-[#64748B] mb-1">
                                                <span class="font-semibold text-[#1F2937]">
                                                    {{ $decision->user->name ?? 'Administrador' }}
                                                    <span class="text-slate-400 font-normal">
                                                        ({{ $decision->tipo_decision === 'correccion_solicitada' ? 'Corrección solicitada' : $decision->tipo_decision }})
                                                    </span>
                                                </span>
                                                <span>{{ $decision->fecha_decision ? $decision->fecha_decision->format('d/m/Y H:i') : $decision->created_at->format('d/m/Y H:i') }}</span>
                                            </div>
                                            @if ($decision->motivo)
                                                <p class="text-[#1F2937] italic">"{{ $decision->motivo }}"</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- SECCIÓN 1: Datos del objeto -->
                        <div class="space-y-3">
                            <h2 class="text-base sm:text-[17px] font-semibold text-[#1F2937]">
                                Datos del objeto
                            </h2>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs sm:text-sm">
                                <div>
                                    <span class="text-xs font-medium text-[#64748B] block">Nombre del objeto</span>
                                    <span class="text-sm sm:text-base font-semibold text-[#1F2937] mt-0.5 block">
                                        {{ $reporte->nombre ?? $reporte->titulo }}
                                    </span>
                                </div>

                                <div>
                                    <span class="text-xs font-medium text-[#64748B] block">Categoría</span>
                                    <span class="text-sm font-medium text-[#1F2937] mt-0.5 block">
                                        {{ $reporte->categoria ?? 'No especificada' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Descripción continua como párrafo normal -->
                            <div class="pt-1">
                                <span class="text-xs font-medium text-[#64748B] block mb-1">Descripción</span>
                                <p class="text-xs sm:text-sm text-[#1F2937] leading-relaxed whitespace-pre-line">
                                    {{ $reporte->descripcion }}
                                </p>
                            </div>
                        </div>

                        <!-- SEPARADOR SUAVE -->
                        <hr class="border-[#E2E8F0]" />

                        <!-- SECCIÓN 2: Ubicación del hallazgo -->
                        <div class="space-y-3">
                            <h2 class="text-base sm:text-[17px] font-semibold text-[#1F2937]">
                                Ubicación del hallazgo
                            </h2>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs sm:text-sm">
                                <div>
                                    <span class="text-xs font-medium text-[#64748B] block">Área o zona</span>
                                    <span class="text-xs sm:text-sm font-medium text-[#1F2937] mt-0.5 block break-words">
                                        {{ $reporte->area_zona ?? $reporte->lugar_hallazgo ?? 'No especificada' }}
                                    </span>
                                </div>

                                <div>
                                    <span class="text-xs font-medium text-[#64748B] block">Ubicación específica</span>
                                    <span class="text-xs sm:text-sm font-medium text-[#1F2937] mt-0.5 block break-words">
                                        {{ $reporte->ubicacion_especifica ?? 'No especificada' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- SEPARADOR SUAVE -->
                        <hr class="border-[#E2E8F0]" />

                        <!-- SECCIÓN 3: Fechas y autor -->
                        <div class="space-y-3">
                            <h2 class="text-base sm:text-[17px] font-semibold text-[#1F2937]">
                                Fechas y autor
                            </h2>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs sm:text-sm">
                                <div>
                                    <span class="text-xs font-medium text-[#64748B] block">Fecha de captura</span>
                                    <span class="text-xs sm:text-sm font-medium text-[#1F2937] mt-0.5 block">
                                        {{ $reporte->fecha_captura ? $reporte->fecha_captura->format('d/m/Y H:i') : ($reporte->created_at ? $reporte->created_at->format('d/m/Y H:i') : '-') }}
                                    </span>
                                </div>

                                <div>
                                    <span class="text-xs font-medium text-[#64748B] block">Último envío a revisión</span>
                                    <span class="text-xs sm:text-sm font-medium text-[#1F2937] mt-0.5 block">
                                        {{ $reporte->fecha_envio_revision ? $reporte->fecha_envio_revision->format('d/m/Y H:i') : ($reporte->fecha_captura ? $reporte->fecha_captura->format('d/m/Y H:i') : '-') }}
                                    </span>
                                </div>

                                <div>
                                    <span class="text-xs font-medium text-[#64748B] block">Fecha y hora aproximada del hallazgo</span>
                                    <span class="text-xs sm:text-sm font-medium text-[#1F2937] mt-0.5 block">
                                        @if ($reporte->fecha_hallazgo)
                                            {{ $reporte->fecha_hallazgo->format('d/m/Y') }} {{ $reporte->hora_hallazgo ? 'a las ' . substr($reporte->hora_hallazgo, 0, 5) . ' hrs' : '' }}
                                        @else
                                            No especificada
                                        @endif
                                    </span>
                                </div>

                                @if ($reporte->fecha_primera_aprobacion)
                                    <div>
                                        <span class="text-xs font-medium text-[#64748B] block">Primera aprobación</span>
                                        <span class="text-xs sm:text-sm font-medium text-[#1F2937] mt-0.5 block">
                                            {{ $reporte->fecha_primera_aprobacion->format('d/m/Y H:i') }}
                                        </span>
                                    </div>
                                @endif

                                @if ($reporte->fecha_vencimiento)
                                    <div>
                                        <span class="text-xs font-medium text-[#64748B] block">Vencimiento de publicación (14 días)</span>
                                        <span class="text-xs sm:text-sm font-medium {{ $reporte->isVencida() ? 'text-rose-700 font-bold' : 'text-[#1F2937]' }} mt-0.5 block">
                                            {{ $reporte->fecha_vencimiento->format('d/m/Y H:i') }}
                                            @if ($reporte->isVencida())
                                                <span class="text-[10px] text-rose-600 font-normal block">(Publicación vencida)</span>
                                            @endif
                                        </span>
                                    </div>
                                @endif

                                <div>
                                    <span class="text-xs font-medium text-[#64748B] block">Autor del reporte</span>
                                    <span class="text-xs sm:text-sm font-medium text-[#1F2937] mt-0.5 block break-words">
                                        {{ $reporte->user->name ?? 'Usuario no disponible' }}
                                    </span>
                                    @if (! empty($reporte->user->email))
                                        <span class="text-xs text-[#64748B] block break-all">
                                            {{ $reporte->user->email }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- 5. ACCIONES DE MODERACIÓN (Bloque horizontal debajo de ambas columnas, ancho completo) -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 sm:p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <!-- Título y explicación breve a la izquierda -->
                    <div>
                        <h2 class="text-base sm:text-[17px] font-semibold text-[#1F2937]">
                            Acciones de moderación
                        </h2>
                        <p class="text-xs sm:text-[13px] text-[#64748B] mt-0.5">
                            Revisa la información y las fotografías antes de tomar una decisión.
                        </p>
                    </div>

                    <!-- Botones de decisión a la derecha (orden: Solicitar corrección primero, Aprobar publicación después) -->
                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
                        <!-- Tokens ocultos de concurrencia y versión -->
                        <input type="hidden" name="reporte_id" value="{{ $reporte->id }}">
                        <input type="hidden" name="lock_version" value="{{ $reporte->version ?? 1 }}">
                        <input type="hidden" name="updated_at_token" value="{{ $reporte->updated_at?->timestamp }}">

                        <!-- Acción Secundaria: Solicitar corrección (abre modal de motivo) -->
                        <button type="button"
                                @if (! $isDisponibleParaRevision) disabled @endif
                                @click="modalCorreccionOpen = true"
                                class="w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-white border border-[#CBD5E1] text-[#1F2937] hover:bg-slate-50 active:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-medium text-xs sm:text-[13px] transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400 cursor-pointer"
                                title="Solicitar corrección al autor del reporte {{ $reporte->folio }}"
                                data-action="solicitar-correccion">
                            <svg class="w-4 h-4 text-[#64748B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span>Solicitar corrección</span>
                        </button>

                        <!-- Acción Principal: Aprobar publicación -->
                        <button type="button"
                                @if (! $isDisponibleParaRevision) disabled @endif
                                @click="modalAprobarOpen = true"
                                class="w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-[#722F37] hover:bg-[#5B252C] active:bg-[#4A1D23] disabled:opacity-40 disabled:cursor-not-allowed text-white font-medium text-xs sm:text-[13px] shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#722F37] cursor-pointer"
                                title="Aprobar publicación del reporte {{ $reporte->folio }}"
                                data-action="aprobar-publicacion">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Aprobar publicación</span>
                        </button>

                        <!-- Acción Delicada: Cancelar publicación (disponible en estados compatibles) -->
                        @if ($reporte->isCancelable())
                            <button type="button"
                                    @click="modalCancelarOpen = true"
                                    class="w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-white border border-rose-300 text-rose-700 hover:bg-rose-50 active:bg-rose-100 disabled:opacity-40 disabled:cursor-not-allowed font-medium text-xs sm:text-[13px] transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-400 cursor-pointer"
                                    title="Cancelar publicación del reporte {{ $reporte->folio }}"
                                    data-action="cancelar-publicacion">
                                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span>Cancelar publicación</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

        </div>

        <!-- MODAL DE SOLICITAR CORRECCIÓN (HU-07 / Tarea 07.3) -->
        <div x-show="modalCorreccionOpen"
             x-cloak
             @keydown.escape.window="modalCorreccionOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-opacity"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-correccion-title">
            <div class="relative max-w-lg w-full bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4"
                 @click.away="modalCorreccionOpen = false">

                <!-- Encabezado del modal -->
                <div class="flex items-start justify-between border-b border-[#E2E8F0] pb-3">
                    <div>
                        <h3 id="modal-correccion-title" class="text-lg font-bold text-[#1F2937]">
                            Solicitar corrección
                        </h3>
                        <p class="text-xs text-[#64748B] mt-0.5">
                            Folio: <strong class="font-mono text-[#1F2937]">{{ $reporte->folio }}</strong>
                        </p>
                    </div>
                    <button type="button"
                            @click="modalCorreccionOpen = false"
                            class="text-[#64748B] hover:text-[#1F2937] p-1 rounded-md transition-colors focus:outline-none cursor-pointer"
                            title="Cerrar modal">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Formulario de solicitud de corrección -->
                <form method="POST"
                      action="{{ route('admin.moderacion.solicitar-correccion', $reporte->id) }}"
                      @submit="if(!validarMotivo()) { $event.preventDefault(); }"
                      class="space-y-4">
                    @csrf
                    <!-- Tokens de versión y concurrencia -->
                    <input type="hidden" name="reporte_id" value="{{ $reporte->id }}">
                    <input type="hidden" name="lock_version" value="{{ $reporte->version ?? 1 }}">
                    <input type="hidden" name="updated_at_token" value="{{ $reporte->updated_at?->timestamp }}">

                    <div>
                        <label for="motivo_correccion" class="block text-xs font-semibold text-[#1F2937] mb-1">
                            Motivo de la corrección <span class="text-rose-600">*</span>
                        </label>
                        <textarea id="motivo_correccion"
                                  name="motivo"
                                  rows="4"
                                  x-model="motivoTexto"
                                  @input="motivoError = ''"
                                  placeholder="Indica con claridad qué información o fotografía debe corregir el usuario..."
                                  class="w-full text-xs sm:text-sm rounded-lg border border-[#CBD5E1] p-3 text-[#1F2937] placeholder-slate-400 focus:border-[#722F37] focus:ring-1 focus:ring-[#722F37] outline-none transition-colors"
                                  required>{{ old('motivo') }}</textarea>

                        <p class="text-[11px] text-[#64748B] mt-1">
                            El motivo es obligatorio y no puede estar vacío ni contener únicamente espacios en blanco.
                        </p>

                        <!-- Error en frontend -->
                        <p x-show="motivoError" x-text="motivoError" class="text-xs text-rose-600 font-medium mt-1"></p>

                        <!-- Error en backend -->
                        @error('motivo')
                            <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Botones de acción del modal -->
                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5 pt-2 border-t border-[#E2E8F0]">
                        <button type="button"
                                @click="modalCorreccionOpen = false"
                                class="w-full sm:w-auto px-4 py-2 rounded-lg bg-white border border-[#CBD5E1] text-xs font-medium text-[#1F2937] hover:bg-slate-50 transition-colors focus:outline-none cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="w-full sm:w-auto px-5 py-2 rounded-lg bg-[#722F37] hover:bg-[#5B252C] text-xs font-medium text-white shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#722F37] cursor-pointer">
                            Confirmar solicitud
                        </button>
                    </div>
                </form>

            </div>
        </div>

        <!-- MODAL DE CANCELAR PUBLICACIÓN (HU-07 / Tarea 07.5) -->
        <div x-show="modalCancelarOpen"
             x-cloak
             @keydown.escape.window="modalCancelarOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-opacity"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-cancelar-title">
            <div class="relative max-w-lg w-full bg-white rounded-2xl border border-rose-200 shadow-2xl p-6 space-y-4"
                 @click.away="modalCancelarOpen = false">

                <!-- Encabezado del modal -->
                <div class="flex items-start justify-between border-b border-[#E2E8F0] pb-3">
                    <div>
                        <h3 id="modal-cancelar-title" class="text-lg font-bold text-rose-950 flex items-center gap-2">
                            <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>Cancelar publicación</span>
                        </h3>
                        <p class="text-xs text-[#64748B] mt-0.5">
                            <span>{{ $reporte->nombre ?? $reporte->titulo }}</span> • Folio: <strong class="font-mono text-[#1F2937]">{{ $reporte->folio }}</strong>
                        </p>
                    </div>
                    <button type="button"
                            @click="modalCancelarOpen = false"
                            class="text-[#64748B] hover:text-[#1F2937] p-1 rounded-md transition-colors focus:outline-none cursor-pointer"
                            title="Cerrar modal">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Advertencia explícita requerida -->
                <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-medium leading-relaxed">
                        La publicación se retirará del muro, pero su registro se conservará.
                    </span>
                </div>

                <!-- Formulario de cancelación -->
                <form method="POST"
                      action="{{ route('admin.moderacion.cancelar', $reporte->id) }}"
                      @submit="if(!validarMotivoCancelar()) { $event.preventDefault(); }"
                      class="space-y-4">
                    @csrf
                    <!-- Tokens de versión y concurrencia -->
                    <input type="hidden" name="reporte_id" value="{{ $reporte->id }}">
                    <input type="hidden" name="lock_version" value="{{ $reporte->version ?? 1 }}">
                    <input type="hidden" name="updated_at_token" value="{{ $reporte->updated_at?->timestamp }}">

                    <div>
                        <label for="motivo_cancelacion" class="block text-xs font-semibold text-[#1F2937] mb-1">
                            Motivo de la cancelación <span class="text-rose-600">*</span>
                        </label>
                        <textarea id="motivo_cancelacion"
                                  name="motivo"
                                  rows="3"
                                  x-model="motivoCancelarTexto"
                                  @input="motivoCancelarError = ''"
                                  placeholder="Explica detalladamente por qué se retira esta publicación..."
                                  class="w-full text-xs sm:text-sm rounded-lg border border-[#CBD5E1] p-3 text-[#1F2937] placeholder-slate-400 focus:border-rose-600 focus:ring-1 focus:ring-rose-600 outline-none transition-colors"
                                  required></textarea>
                        <p x-show="motivoCancelarError" x-text="motivoCancelarError" class="text-xs text-rose-600 font-medium mt-1"></p>
                    </div>

                    <div>
                        <label for="folio_referencia_duplicado" class="block text-xs font-semibold text-[#1F2937] mb-1">
                            Folio del reporte conservado (en caso de duplicado)
                        </label>
                        <input type="text"
                               id="folio_referencia_duplicado"
                               name="folio_referencia_duplicado"
                               x-model="folioDuplicadoTexto"
                               placeholder="Ej. OBJ-20261031-0001 (opcional)"
                               class="w-full text-xs sm:text-sm rounded-lg border border-[#CBD5E1] p-2.5 text-[#1F2937] placeholder-slate-400 focus:border-rose-600 focus:ring-1 focus:ring-rose-600 outline-none transition-colors" />
                        <p class="text-[11px] text-[#64748B] mt-1">
                            Si este objeto duplica a otro ya registrado, documenta el folio del reporte que se mantendrá activo.
                        </p>
                    </div>

                    <!-- Botones de acción del modal -->
                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5 pt-3 border-t border-[#E2E8F0]">
                        <button type="button"
                                @click="modalCancelarOpen = false"
                                class="w-full sm:w-auto px-4 py-2 rounded-lg bg-white border border-[#CBD5E1] text-xs font-medium text-[#1F2937] hover:bg-slate-50 transition-colors focus:outline-none cursor-pointer">
                            Volver
                        </button>
                        <button type="submit"
                                class="w-full sm:w-auto px-5 py-2 rounded-lg bg-rose-700 hover:bg-rose-800 text-xs font-medium text-white shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-600 cursor-pointer">
                            Confirmar cancelación
                        </button>
                    </div>
                </form>

            </div>
        </div>

        <!-- MODAL DE CONFIRMAR APROBACIÓN (HU-07 / Tarea 07.4) -->
        <div x-show="modalAprobarOpen"
             x-cloak
             @keydown.escape.window="modalAprobarOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-opacity"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-aprobar-title">
            <div class="relative max-w-md w-full bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4"
                 @click.away="modalAprobarOpen = false">

                <!-- Encabezado del modal -->
                <div class="flex items-start justify-between border-b border-[#E2E8F0] pb-3">
                    <div>
                        <h3 id="modal-aprobar-title" class="text-lg font-bold text-[#1F2937]">
                            Aprobar publicación
                        </h3>
                        <p class="text-xs text-[#64748B] mt-0.5">
                            Folio: <strong class="font-mono text-[#1F2937]">{{ $reporte->folio }}</strong>
                        </p>
                    </div>
                    <button type="button"
                            @click="modalAprobarOpen = false"
                            class="text-[#64748B] hover:text-[#1F2937] p-1 rounded-md transition-colors focus:outline-none cursor-pointer"
                            title="Cerrar modal">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3">
                    <p class="text-xs sm:text-sm text-[#1F2937] leading-relaxed">
                        ¿Deseas autorizar la publicación de <strong>{{ $reporte->nombre ?? $reporte->titulo }}</strong> en el muro institucional?
                    </p>
                    <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-950 text-xs space-y-1">
                        <div class="font-semibold flex items-center gap-1.5 text-amber-900">
                            <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Vigencia institucional: 14 días naturales</span>
                        </div>
                        <p class="text-amber-900/90 leading-relaxed">
                            @if ($reporte->fecha_vencimiento)
                                Este reporte ya cuenta con vigencia calculada hasta el <strong>{{ $reporte->fecha_vencimiento->format('d/m/Y H:i') }}</strong> (no se reinicia el plazo original).
                            @else
                                La publicación se mantendrá visible para la comunidad durante exactamente 14 días naturales a partir de este momento.
                            @endif
                        </p>
                    </div>
                </div>

                <!-- Formulario de aprobación -->
                <form method="POST" action="{{ route('admin.moderacion.aprobar', $reporte->id) }}">
                    @csrf
                    <!-- Tokens de versión y concurrencia -->
                    <input type="hidden" name="reporte_id" value="{{ $reporte->id }}">
                    <input type="hidden" name="lock_version" value="{{ $reporte->version ?? 1 }}">
                    <input type="hidden" name="updated_at_token" value="{{ $reporte->updated_at?->timestamp }}">

                    <!-- Botones de acción del modal -->
                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5 pt-3 border-t border-[#E2E8F0]">
                        <button type="button"
                                @click="modalAprobarOpen = false"
                                class="w-full sm:w-auto px-4 py-2 rounded-lg bg-white border border-[#CBD5E1] text-xs font-medium text-[#1F2937] hover:bg-slate-50 transition-colors focus:outline-none cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="w-full sm:w-auto px-5 py-2 rounded-lg bg-[#722F37] hover:bg-[#5B252C] text-xs font-medium text-white shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#722F37] cursor-pointer">
                            Confirmar aprobación
                        </button>
                    </div>
                </form>

            </div>
        </div>

        <!-- VISOR MODAL SENCILLO DE FOTOGRAFÍA (Alpine.js) -->
        <div x-show="modalOpen"
             x-cloak
             @keydown.escape.window="modalOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs transition-opacity"
             role="dialog"
             aria-modal="true"
             aria-label="Visor de fotografía">
            <div class="relative max-w-4xl max-h-[90vh] w-full flex flex-col items-center justify-center"
                 @click.away="modalOpen = false">
                <div class="w-full flex items-center justify-between mb-2 px-1">
                    <div>
                        @if ($fotografiaPrincipal)
                            <template x-if="activeId === {{ $fotografiaPrincipal->id }}">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white/20 text-white text-xs font-semibold backdrop-blur-xs">
                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                    <span>Fotografía principal</span>
                                </span>
                            </template>
                            <template x-if="activeId !== {{ $fotografiaPrincipal->id }}">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white/20 text-white text-xs font-medium backdrop-blur-xs">
                                    <span>Fotografía adicional</span>
                                </span>
                            </template>
                        @endif
                    </div>
                    <button type="button"
                            @click="modalOpen = false"
                            class="text-white/80 hover:text-white p-1 rounded-md text-sm font-semibold inline-flex items-center gap-1 focus:outline-none"
                            title="Cerrar visor">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <span>Cerrar</span>
                    </button>
                </div>
                <img :src="activeImg"
                     alt="Fotografía ampliada de {{ $reporte->nombre ?? $reporte->titulo }}"
                     class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl bg-black/20" />
            </div>
        </div>
    </div>
</x-app-layout>
