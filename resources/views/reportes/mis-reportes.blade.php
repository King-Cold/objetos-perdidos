<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-black text-xl text-slate-800 leading-tight">
                Mis Reportes de Objetos
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Historial de objetos perdidos o encontrados registrados por tu cuenta
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-sm font-semibold text-emerald-800 flex items-center gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (isset($reportes) && $reportes->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach ($reportes as $rep)
                        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="px-2.5 py-1 rounded-full bg-slate-100 text-[11px] font-bold text-slate-700">
                                        {{ $rep->categoria }}
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $rep->estado === 'Aprobada' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $rep->estado }}
                                    </span>
                                </div>
                                <h3 class="text-base font-bold text-slate-900 mb-1">{{ $rep->titulo }}</h3>
                                <p class="text-xs text-slate-600 line-clamp-2 mb-4">{{ $rep->descripcion }}</p>
                            </div>
                            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                                <span class="text-[11px] text-slate-400">{{ $rep->created_at->format('d/m/Y') }}</span>
                                <a href="{{ route('reportes.edit', $rep->id) }}" class="text-xs font-bold text-tecnm-wine hover:underline">
                                    Editar reporte &rsaquo;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-3xl p-10 border border-slate-200 text-center max-w-md mx-auto">
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-800 mb-1">Aún no tienes reportes registrados</h3>
                    <p class="text-xs text-slate-500 mb-4">Cuando reportes un objeto perdido o encontrado aparecerá aquí.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

