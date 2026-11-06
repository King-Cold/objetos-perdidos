<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-rose-50 border border-rose-100 text-[#691C32] flex items-center justify-center shrink-0 shadow-2xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-[#691C32] border border-rose-200">
                            Historial Privado
                        </span>
                        <span class="text-xs text-slate-400">&bull;</span>
                        <span class="text-xs font-mono font-semibold text-slate-500">{{ $publicacion->folio }}</span>
                    </div>
                    <h2 class="font-black text-xl sm:text-2xl text-slate-900 leading-tight">
                        Historial del Objeto: {{ $publicacion->folio }}
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Consulta protegida de la situación actual y eventos cronológicos del reporte
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto">
                @if (Auth::user() && Auth::user()->isAdmin())
                    <a href="{{ route('admin.moderacion.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors shadow-2xs">
                        <span aria-hidden="true">&larr;</span>
                        <span>Volver a Gestión</span>
                    </a>
                @else
                    <a href="{{ route('reportes.mis-reportes') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors shadow-2xs">
                        <span aria-hidden="true">&larr;</span>
                        <span>Volver a Mis Reportes</span>
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8 bg-slate-50/60 min-h-[calc(100vh-140px)]">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- 1. ENCABEZADO PRINCIPAL: IDENTIFICACIÓN Y SITUACIÓN ACTUAL DEL OBJETO --}}
            <section aria-labelledby="situacion-actual-heading" class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs overflow-hidden">
                {{-- Barra superior de la sección de situación actual --}}
                <div class="p-4 sm:p-5 border-b border-slate-100 bg-gradient-to-r from-rose-50/40 via-white to-amber-50/30 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-[#691C32] text-white flex items-center justify-center shrink-0 shadow-2xs">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <h3 id="situacion-actual-heading" class="text-base sm:text-lg font-black text-slate-900 tracking-tight">
                                Situación Actual del Objeto
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">
                                Condición y datos vigentes en el sistema, diferenciados de los eventos históricos
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 self-start sm:self-auto">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                            Estado Vigente
                        </span>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-[#691C32] border border-rose-200">
                            Acceso Protegido
                        </span>
                    </div>
                </div>

                {{-- Contenido estructurado con los 5 campos clave actuales claramente identificables --}}
                <div class="p-5 sm:p-6 space-y-5">
                    {{-- Fila con los 5 parámetros principales --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        {{-- 1. Folio único --}}
                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-col justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-1">
                                Folio del reporte
                            </span>
                            <span class="text-sm sm:text-base font-black text-[#691C32] font-mono tracking-tight" id="dato-actual-folio">
                                {{ $publicacion->folio }}
                            </span>
                        </div>

                        {{-- 2. Nombre del objeto --}}
                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 sm:col-span-1 lg:col-span-1 flex flex-col justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-1">
                                Nombre del objeto
                            </span>
                            <span class="text-sm sm:text-base font-bold text-slate-900 truncate" title="{{ $publicacion->nombre ?? $publicacion->titulo }}" id="dato-actual-objeto">
                                {{ $publicacion->nombre ?? $publicacion->titulo }}
                            </span>
                        </div>

                        {{-- 3. Estado de revisión --}}
                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-col justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-1">
                                Estado de revisión
                            </span>
                            <div>
                                @if ($publicacion->estado === 'Aprobada')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-900 border border-emerald-300" id="dato-actual-estado">
                                        {{ $publicacion->estado }}
                                    </span>
                                @elseif ($publicacion->estado === 'Pendiente de aprobación')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300" id="dato-actual-estado">
                                        {{ $publicacion->estado }}
                                    </span>
                                @elseif ($publicacion->isEnCorreccion() || $publicacion->estado === 'Requiere corrección' || $publicacion->estado === 'Corrección solicitada')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-900 border border-rose-300" id="dato-actual-estado">
                                        {{ $publicacion->estado }}
                                    </span>
                                @elseif ($publicacion->isCancelado())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-200 text-slate-800 border border-slate-300" id="dato-actual-estado">
                                        Cancelado
                                    </span>
                                @elseif ($publicacion->estado === 'Devuelto')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-900 border border-purple-300" id="dato-actual-estado">
                                        Devuelto
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-900 border border-blue-300" id="dato-actual-estado">
                                        {{ $publicacion->estado }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- 4. Disponibilidad actual --}}
                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-col justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-1">
                                Disponibilidad
                            </span>
                            <div>
                                @if ($publicacion->isDisponible())
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-300" id="dato-actual-disponibilidad">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span>Disponible en el muro</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-300" id="dato-actual-disponibilidad">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        <span>Fuera del muro</span>
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- 5. Situación de resguardo --}}
                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-col justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-1">
                                Situación de resguardo
                            </span>
                            <span class="text-xs sm:text-sm font-bold text-slate-800 leading-snug" id="dato-actual-resguardo">
                                {{ $publicacion->situacion_resguardo ?? \App\Models\Publicacion::RESGUARDO_AUTOR }}
                            </span>
                        </div>
                    </div>

                    {{-- Datos complementarios del reporte en una franja sutil --}}
                    <div class="pt-3 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-slate-400 font-medium">Categoría:</span>
                            <span class="font-bold text-slate-800">{{ $publicacion->categoria ?? 'General' }}</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-slate-400 font-medium">Fecha de hallazgo:</span>
                            <span class="font-bold text-slate-800">{{ $publicacion->fecha_hallazgo_texto }}</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-slate-400 font-medium">Ubicación:</span>
                            <span class="font-bold text-slate-800 truncate" title="{{ $publicacion->area_zona }}@if ($publicacion->ubicacion_especifica) - {{ $publicacion->ubicacion_especifica }}@endif">
                                {{ $publicacion->area_zona }}@if ($publicacion->ubicacion_especifica) - {{ $publicacion->ubicacion_especifica }}@endif
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-slate-400 font-medium">Vigencia:</span>
                            <span class="font-bold {{ $publicacion->fecha_primera_aprobacion ? 'text-slate-800' : 'text-amber-700' }}">{{ $publicacion->vigencia_texto }}</span>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 2. SECCIÓN CLARAMENTE DIFERENCIADA: HISTORIAL DE EVENTOS REGISTRADOS --}}
            <section aria-labelledby="eventos-historicos-heading" class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 bg-slate-50/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-slate-200 text-slate-700 flex items-center justify-center shrink-0">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 id="eventos-historicos-heading" class="text-base sm:text-lg font-black text-slate-900 tracking-tight">
                                Historial de Eventos Registrados
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">
                                Sucesos cronológicos registrados en la vida del objeto (creación, moderación, entregas e incidencias)
                            </p>
                        </div>
                    </div>

                    <div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-900 border border-amber-200">
                            Orden cronológico
                        </span>
                    </div>
                </div>

                {{-- Contenido de eventos cronológicos ascendentes (HU-13 / Tarea 2) --}}
                <div class="p-5 sm:p-6">
                    @if (isset($eventos) && $eventos->isNotEmpty())
                        <div class="relative pl-6 sm:pl-8 border-l-2 border-slate-200 space-y-6" id="linea-tiempo-eventos">
                            @foreach ($eventos as $index => $ev)
                                @php
                                    $color = $ev['color'] ?? 'slate';
                                    $dotBg = match($color) {
                                        'wine' => 'bg-[#691C32]',
                                        'emerald' => 'bg-emerald-600',
                                        'amber' => 'bg-amber-500',
                                        'blue' => 'bg-blue-600',
                                        'orange' => 'bg-orange-500',
                                        'rose' => 'bg-rose-600',
                                        default => 'bg-slate-500',
                                    };
                                    $ringBg = match($color) {
                                        'wine' => 'ring-rose-100',
                                        'emerald' => 'ring-emerald-100',
                                        'amber' => 'ring-amber-100',
                                        'blue' => 'ring-blue-100',
                                        'orange' => 'ring-orange-100',
                                        'rose' => 'ring-rose-100',
                                        default => 'ring-slate-100',
                                    };
                                    $badgeClass = match($color) {
                                        'wine' => 'bg-rose-100 text-[#691C32] border-rose-200',
                                        'emerald' => 'bg-emerald-100 text-emerald-900 border-emerald-300',
                                        'amber' => 'bg-amber-100 text-amber-900 border-amber-300',
                                        'blue' => 'bg-blue-100 text-blue-900 border-blue-300',
                                        'orange' => 'bg-orange-100 text-orange-900 border-orange-300',
                                        'rose' => 'bg-rose-100 text-rose-900 border-rose-300',
                                        default => 'bg-slate-100 text-slate-800 border-slate-300',
                                    };
                                    $cardBg = match($color) {
                                        'wine' => 'bg-gradient-to-r from-rose-50/50 to-white border-rose-200/80',
                                        'emerald' => 'bg-gradient-to-r from-emerald-50/40 to-white border-emerald-200/80',
                                        'amber' => 'bg-gradient-to-r from-amber-50/40 to-white border-amber-200/80',
                                        'blue' => 'bg-gradient-to-r from-blue-50/40 to-white border-blue-200/80',
                                        'orange' => 'bg-gradient-to-r from-orange-50/40 to-white border-orange-200/80',
                                        'rose' => 'bg-gradient-to-r from-rose-50/50 to-white border-rose-200/90',
                                        default => 'bg-gradient-to-r from-slate-50/60 to-white border-slate-200/80',
                                    };
                                @endphp

                                <div class="relative group evento-item" data-tipo="{{ $ev['tipo'] }}" data-index="{{ $index }}">
                                    {{-- Nodo de la línea de tiempo --}}
                                    <span class="absolute -left-[31px] sm:-left-[39px] top-1.5 w-4 h-4 rounded-full {{ $dotBg }} ring-4 {{ $ringBg }} flex items-center justify-center shadow-xs transition-transform group-hover:scale-110">
                                        <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                    </span>

                                    {{-- Tarjeta del evento --}}
                                    <div class="p-4 sm:p-5 rounded-2xl border {{ $cardBg }} shadow-2xs space-y-2.5">
                                        {{-- Fila superior: Tipo de evento e Indicador temporal (Fecha y Hora) --}}
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 pb-2 border-b border-slate-100/80">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $badgeClass }}">
                                                    {{ $ev['tipo'] }}
                                                </span>
                                                <span class="text-[11px] font-semibold text-slate-400">
                                                    Evento #{{ $index + 1 }}
                                                </span>
                                            </div>

                                            <div class="flex items-center gap-1.5 text-xs text-slate-600 font-mono">
                                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span class="font-bold text-slate-800">{{ $ev['fecha_hora_texto'] }}</span>
                                            </div>
                                        </div>

                                        {{-- Fila intermedia: Responsable del evento --}}
                                        <div class="flex items-center gap-2 text-xs">
                                            <div class="w-5 h-5 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-[10px] font-bold shrink-0">
                                                {{ strtoupper(substr($ev['responsable'], 0, 1)) }}
                                            </div>
                                            <div class="flex items-baseline gap-1.5 min-w-0">
                                                <span class="text-slate-400 font-medium shrink-0">Responsable:</span>
                                                <strong class="text-slate-900 font-bold truncate">{{ $ev['responsable'] }}</strong>
                                            </div>
                                        </div>

                                        {{-- Fila inferior: Motivo u observación autorizada --}}
                                        @if (! empty($ev['motivo']))
                                            <div class="mt-1.5 p-3 rounded-xl bg-white/90 border border-slate-200/70 text-xs text-slate-700 leading-relaxed shadow-2xs">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">
                                                    Motivo u observación:
                                                </span>
                                                <p class="font-medium text-slate-800 whitespace-pre-line">
                                                    {{ $ev['motivo'] }}
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        {{-- Estado vacío de eventos --}}
                        <div class="py-12 px-6 text-center">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h4 class="text-sm font-bold text-slate-800">No se encontraron eventos registrados</h4>
                            <p class="text-xs text-slate-500 mt-0.5">No hay antecedentes disponibles para este objeto en este momento.</p>
                        </div>
                    @endif
                </div>
            </section>

        </div>
    </div>
</x-app-layout>

