@props(['paginator'])

@if ($paginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $paginator->hasPages())
    <nav role="navigation" aria-label="Paginación del muro" class="mt-8 pt-6 border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-4">
        {{-- Información del estado de paginación --}}
        <div class="text-xs text-slate-500 font-medium order-2 sm:order-1 text-center sm:text-left">
            Mostrando página <span class="font-bold text-slate-800">{{ $paginator->currentPage() }}</span> de <span class="font-bold text-slate-800">{{ $paginator->lastPage() }}</span>
            <span class="text-slate-400">({{ $paginator->total() }} {{ $paginator->total() === 1 ? 'objeto disponible' : 'objetos disponibles' }})</span>
        </div>

        {{-- Controles Anterior y Siguiente condicionales --}}
        <div class="flex items-center gap-3 order-1 sm:order-2 w-full sm:w-auto justify-between sm:justify-end">
            {{-- Control «Anterior»: Solo se ofrece cuando exista página previa --}}
            @if (! $paginator->onFirstPage())
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    rel="prev"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-tecnm-gold/50 bg-white hover:bg-amber-50/50 hover:border-tecnm-gold text-slate-800 text-xs sm:text-sm font-bold transition-all shadow-xs focus:outline-none focus:ring-2 focus:ring-tecnm-gold"
                >
                    <svg class="w-4 h-4 text-tecnm-wine" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    <span>Anterior</span>
                </a>
            @endif

            {{-- Control «Siguiente»: Solo se ofrece cuando exista página posterior --}}
            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    rel="next"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs sm:text-sm font-bold transition-all shadow-xs focus:outline-none focus:ring-2 focus:ring-tecnm-gold ml-auto sm:ml-0"
                >
                    <span>Siguiente</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endif
        </div>
    </nav>
@endif
