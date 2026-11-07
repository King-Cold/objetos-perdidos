<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-rose-50 border border-rose-100 text-[#691C32] flex items-center justify-center shrink-0 shadow-2xs">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-black text-lg sm:text-xl text-slate-900 leading-tight">
                            Avisos
                        </h2>
                        @if ($avisos->total() > 0)
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $avisos->total() }} {{ $avisos->total() === 1 ? 'aviso' : 'avisos' }}
                            </span>
                        @endif
                        @if (isset($noLeidosCount) && $noLeidosCount > 0)
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-black bg-rose-100 text-rose-800 border border-rose-200">
                                {{ $noLeidosCount }} {{ $noLeidosCount === 1 ? 'pendiente' : 'pendientes' }}
                            </span>
                        @endif
                    </div>
                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium">
                        Consulta las decisiones administrativas y avisos sobre tus reportes y procesos de entrega
                    </p>
                </div>
            </div>

            {{-- Opción para marcar todos los avisos pendientes como leídos (Task 2) --}}
            @if (isset($noLeidosCount) && $noLeidosCount > 0)
                <div class="flex items-center sm:self-center">
                    <form method="POST" action="{{ route('avisos.marcar-todos-leidos') }}">
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-300 hover:border-tecnm-wine/40 text-slate-700 hover:text-slate-900 text-xs font-bold transition-all shadow-2xs hover:shadow-xs active:scale-[0.98] cursor-pointer"
                            title="Marcar todos los avisos pendientes como leídos"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Marcar todos como leídos</span>
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-4 sm:py-6 bg-slate-50/60 min-h-[calc(100vh-140px)]">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Mensajes de estado y retroalimentación --}}
            @if (session('status'))
                <div class="mb-4 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('info'))
                <div class="mb-4 p-3.5 rounded-xl bg-sky-50 border border-sky-200 text-xs text-sky-800 font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4 text-sky-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('info') }}</span>
                </div>
            @endif

            @if (session('warning'))
                <div class="mb-4 p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800 font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>{{ session('warning') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($avisos->count() > 0)
                <div class="space-y-3.5 sm:space-y-4">
                    @foreach ($avisos as $aviso)
                        <div class="bg-white rounded-2xl border p-4 sm:p-5 shadow-xs transition-all flex flex-col justify-between {{ ! $aviso->leido ? 'border-slate-300 border-l-4 border-l-[#691C32] ring-1 ring-rose-100/50' : 'border-slate-200 border-l-4 border-l-slate-300 opacity-95' }}">
                            <div>
                                {{-- Fila 1: Tipo de evento, Folio y Estado de lectura --}}
                                <div class="flex flex-wrap items-center justify-between pb-2.5 border-b border-slate-100 gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $aviso->color_badge }}">
                                            {{ $aviso->tipo_etiqueta }}
                                        </span>

                                        @if ($aviso->folio_reporte)
                                            <span class="text-xs font-black text-slate-800 tracking-tight" title="Folio del reporte">
                                                {{ $aviso->folio_reporte }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-2">
                                        @if (! $aviso->leido)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-black bg-rose-50 text-rose-700 border border-rose-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-600 animate-pulse"></span>
                                                No leído
                                            </span>

                                            {{-- Acción individual: Marcar como leído (Task 2) --}}
                                            <form method="POST" action="{{ route('avisos.marcar-leido', $aviso->id) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold text-slate-600 hover:text-[#691C32] bg-slate-100 hover:bg-rose-50 border border-slate-200 hover:border-rose-200 transition-all cursor-pointer"
                                                    title="Marcar este aviso como leído"
                                                >
                                                    <svg class="w-3 h-3 text-slate-400 group-hover:text-[#691C32]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span>Marcar como leído</span>
                                                </button>
                                            </form>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                                <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                                Leído
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Fila 2: Título y Mensaje descriptivo --}}
                                <div class="pt-2.5">
                                    <h3 class="font-black text-sm sm:text-base text-slate-900 tracking-tight">
                                        {{ $aviso->titulo }}
                                    </h3>
                                    <p class="text-xs sm:text-sm text-slate-600 mt-1 leading-relaxed">
                                        {{ $aviso->mensaje }}
                                    </p>
                                </div>

                                {{-- Fila 3: Motivo autorizado (cuando aplique) --}}
                                @if ($aviso->motivo_autorizado)
                                    <div class="mt-3 p-3 sm:p-3.5 rounded-xl bg-amber-50/70 border border-amber-200/80 text-xs text-amber-950 space-y-1">
                                        <span class="font-extrabold flex items-center gap-1.5 text-amber-900">
                                            <svg class="w-3.5 h-3.5 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Motivo u observaciones autorizadas:
                                        </span>
                                        <p class="text-amber-900/90 leading-relaxed pl-5 font-medium">
                                            {{ $aviso->motivo_autorizado }}
                                        </p>
                                    </div>
                                @endif
                            </div>

                            {{-- Fila 4: Pie de tarjeta con Fecha/Hora y Acceso seguro al proceso --}}
                            <div class="pt-3 mt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
                                <div class="text-[11px] sm:text-xs text-slate-400 font-medium flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>{{ $aviso->fecha_formateada }}</span>
                                </div>

                                @if ($aviso->enlace_destino)
                                    <div class="flex items-center justify-end">
                                        <a
                                            href="{{ route('avisos.ir', $aviso->id) }}"
                                            data-enlace="{{ $aviso->enlace_destino }}"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-white hover:bg-slate-100 border border-slate-300 hover:border-[#691C32] text-slate-700 hover:text-[#691C32] text-xs font-bold transition-all shadow-2xs"
                                        >
                                            <span>{{ $aviso->texto_accion }}</span>
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    {{-- Paginación --}}
                    @if ($avisos->hasPages())
                        <div class="pt-4">
                            {{ $avisos->links() }}
                        </div>
                    @endif
                </div>
            @else
                {{-- Estado Vacío Estándar --}}
                <div class="bg-white rounded-3xl p-10 sm:p-12 border border-slate-200 text-center max-w-md mx-auto shadow-sm my-6 sm:my-10">
                    <div class="w-14 h-14 rounded-2xl bg-rose-50 text-[#691C32] border border-rose-100 flex items-center justify-center mx-auto mb-4 shadow-2xs">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <h3 class="text-base font-black text-slate-800 mb-1.5 tracking-tight">
                        No tienes avisos
                    </h3>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Aquí recibirás notificaciones sobre la aprobación, correcciones o cancelaciones de tus reportes y entregas de objetos.
                    </p>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
