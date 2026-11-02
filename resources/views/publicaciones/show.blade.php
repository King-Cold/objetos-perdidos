@auth
    <x-app-layout>
        <x-slot name="header">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="font-black text-lg sm:text-xl text-slate-800 leading-tight">
                        Detalle del Hallazgo
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Información y características del objeto en resguardo
                    </p>
                </div>
                <div>
                    <a href="{{ route('muro') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors shadow-2xs">
                        <span aria-hidden="true">&larr;</span>
                        <span>Volver al Muro</span>
                    </a>
                </div>
            </div>
        </x-slot>

        @php
            $nombreObjeto = $publicacion->nombre ?? $publicacion->titulo;
            $fechaPublicacion = $publicacion->fecha_publicacion;
            
            // Fotografías aprobadas para publicación (HU-09.2): ordenadas con la principal primero
            $fotosAprobadas = ($publicacion->estado === \App\Models\Publicacion::ESTADO_APROBADA && $publicacion->isDisponible())
                ? $publicacion->fotografias->sortByDesc('es_principal')->values()
                : collect();
            $totalFotos = $fotosAprobadas->count();
            $fotoPrincipal = $fotosAprobadas->first();

            // Contacto según resguardo (HU-09.3): Biblioteca confirmada vs Posesión del autor
            $isResguardoBibliotecaConfirmado = $publicacion->hasRecepcionFisicaConfirmada() && $publicacion->isDisponible();
            $telefonoAutorValido = (! $isResguardoBibliotecaConfirmado && $publicacion->user) 
                ? $publicacion->user->telefono_valido 
                : null;
        @endphp

        <div class="py-6"
             x-data="{
                 fotos: {{ Js::from($fotosAprobadas->map(fn($f) => [
                     'id' => $f->id,
                     'url' => $f->url,
                     'es_principal' => (bool)$f->es_principal,
                     'nombre' => $f->nombre_original ?? $nombreObjeto,
                 ])->all()) }},
                 currentIndex: 0,
                 modalOpen: false,
                 copied: false,
                 async copyPhone(phone) {
                     try {
                         if (navigator.clipboard && navigator.clipboard.writeText) {
                             await navigator.clipboard.writeText(phone);
                         } else {
                             const el = document.createElement('textarea');
                             el.value = phone;
                             el.style.position = 'fixed';
                             el.style.opacity = '0';
                             document.body.appendChild(el);
                             el.select();
                             document.execCommand('copy');
                             document.body.removeChild(el);
                         }
                         this.copied = true;
                         setTimeout(() => { this.copied = false; }, 2500);
                     } catch (e) {
                         console.error(e);
                     }
                 },
                 next() {
                     if (this.fotos.length > 1) {
                         this.currentIndex = (this.currentIndex + 1) % this.fotos.length;
                     }
                 },
                 prev() {
                     if (this.fotos.length > 1) {
                         this.currentIndex = (this.currentIndex - 1 + this.fotos.length) % this.fotos.length;
                     }
                 },
                 goTo(index) {
                     if (index >= 0 && index < this.fotos.length) {
                         this.currentIndex = index;
                     }
                 },
                 get currentPhoto() {
                     return this.fotos[this.currentIndex] || null;
                 }
             }"
             @keydown.arrow-left.window="if (!modalOpen) prev()"
             @keydown.arrow-right.window="if (!modalOpen) next()"
             @keydown.escape.window="modalOpen = false">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- ORGANIZACIÓN EN 2 COLUMNAS (Galería a la izquierda, Información detallada a la derecha) -->
                <div class="flex flex-col lg:flex-row items-start gap-6">

                    <!-- COLUMNA IZQUIERDA (38%): Galería de fotografías aprobadas (HU-09.2) -->
                    <div class="w-full lg:w-[38%] shrink-0">
                        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5 space-y-4">
                            <!-- Título y contador de fotografías -->
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-[#691C32]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>Fotografías</span>
                                </h3>
                                @if ($totalFotos > 0)
                                    <span class="text-xs font-semibold text-slate-500" id="indicador-contador-galeria">
                                        <span x-text="(currentIndex + 1) + ' de ' + fotos.length">1 de {{ $totalFotos }}</span>
                                    </span>
                                @else
                                    <span class="text-xs font-semibold text-slate-400">
                                        0 imágenes
                                    </span>
                                @endif
                            </div>

                            <!-- Visor de fotografía principal / activa -->
                            <div class="relative w-full h-72 sm:h-80 rounded-2xl overflow-hidden bg-slate-50 border border-slate-200/80 flex items-center justify-center">
                                @if ($fotoPrincipal)
                                    <!-- Píldora indicadora de Fotografía Principal / Adicional -->
                                    <div class="absolute top-2.5 left-2.5 z-10 pointer-events-none">
                                        <template x-if="currentPhoto && currentPhoto.es_principal">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/95 text-[11px] font-bold text-[#691C32] shadow-xs border border-slate-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#691C32]"></span>
                                                <span>Fotografía principal</span>
                                            </span>
                                        </template>
                                        <template x-if="currentPhoto && !currentPhoto.es_principal">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/95 text-[11px] font-medium text-slate-600 shadow-xs border border-slate-200">
                                                <span>Fotografía adicional</span>
                                            </span>
                                        </template>
                                    </div>

                                    <!-- Indicador de posición flotante sobre la imagen (ej: 1 de 4) -->
                                    <div class="absolute top-2.5 right-2.5 z-10 pointer-events-none" id="indicador-posicion-visor">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white/95 text-[11px] font-bold text-slate-700 shadow-xs border border-slate-200">
                                            <span x-text="(currentIndex + 1) + ' de ' + fotos.length">1 de {{ $totalFotos }}</span>
                                        </span>
                                    </div>

                                    <!-- Imagen activa -->
                                    <img :src="currentPhoto ? currentPhoto.url : '{{ $fotoPrincipal->url }}'"
                                         src="{{ $fotoPrincipal->url }}"
                                         :alt="'Fotografía ' + (currentIndex + 1) + ' de ' + {{ json_encode($nombreObjeto) }}"
                                         alt="Fotografía principal de {{ $nombreObjeto }}"
                                         class="w-full h-full object-contain p-2 cursor-zoom-in transition-transform duration-200 hover:scale-[1.02]"
                                         @click="modalOpen = true"
                                         title="Haz clic para ampliar la fotografía" />

                                    @if ($totalFotos > 1)
                                        <!-- Control Anterior -->
                                        <button type="button"
                                                @click="prev()"
                                                class="absolute left-2.5 top-1/2 -translate-y-1/2 z-10 w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-white/90 hover:bg-white text-slate-700 hover:text-slate-900 shadow-md border border-slate-200 flex items-center justify-center transition-all hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#691C32] cursor-pointer"
                                                aria-label="Fotografía anterior"
                                                title="Fotografía anterior">
                                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                                            </svg>
                                        </button>

                                        <!-- Control Siguiente -->
                                        <button type="button"
                                                @click="next()"
                                                class="absolute right-2.5 top-1/2 -translate-y-1/2 z-10 w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-white/90 hover:bg-white text-slate-700 hover:text-slate-900 shadow-md border border-slate-200 flex items-center justify-center transition-all hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#691C32] cursor-pointer"
                                                aria-label="Fotografía siguiente"
                                                title="Fotografía siguiente">
                                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    @endif

                                    <!-- Botón Ampliar -->
                                    <button type="button"
                                            @click="modalOpen = true"
                                            class="absolute bottom-2.5 right-2.5 z-10 inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white/90 hover:bg-white text-xs font-semibold text-slate-700 shadow-xs border border-slate-200 transition-colors cursor-pointer"
                                            title="Ampliar fotografía">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                        </svg>
                                        <span>Ampliar</span>
                                    </button>
                                @else
                                    <div class="text-center p-6 text-slate-400">
                                        <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-xs font-semibold text-slate-500">Sin fotografía adjunta</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Miniaturas de la galería (para selección directa) -->
                            @if ($totalFotos > 1)
                                <div class="pt-1">
                                    <div class="grid grid-cols-4 gap-2">
                                        @foreach ($fotosAprobadas as $index => $foto)
                                            <button type="button"
                                                    @click="goTo({{ $index }})"
                                                    class="relative aspect-square rounded-xl overflow-hidden bg-slate-50 border-2 transition-all cursor-pointer focus:outline-none"
                                                    :class="currentIndex === {{ $index }} ? 'border-[#691C32] ring-2 ring-[#691C32]/30' : 'border-slate-200 hover:border-slate-300'"
                                                    title="{{ $foto->es_principal ? 'Fotografía principal' : 'Fotografía adicional ' . ($index) }}"
                                                    aria-label="Seleccionar fotografía {{ $index + 1 }} de {{ $totalFotos }}">
                                                <img src="{{ $foto->url }}"
                                                     alt="{{ $foto->nombre_original ?? 'Foto del objeto' }}"
                                                     class="w-full h-full object-cover" />
                                                @if ($foto->es_principal)
                                                    <span class="absolute bottom-1 left-1 px-1.5 py-0.5 rounded text-[8px] font-bold bg-[#691C32] text-white shadow-2xs">
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

                    <!-- COLUMNA DERECHA (62%): Información completa del hallazgo -->
                    <div class="w-full lg:flex-1 min-w-0">
                        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-slate-200/80 shadow-xs space-y-4">
                            <!-- Barra superior: Folio y Categoría (Sin Disponible repetido) -->
                            <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    @if ($publicacion->folio)
                                        <span class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-xs font-black text-[#691C32] tracking-wider">
                                            Folio: {{ $publicacion->folio }}
                                        </span>
                                    @endif
                                    <span class="px-2.5 py-1 rounded-full bg-slate-100 text-xs font-bold text-slate-700">
                                        {{ $publicacion->categoria ?? 'General' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Título principal del objeto -->
                            <div>
                                <h1 class="text-xl sm:text-2xl font-black text-slate-900 leading-snug break-words">
                                    {{ $nombreObjeto }}
                                </h1>
                            </div>

                            <!-- Descripción completa -->
                            <div class="p-4 bg-slate-50/70 rounded-2xl border border-slate-100">
                                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                    Descripción completa
                                </span>
                                <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line break-words">
                                    {{ $publicacion->descripcion }}
                                </p>
                            </div>

                            <!-- Cuadrícula de Datos del Hallazgo (Diseño estructurado con cajas) -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs pt-1">
                                <!-- 1. Fecha del hallazgo -->
                                <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-100 space-y-0.5">
                                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                                        Fecha del hallazgo
                                    </span>
                                    <span class="font-bold text-slate-800 text-xs sm:text-sm block">
                                        {{ $publicacion->fecha_hallazgo ? ($publicacion->fecha_hallazgo instanceof \Carbon\Carbon ? $publicacion->fecha_hallazgo->format('d/m/Y') : \Carbon\Carbon::parse($publicacion->fecha_hallazgo)->format('d/m/Y')) : 'No especificada' }}
                                    </span>
                                    @if (!empty(trim((string)$publicacion->hora_hallazgo)))
                                        <span class="block text-[11px] font-medium text-slate-500">
                                            Hora aproximada: {{ substr(trim($publicacion->hora_hallazgo), 0, 5) }} hrs
                                        </span>
                                    @endif
                                </div>

                                <!-- 2. Fecha de publicación -->
                                <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-100 space-y-0.5">
                                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                                        Fecha de publicación
                                    </span>
                                    <span class="font-bold text-slate-800 text-xs sm:text-sm block">
                                        {{ $fechaPublicacion ? $fechaPublicacion->format('d/m/Y') : 'No disponible' }}
                                    </span>
                                </div>

                                <!-- 3. Área o edificio -->
                                <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-100 space-y-0.5">
                                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                                        Área o edificio
                                    </span>
                                    <span class="font-bold text-slate-800 text-xs sm:text-sm block">
                                        {{ $publicacion->area_zona ?? 'No especificado' }}
                                    </span>
                                </div>

                                <!-- 4. Ubicación específica -->
                                <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-100 space-y-0.5">
                                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                                        Ubicación específica
                                    </span>
                                    <span class="font-bold text-slate-800 text-xs sm:text-sm block">
                                        {{ $publicacion->ubicacion_especifica ?? 'No especificada' }}
                                    </span>
                                </div>

                                <!-- 5. Nombre completo del autor del reporte -->
                                <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-100 space-y-0.5">
                                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                                        Nombre completo del autor del reporte
                                    </span>
                                    <span class="font-bold text-slate-800 text-xs sm:text-sm block">
                                        {{ $publicacion->user?->name ?? 'No disponible' }}
                                    </span>
                                </div>

                                <!-- 6. Disponibilidad actual (Única en toda la vista) -->
                                <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-100 space-y-0.5">
                                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                                        Disponibilidad actual
                                    </span>
                                    <div class="pt-0.5">
                                        @if ($publicacion->isDisponible())
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                <span>Disponible</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-xs font-bold border border-slate-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                                <span>No disponible</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Sección de Contacto según Resguardo (HU-09.3 / CA-16 / CA-20) --}}
                            @if ($isResguardoBibliotecaConfirmado)
                                <!-- Recepción física confirmada en Biblioteca (HU-09.3 / CA-20) -->
                                <div class="p-3.5 sm:p-4 rounded-2xl bg-amber-50/80 border border-amber-200" id="resguardo-biblioteca-confirmado">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 mt-0.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                        </div>
                                        <div class="space-y-1 text-xs text-amber-900 leading-relaxed flex-1">
                                            <div class="flex items-center gap-2 mb-0.5">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold border border-emerald-300">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    <span>Disponible en biblioteca</span>
                                                </span>
                                            </div>

                                            <h3 class="text-xs sm:text-sm font-extrabold text-amber-950">
                                                Objeto bajo resguardo confirmado de Biblioteca
                                            </h3>

                                            <p>
                                                <strong>Ubicación de atención:</strong> {{ $publicacion->lugar_hallazgo ?? 'Mostrador principal de Biblioteca Campus Poniente' }}
                                            </p>
                                            <p class="sr-only">
                                                <strong>Ubicación de resguardo:</strong> {{ $publicacion->lugar_hallazgo ?? 'Biblioteca Campus Poniente' }}
                                            </p>
                                            <p>
                                                <strong>Horario de atención:</strong> Lunes a Viernes de 7:00 a 19:00 hrs.
                                            </p>
                                            <p class="text-amber-800 text-[11px] mt-1">
                                                <strong>Instrucciones de recuperación:</strong> Preséntate en el mostrador principal de Biblioteca portando tu credencial institucional vigente y acredita la propiedad del objeto con características específicas ante el personal encargado.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @elseif ($telefonoAutorValido)
                                <!-- Contacto vigente con el Autor del Reporte (HU-09.3 / CA-16) -->
                                <div class="p-4 sm:p-5 rounded-2xl bg-linear-to-br from-slate-50 via-white to-amber-50/20 border border-slate-200/90 shadow-2xs" id="contacto-autor-vigente">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                        <!-- Información de contacto y teléfono -->
                                        <div class="flex items-start sm:items-center gap-3.5 min-w-0">
                                            <div class="w-10 h-10 rounded-xl bg-tecnm-wine/10 text-tecnm-wine flex items-center justify-center shrink-0 mt-0.5 sm:mt-0 ring-1 ring-tecnm-wine/20">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider block">
                                                        Contacto directo con el autor
                                                    </span>
                                                    <!-- Insignia feedback de copiado -->
                                                    <span x-show="copied"
                                                          x-cloak
                                                          x-transition:enter="transition ease-out duration-200"
                                                          x-transition:enter-start="opacity-0 scale-90"
                                                          x-transition:enter-end="opacity-100 scale-100"
                                                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                        <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        <span>¡Copiado!</span>
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                                    <span class="text-sm sm:text-base font-black text-slate-900 tracking-tight select-text" id="contacto-telefono-vigente">
                                                        Teléfono: {{ $publicacion->user->phone }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Acciones: Copiar y Llamar -->
                                        <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                                            <!-- Botón Copiar -->
                                            <button type="button"
                                                    @click="copyPhone('{{ $telefonoAutorValido }}')"
                                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-300 hover:border-slate-400 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-all shadow-2xs active:scale-95 cursor-pointer"
                                                    title="Copiar número al portapapeles">
                                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                                <span x-text="copied ? '¡Copiado!' : 'Copiar'">Copiar</span>
                                            </button>

                                            <!-- Botón Llamar (HU-09.3) -->
                                            <a href="tel:{{ $telefonoAutorValido }}"
                                               class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-all shadow-xs hover:shadow-md active:scale-95 cursor-pointer"
                                               id="boton-llamar"
                                               title="Llamar al {{ $publicacion->user->phone }}">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                </svg>
                                                <span>Llamar</span>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <!-- Información del autor -->
                                    <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                                        <span>Autor del reporte: <strong class="text-slate-700 font-semibold">{{ $publicacion->user?->name }}</strong></span>
                                        <span class="hidden sm:inline-block text-slate-400">Presiona Copiar o Llamar según tu dispositivo</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL PARA AMPLIAR FOTOGRAFÍA (Alpine.js con navegación) -->
            <div x-show="modalOpen"
                 x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/80 backdrop-blur-xs flex items-center justify-center p-4"
                 @keydown.escape.window="modalOpen = false"
                 @keydown.arrow-left.window="if (modalOpen) prev()"
                 @keydown.arrow-right.window="if (modalOpen) next()">
                <div class="relative bg-white rounded-3xl max-w-4xl w-full p-4 sm:p-6 shadow-2xl border border-slate-200"
                     @click.away="modalOpen = false">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-bold text-slate-800">
                                Fotografía de {{ $nombreObjeto }}
                            </h4>
                            @if ($totalFotos > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-[11px] font-bold text-slate-600 border border-slate-200"
                                      x-text="(currentIndex + 1) + ' de ' + fotos.length">
                                    1 de {{ $totalFotos }}
                                </span>
                            @endif
                        </div>
                        <button type="button"
                                @click="modalOpen = false"
                                class="p-1 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                                title="Cerrar visor">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="relative max-h-[75vh] overflow-hidden rounded-2xl flex items-center justify-center bg-slate-50">
                        <img :src="currentPhoto ? currentPhoto.url : ''"
                             alt="Fotografía ampliada de {{ $nombreObjeto }}"
                             class="max-h-[75vh] w-auto object-contain rounded-2xl" />

                        @if ($totalFotos > 1)
                            <!-- Controles Anterior y Siguiente dentro del modal -->
                            <button type="button"
                                    @click="prev()"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 hover:bg-white text-slate-800 shadow-md border border-slate-200 flex items-center justify-center transition-all hover:scale-105 cursor-pointer"
                                    aria-label="Fotografía anterior"
                                    title="Fotografía anterior">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>

                            <button type="button"
                                    @click="next()"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 hover:bg-white text-slate-800 shadow-md border border-slate-200 flex items-center justify-center transition-all hover:scale-105 cursor-pointer"
                                    aria-label="Fotografía siguiente"
                                    title="Fotografía siguiente">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-app-layout>
@else
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="csrf-token" content="{{ csrf_token() }}">

            <title>{{ $publicacion->titulo }} - Detalle &bull; TecNM Campus Poniente</title>

            <link rel="preconnect" href="https://fonts.bunny.net">
            <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

            @vite(['resources/css/app.css', 'resources/js/app.js'])
        </head>
        <body class="font-sans antialiased bg-gray-100 min-h-screen text-slate-800 flex flex-col">
            <!-- Header para Invitados -->
            <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="p-1.5 rounded-xl bg-white shadow-xs ring-1 ring-amber-400">
                            <img src="{{ asset('images/logo-tecnm.png') }}" alt="TecNM" class="h-7 w-auto" />
                        </div>
                        <span class="text-sm font-extrabold text-slate-900">
                            Detalle de Publicación
                        </span>
                    </div>
                    <a href="{{ route('muro') }}" class="px-3.5 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                        Volver al Muro
                    </a>
                </div>
            </header>

            @php
                $fotoPrincipal = $publicacion->foto_principal;
                $nombreObjeto = $publicacion->nombre ?? $publicacion->titulo;
            @endphp

            <!-- Contenedor Principal para Visitantes (Consulta Pública sin Sesión) en 2 Columnas -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
                <div class="flex flex-col lg:flex-row items-start gap-6">
                    <!-- Columna Izquierda: Fotografía principal exclusiva para visitantes sin sesión -->
                    <div class="w-full lg:w-[38%] shrink-0">
                        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs">
                            <div class="relative w-full h-72 sm:h-80 bg-slate-100 rounded-2xl overflow-hidden border border-slate-100 flex items-center justify-center">
                                @if ($fotoPrincipal)
                                    <img
                                        src="{{ $fotoPrincipal->url }}"
                                        alt="{{ $nombreObjeto }}"
                                        class="w-full h-full object-contain p-2"
                                        onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"
                                    />
                                    <div class="hidden absolute inset-0 flex flex-col items-center justify-center bg-slate-50 text-slate-400 p-4 text-center">
                                        <svg class="w-12 h-12 mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-xs font-semibold text-slate-500">Imagen no disponible</span>
                                    </div>
                                @else
                                    <div class="text-center p-6 text-slate-400">
                                        <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-xs font-semibold text-slate-500">Sin fotografía adjunta</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Información pública y bloqueo de contacto -->
                    <div class="w-full lg:flex-1 min-w-0">
                        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-4">
                            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 break-words">
                                {{ $nombreObjeto }}
                            </h1>

                            <div class="p-4 bg-slate-50/70 rounded-2xl border border-slate-100">
                                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                    Descripción completa
                                </span>
                                <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line break-words">
                                    {{ $publicacion->descripcion }}
                                </p>
                            </div>

                            <!-- Aviso de contacto protegido -->
                            <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200 text-amber-900">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 mt-0.5">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 text-xs leading-relaxed space-y-1">
                                        <h3 class="text-xs sm:text-sm font-bold text-amber-950">
                                            Información de contacto protegida
                                        </h3>
                                        <p class="text-amber-900">
                                            Por motivos de privacidad y seguridad de la comunidad del TecNM Campus Poniente, los datos de contacto, resguardo y procedimientos de reclamo están reservados para usuarios registrados.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Acciones -->
                            <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                                <a href="{{ route('muro') }}" class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold text-center transition-colors">
                                    Volver al Muro
                                </a>
                                <a href="{{ route('login') }}" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold text-center transition-colors shadow-xs">
                                    Iniciar sesión
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </body>
    </html>
@endauth
