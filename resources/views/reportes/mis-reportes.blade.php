<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-black text-xl text-slate-800 leading-tight">
                    Mis Reportes de Objetos
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Historial de objetos perdidos o encontrados registrados por tu cuenta
                </p>
            </div>
            <div>
                <a href="{{ route('reportes.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Encontré un objeto</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Confirmación visual SweetAlert2 al registrar nuevo reporte (Tarea 05.5) --}}
            @if (session('folio'))
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        if (typeof window.Swal !== 'undefined') {
                            window.Swal.fire({
                                icon: 'success',
                                title: 'Reporte Enviado Correctamente',
                                html: `
                                    <div class="text-center space-y-3">
                                        <p class="text-sm text-slate-600">
                                            Tu reporte de hallazgo ha sido registrado con éxito.
                                        </p>
                                        <div class="p-3 bg-amber-50 rounded-2xl border border-amber-200">
                                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Folio único asignado</span>
                                            <span class="text-lg font-black text-[#691C32] tracking-wider">{{ session('folio') }}</span>
                                        </div>
                                        <p class="text-xs font-semibold text-amber-800">
                                            Estado: <span class="underline">Pendiente de aprobación</span>
                                        </p>
                                        <p class="text-xs text-slate-500 leading-relaxed">
                                            Personal de Biblioteca verificará los datos y el resguardo físico antes de su publicación en el muro institucional.
                                        </p>
                                    </div>
                                `,
                                confirmButtonText: 'Entendido',
                                buttonsStyling: false,
                                customClass: {
                                    confirmButton: 'inline-flex items-center justify-center px-8 py-2.5 rounded-full font-bold text-xs tracking-wide text-white bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] shadow-md transition-all cursor-pointer'
                                }
                            });
                        }
                    });
                </script>
            @endif

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
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-[11px] font-bold text-slate-700">
                                            {{ $rep->categoria ?? 'General' }}
                                        </span>
                                        @if ($rep->folio)
                                            <span class="px-2 py-0.5 rounded-md bg-amber-50 border border-amber-200/70 text-[11px] font-black text-[#691C32] tracking-wider">
                                                {{ $rep->folio }}
                                            </span>
                                        @endif
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $rep->estado === 'Aprobada' ? 'bg-emerald-100 text-emerald-800' : ($rep->estado === 'Pendiente de aprobación' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800') }}">
                                        {{ $rep->estado }}
                                    </span>
                                </div>
                                <h3 class="text-base font-bold text-slate-900 mb-1">{{ $rep->titulo ?? $rep->nombre }}</h3>
                                <p class="text-xs text-slate-600 line-clamp-2 mb-3">{{ $rep->descripcion }}</p>

                                <!-- Información básica del hallazgo (Tarea 05.5) -->
                                <div class="space-y-1 mb-3 text-[11px] text-slate-500">
                                    @if ($rep->fecha_hallazgo)
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <span>Encontrado: {{ $rep->fecha_hallazgo->format('d/m/Y') }} {{ $rep->hora_hallazgo ? 'a las ' . substr($rep->hora_hallazgo, 0, 5) : '' }}</span>
                                        </div>
                                    @endif
                                    @if ($rep->area_zona || $rep->ubicacion_especifica)
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span class="truncate">{{ $rep->area_zona }}{{ $rep->ubicacion_especifica ? ' - ' . $rep->ubicacion_especifica : '' }}</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Fotografías asociadas con acceso privado y controlado (Tarea 05.5) -->
                                @if ($rep->fotografias && $rep->fotografias->isNotEmpty())
                                    <div class="mt-2.5 pt-2 border-t border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">
                                            Fotografías ({{ $rep->fotografias->count() }})
                                        </span>
                                        <div class="flex items-center gap-2 overflow-x-auto py-1">
                                            @foreach ($rep->fotografias as $foto)
                                                <img
                                                    src="{{ $foto->url }}"
                                                    alt="{{ $foto->nombre_original ?? 'Foto del objeto' }}"
                                                    class="w-14 h-14 rounded-lg object-cover border border-slate-200 shrink-0 bg-slate-50"
                                                    loading="lazy"
                                                />
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="pt-3 border-t border-slate-100 flex items-center justify-between mt-3">
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
                    <p class="text-xs text-slate-500 mb-5">Cuando reportes un objeto perdido o encontrado aparecerá aquí.</p>
                    <a href="{{ route('reportes.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Encontré un objeto</span>
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
