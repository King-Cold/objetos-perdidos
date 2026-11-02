@props(['errorFecha' => null])

<div class="mb-8 bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs">
    <form method="GET" action="{{ route('muro') }}" class="flex flex-col lg:flex-row items-stretch lg:items-end gap-3.5" role="search" aria-label="Búsqueda y filtro de objetos perdidos">
        
        <!-- Campo 1: Búsqueda por palabras (mayor espacio) -->
        <div class="flex-1 min-w-0">
            <label for="filtro_buscar" class="block text-xs font-bold text-slate-700 mb-1.5">
                Buscar por nombre o descripción
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    id="filtro_buscar"
                    name="buscar"
                    value="{{ request('buscar') }}"
                    placeholder="Buscar por nombre o descripción"
                    class="w-full pl-10 pr-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-slate-800 text-xs sm:text-sm placeholder-slate-400 focus:bg-white focus:border-tecnm-wine focus:ring-2 focus:ring-tecnm-wine/20 outline-none transition-all"
                />
            </div>
        </div>

        <!-- Campo 2: Selector de fecha con etiqueta visible «Fecha del hallazgo» -->
        <div class="w-full lg:w-56 shrink-0">
            <label for="filtro_fecha_hallazgo" class="block text-xs font-bold text-slate-700 mb-1.5">
                Fecha del hallazgo
            </label>
            <div class="relative">
                <input
                    type="date"
                    id="filtro_fecha_hallazgo"
                    name="fecha_hallazgo"
                    max="{{ \Carbon\Carbon::now(\App\Models\Publicacion::TIMEZONE)->toDateString() }}"
                    value="{{ request('fecha_hallazgo') }}"
                    class="w-full px-3.5 py-2.5 rounded-xl border {{ !empty($errorFecha) ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200 bg-slate-50/50' }} text-slate-800 text-xs sm:text-sm focus:bg-white focus:border-tecnm-wine focus:ring-2 focus:ring-tecnm-wine/20 outline-none transition-all"
                    aria-describedby="{{ !empty($errorFecha) ? 'error_fecha_hallazgo' : '' }}"
                />
            </div>
            @if (!empty($errorFecha))
                <p class="mt-1.5 text-xs text-rose-600 font-semibold flex items-center gap-1" role="alert" id="error_fecha_hallazgo">
                    <svg class="w-3.5 h-3.5 shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ $errorFecha }}</span>
                </p>
            @endif
        </div>

        <!-- Acciones: Botón «Buscar» (principal) y Opción «Limpiar filtros» (secundaria) -->
        <div class="flex items-center gap-2.5 shrink-0 pt-1 lg:pt-0">
            <button
                type="submit"
                class="flex-1 lg:flex-initial inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover active:scale-[0.98] text-white text-xs sm:text-sm font-bold shadow-xs transition-all focus:outline-none focus:ring-2 focus:ring-tecnm-wine focus:ring-offset-2 cursor-pointer min-h-[44px]"
            >
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <span>Buscar</span>
            </button>

            <a
                href="{{ route('muro') }}"
                class="flex-1 lg:flex-initial inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 cursor-pointer min-h-[44px]"
                title="Limpiar filtros y volver a la primera página sin criterios"
            >
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>Limpiar filtros</span>
            </a>
        </div>
    </form>
</div>

