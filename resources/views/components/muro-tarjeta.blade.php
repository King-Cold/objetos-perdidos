@props(['publicacion'])

@php
    $fotoPrincipal = $publicacion->foto_principal;
    $nombreObjeto = $publicacion->nombre ?? $publicacion->titulo;
@endphp

<article class="bg-white rounded-2xl border border-tecnm-gold/30 hover:border-tecnm-gold shadow-xs hover:shadow-md transition-all duration-200 flex flex-col justify-between overflow-hidden group">
    <div>
        {{-- Fotografía principal --}}
        <div class="relative w-full aspect-[4/3] bg-slate-100 overflow-hidden border-b border-slate-100 object-cover">
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

        {{-- Información pública: Nombre del objeto, fecha del hallazgo y descripción completa --}}
        <div class="p-5">
            @if ($publicacion->fecha_hallazgo)
                <div class="flex items-center gap-1.5 text-xs text-slate-500 mb-2 font-medium">
                    <svg class="w-3.5 h-3.5 text-tecnm-wine/80 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Encontrado el {{ \Carbon\Carbon::parse($publicacion->fecha_hallazgo)->format('d/m/Y') }}</span>
                </div>
            @endif

            <h3 class="text-base sm:text-lg font-black text-slate-900 mb-2 leading-snug break-words">
                {{ $nombreObjeto }}
            </h3>
            <p class="text-xs sm:text-sm text-slate-700 leading-relaxed whitespace-pre-line break-words">
                {{ $publicacion->descripcion }}
            </p>
        </div>
    </div>

    @php
        $paramsRetorno = array_filter(
            request()->only(['buscar', 'fecha_hallazgo', 'page']),
            fn($v) => !is_null($v) && $v !== ''
        );
    @endphp

    {{-- Botón de acción con color institucional rojo vino --}}
    <div class="p-5 pt-0">
        <a
            href="{{ auth()->check() ? route('publicaciones.show', array_merge(['id' => $publicacion->id], $paramsRetorno)) : route('publicaciones.detalles-privados', array_merge(['id' => $publicacion->id], $paramsRetorno)) }}"
            class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs focus:outline-none focus:ring-2 focus:ring-tecnm-gold focus:ring-offset-2"
        >
            <span>Ver más detalles</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </a>
    </div>
</article>
