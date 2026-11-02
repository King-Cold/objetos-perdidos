@props(['publicacion'])

@php
    $fotoPrincipal = $publicacion->foto_principal;
    $nombreObjeto = $publicacion->nombre ?? $publicacion->titulo;
@endphp

<article class="bg-white rounded-2xl border border-tecnm-gold/30 hover:border-tecnm-gold shadow-xs hover:shadow-md transition-all duration-200 flex flex-col justify-between overflow-hidden group">
    <div>
        {{-- Fotografía principal (únicamente imagen de vista previa, no galería completa) --}}
        <div class="relative w-full aspect-[4/3] bg-slate-100 overflow-hidden border-b border-slate-100">
            @if ($fotoPrincipal)
                <img
                    src="{{ $fotoPrincipal->url }}"
                    alt="{{ $nombreObjeto }}"
                    loading="lazy"
                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                    onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"
                />
                <div class="hidden absolute inset-0 flex flex-col items-center justify-center bg-slate-50 text-slate-400 p-4 text-center">
                    <svg class="w-10 h-10 mb-1.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-xs font-semibold text-slate-500">Imagen no disponible</span>
                </div>
            @else
                <div class="absolute inset-0 flex flex-col items-center justify-center bg-slate-50 text-slate-400 p-4 text-center">
                    <svg class="w-10 h-10 mb-1.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-xs font-semibold text-slate-500">Imagen no disponible</span>
                </div>
            @endif
        </div>

        {{-- Información del objeto: Nombre del objeto y descripción completa --}}
        <div class="p-5">
            <h3 class="text-base sm:text-lg font-black text-slate-900 mb-2 leading-snug break-words">
                {{ $nombreObjeto }}
            </h3>
            <p class="text-xs sm:text-sm text-slate-700 leading-relaxed whitespace-pre-line break-words">
                {{ $publicacion->descripcion }}
            </p>

            @auth
                {{-- Información adicional ampliada exclusiva para usuarios autenticados (HU-09.2) --}}
                <div class="mt-4 pt-3.5 border-t border-slate-100 space-y-2 text-xs">
                    {{-- Disponibilidad actual --}}
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold text-[11px] uppercase tracking-wider">Disponibilidad:</span>
                        @if ($publicacion->isDisponible())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 text-[11px] font-bold border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span>Disponible</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[11px] font-bold border border-slate-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                <span>No disponible</span>
                            </span>
                        @endif
                    </div>

                    {{-- Fecha en que fue encontrado --}}
                    @if ($publicacion->fecha_hallazgo)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500 font-medium">Encontrado:</span>
                            <span class="text-slate-800 font-bold">
                                {{ $publicacion->fecha_hallazgo instanceof \Carbon\Carbon ? $publicacion->fecha_hallazgo->format('d/m/Y') : \Carbon\Carbon::parse($publicacion->fecha_hallazgo)->format('d/m/Y') }}
                                @if (!empty(trim((string)$publicacion->hora_hallazgo)))
                                    <span class="font-normal text-slate-500 text-[11px]">({{ substr(trim($publicacion->hora_hallazgo), 0, 5) }} hrs)</span>
                                @endif
                            </span>
                        </div>
                    @endif

                    {{-- Ubicación --}}
                    @if ($publicacion->area_zona || $publicacion->lugar_hallazgo)
                        <div class="flex items-start justify-between gap-2 text-xs">
                            <span class="text-slate-500 font-medium shrink-0">Ubicación:</span>
                            <span class="text-slate-800 font-bold text-right truncate">
                                {{ $publicacion->area_zona ?? $publicacion->lugar_hallazgo }}
                            </span>
                        </div>
                    @endif
                </div>
            @endauth
        </div>
    </div>

    {{-- Botón de acción independiente para ver detalles --}}
    <div class="p-5 pt-0">
        <a
            href="{{ auth()->check() ? route('publicaciones.show', $publicacion->id) : route('publicaciones.detalles-privados', $publicacion->id) }}"
            class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs focus:outline-none focus:ring-2 focus:ring-tecnm-gold focus:ring-offset-2"
        >
            <span>Ver más detalles</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </a>
    </div>
</article>
