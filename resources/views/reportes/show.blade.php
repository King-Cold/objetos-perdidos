<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-black text-xl text-slate-800 leading-tight">
                    Detalle del Reporte: {{ $publicacion->folio ?? 'Hallazgo' }}
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Consulta de datos capturados, fotografías y situación actual
                </p>
            </div>
            <div>
                <a href="{{ route('reportes.mis-reportes') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                    <span aria-hidden="true">&larr;</span>
                    <span>Volver a Mis Reportes</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Observaciones Administrativas de Biblioteca (CA-07, CA-08) -->
            @if ($publicacion->isEnCorreccion() && ($publicacion->motivo_correccion || $publicacion->observaciones_admin || $publicacion->observaciones))
                <div class="p-4 sm:p-5 rounded-2xl bg-amber-50 border border-amber-300 shadow-xs">
                    <div class="flex items-start gap-3">
                        <div class="p-2 rounded-xl bg-amber-100 text-amber-800 shrink-0 mt-0.5">
                            <svg class="w-5 h-5 text-amber-800" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-black text-amber-950">
                                Solicitud de Corrección por Biblioteca
                            </h3>
                            <p class="text-xs text-amber-900 mt-1 leading-relaxed font-medium">
                                {{ $publicacion->motivo_correccion ?? $publicacion->observaciones_admin ?? $publicacion->observaciones }}
                            </p>
                            <p class="text-[11px] text-amber-800/80 mt-2">
                                Atiende estas observaciones editando los datos del reporte para que pueda ser aprobado y publicado en el muro.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tarjeta Principal del Reporte (CA-03, CA-06) -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-6">
                <!-- Barra superior con Folio, Estado y Disponibilidad -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 border-b border-slate-100">
                    <div class="flex items-center gap-2 flex-wrap">
                        @if ($publicacion->folio)
                            <span class="px-3 py-1 rounded-lg bg-amber-50 border border-amber-200 text-xs font-black text-[#691C32] tracking-wider">
                                Folio: {{ $publicacion->folio }}
                            </span>
                        @endif
                        <span class="px-3 py-1 rounded-full bg-slate-100 text-xs font-bold text-slate-700">
                            {{ $publicacion->categoria ?? 'General' }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 rounded-full text-xs font-extrabold uppercase
                            @if ($publicacion->estado === 'Aprobada') bg-emerald-100 text-emerald-800
                            @elseif ($publicacion->estado === 'Pendiente de aprobación') bg-amber-100 text-amber-800
                            @elseif ($publicacion->estado === 'Requiere corrección' || $publicacion->estado === 'Corrección solicitada') bg-rose-100 text-rose-800
                            @elseif ($publicacion->estado === 'Cancelado') bg-slate-100 text-slate-600
                            @else bg-blue-100 text-blue-800
                            @endif">
                            {{ $publicacion->estado }}
                        </span>

                        @if ($publicacion->isDisponible())
                            <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200">
                                En el muro
                            </span>
                        @else
                            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold">
                                Fuera del muro
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Título y Descripción -->
                <div>
                    <h1 class="text-2xl font-black text-slate-900 mb-2">
                        {{ $publicacion->nombre ?? $publicacion->titulo }}
                    </h1>
                    <div class="p-4 bg-slate-50/70 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Descripción del hallazgo</span>
                        <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $publicacion->descripcion }}</p>
                    </div>
                </div>

                <!-- Cuadrícula de Información Técnica y Situación Actual -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                        <span class="text-slate-400 font-semibold block">Situación de resguardo:</span>
                        <span class="font-bold text-slate-800 text-sm">
                            {{ $publicacion->situacion_resguardo ?? 'En posesión del autor' }}
                        </span>
                    </div>

                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                        <span class="text-slate-400 font-semibold block">Vigencia de publicación:</span>
                        <span class="font-bold text-sm {{ $publicacion->fecha_primera_aprobacion ? 'text-slate-800' : 'text-amber-800' }}">
                            {{ $publicacion->vigencia_texto }}
                        </span>
                    </div>

                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                        <span class="text-slate-400 font-semibold block">Fecha y hora del hallazgo:</span>
                        <span class="font-bold text-slate-800">
                            {{ $publicacion->fecha_hallazgo ? $publicacion->fecha_hallazgo->format('d/m/Y') : 'No especificada' }}
                            {{ $publicacion->hora_hallazgo ? 'a las ' . substr($publicacion->hora_hallazgo, 0, 5) : '' }}
                        </span>
                    </div>

                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                        <span class="text-slate-400 font-semibold block">Momento de captura original:</span>
                        <span class="font-bold text-slate-800">
                            {{ $publicacion->fecha_registro_texto }}
                        </span>
                    </div>

                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 sm:col-span-2 space-y-1">
                        <span class="text-slate-400 font-semibold block">Ubicación del hallazgo:</span>
                        <span class="font-bold text-slate-800">
                            {{ $publicacion->area_zona }}
                            @if ($publicacion->ubicacion_especifica)
                                &bull; {{ $publicacion->ubicacion_especifica }}
                            @endif
                        </span>
                    </div>
                </div>

                <!-- Galería de Fotografías Asociadas (CA-06, Tarea 05.5) -->
                @if ($publicacion->fotografias && $publicacion->fotografias->isNotEmpty())
                    <div class="pt-4 border-t border-slate-100">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wider block mb-3">
                            Fotografías del reporte ({{ $publicacion->fotografias->count() }})
                        </span>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                            @foreach ($publicacion->fotografias as $index => $foto)
                                <div class="relative group rounded-2xl overflow-hidden border {{ $foto->es_principal ? 'border-amber-400 ring-2 ring-amber-400/60' : 'border-slate-200' }} bg-slate-50">
                                    <img
                                        src="{{ $foto->url }}"
                                        alt="{{ $foto->nombre_original ?? 'Fotografía del objeto' }}"
                                        class="w-full h-36 object-cover"
                                        loading="lazy"
                                    />
                                    <div class="p-2 bg-white/90 border-t border-slate-100 flex items-center justify-between text-[10px]">
                                        <span class="truncate text-slate-600 font-medium">{{ $foto->nombre_original ?? 'Foto ' . ($index + 1) }}</span>
                                        @if ($foto->es_principal)
                                            <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 font-extrabold shrink-0">Principal</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Acciones Permitidas (CA-03, CA-09) -->
                <div class="pt-6 border-t border-slate-100 flex items-center justify-between">
                    <a href="{{ route('reportes.mis-reportes') }}" class="px-4 py-2 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                        &larr; Volver a Mis Reportes
                    </a>

                    @if ($publicacion->puedeEditarse())
                        <a href="{{ route('reportes.edit', $publicacion->id) }}" class="px-4 py-2 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                            Editar reporte
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

