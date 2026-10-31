<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full bg-amber-50 border border-amber-300/80 text-[10px] font-black uppercase tracking-wider text-amber-900">
                        Panel Administrativo &bull; Biblioteca
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full bg-tecnm-wine/10 text-[10px] font-bold text-tecnm-wine">
                        HU-07 Moderación
                    </span>
                </div>
                <h2 class="font-black text-2xl text-slate-800 leading-tight tracking-tight">
                    Moderación de reportes
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Revisión y autorización de reportes de objetos encontrados antes de su publicación en el muro institucional.
                </p>
            </div>

            <!-- Contador de pendientes -->
            <div class="flex items-center gap-3">
                <div class="px-4 py-2 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full {{ $pendientes->total() > 0 ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500' }}"></span>
                    <span class="text-xs font-medium text-slate-600">
                        Total pendientes: <strong class="text-slate-900 font-extrabold">{{ $pendientes->total() }}</strong>
                    </span>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Avisos de Estado (si aplica) -->
            @if (session('status'))
                <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-sm font-semibold text-emerald-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-xs">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                    @php
                        $targetReporteId = session('reporte_aprobado_id') ?? session('reporte_corregido_id');
                    @endphp
                    @if ($targetReporteId)
                        <a href="{{ route('admin.moderacion.show', $targetReporteId) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-emerald-300 text-xs font-bold text-emerald-900 hover:bg-emerald-100 transition-colors shadow-2xs self-start sm:self-auto cursor-pointer">
                            <span>Ver detalle del reporte</span>
                            <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    @endif
                </div>
            @endif

            <!-- Contenedor Principal: Tabla Administrativa / Estado Vacío -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-gradient-to-r from-slate-50/60 to-white">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Lista de reportes pendientes de revisión
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Ordenados cronológicamente desde el envío a revisión más antiguo para garantizar atención prioritaria.
                        </p>
                    </div>

                    @if ($pendientes->total() > 0)
                        <div class="text-[11px] font-medium text-slate-500 bg-white px-3 py-1.5 rounded-xl border border-slate-200 shadow-xs">
                            Criterio: <strong class="text-slate-700">Envío más antiguo primero</strong>
                        </div>
                    @endif
                </div>

                @if ($pendientes->count() === 0)
                    <!-- ESTADO VACÍO EXACTO (Requerimiento 8) -->
                    <div class="py-16 px-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-3xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shadow-xs">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h4 class="text-lg font-black text-slate-800 tracking-tight">
                            No hay reportes pendientes de revisión
                        </h4>
                        <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto leading-relaxed">
                            Todos los reportes de hallazgos han sido moderados y se encuentran debidamente autorizados o devueltos.
                        </p>
                        <div class="mt-5">
                            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                <span>Volver al panel principal</span>
                            </a>
                        </div>
                    </div>
                @else
                    <!-- TABLA RESPONSIVA DE PENDIENTES (Requerimientos 3, 4, 5, 6, 7, 9) -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-600 border-b border-slate-200/80">
                                    <th scope="col" class="py-3.5 px-4 sm:px-6">Folio del reporte</th>
                                    <th scope="col" class="py-3.5 px-4">Nombre del objeto</th>
                                    <th scope="col" class="py-3.5 px-4">Autor</th>
                                    <th scope="col" class="py-3.5 px-4">Fecha de captura</th>
                                    <th scope="col" class="py-3.5 px-4">Fecha y hora del último envío a revisión</th>
                                    <th scope="col" class="py-3.5 px-4 sm:px-6 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                                @foreach ($pendientes as $rep)
                                    @php
                                        // Formateo y diferenciación clara de fechas (Requerimiento 6 y 7)
                                        $fechaCapturaTexto = $rep->fecha_captura 
                                            ? $rep->fecha_captura->format('d/m/Y H:i') 
                                            : ($rep->created_at ? $rep->created_at->format('d/m/Y H:i') : '-');

                                        $fechaEnvioTexto = $rep->fecha_envio_revision 
                                            ? $rep->fecha_envio_revision->format('d/m/Y H:i') 
                                            : $fechaCapturaTexto;
                                    @endphp
                                    <tr class="hover:bg-amber-50/30 transition-colors group">
                                        <!-- 1. Folio del reporte -->
                                        <td class="py-4 px-4 sm:px-6 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-amber-50/80 border border-amber-200/80 font-mono text-[11px] font-black text-[#691C32] tracking-wider shadow-2xs">
                                                    {{ $rep->folio ?? ('#' . $rep->id) }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- 2. Nombre del objeto -->
                                        <td class="py-4 px-4">
                                            <div class="font-bold text-slate-900 group-hover:text-tecnm-wine transition-colors max-w-xs truncate" title="{{ $rep->nombre ?? $rep->titulo }}">
                                                {{ $rep->nombre ?? $rep->titulo }}
                                            </div>
                                            @if ($rep->categoria)
                                                <span class="text-[10px] font-semibold text-slate-400">
                                                    {{ $rep->categoria }}
                                                </span>
                                            @endif
                                        </td>

                                        <!-- 3. Autor -->
                                        <td class="py-4 px-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-slate-100 border border-slate-200 text-slate-600 flex items-center justify-center text-[10px] font-bold shrink-0">
                                                    {{ strtoupper(substr($rep->user->name ?? 'U', 0, 1)) }}
                                                </div>
                                                <div class="truncate max-w-[160px]">
                                                    <span class="font-semibold text-slate-800 block truncate">
                                                        {{ $rep->user->name ?? 'Usuario no disponible' }}
                                                    </span>
                                                    <span class="text-[10px] text-slate-400 block truncate">
                                                        {{ $rep->user->email ?? '' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 4. Fecha de captura (original) -->
                                        <td class="py-4 px-4 whitespace-nowrap text-slate-600">
                                            <div class="flex items-center gap-1.5 font-medium">
                                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>{{ $fechaCapturaTexto }}</span>
                                            </div>
                                        </td>

                                        <!-- 5. Fecha y hora del último envío a revisión -->
                                        <td class="py-4 px-4 whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-50 border border-slate-200/80 font-semibold text-slate-800">
                                                <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>{{ $fechaEnvioTexto }}</span>
                                            </div>
                                        </td>

                                        <!-- 6. Acción «Revisar» (Requerimiento 9) -->
                                        <td class="py-4 px-4 sm:px-6 whitespace-nowrap text-right">
                                            <a
                                                href="{{ route('admin.moderacion.show', $rep->id) }}"
                                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover active:bg-[#430918] text-white font-bold text-xs tracking-wide shadow-xs hover:shadow transition-all focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-tecnm-gold cursor-pointer"
                                                title="Revisar reporte {{ $rep->folio ?? ('#' . $rep->id) }}"
                                            >
                                                <svg class="w-3.5 h-3.5 text-amber-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <span>Revisar</span>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    @if ($pendientes->hasPages())
                        <div class="p-4 sm:p-5 border-t border-slate-100 bg-slate-50/50">
                            {{ $pendientes->links() }}
                        </div>
                    @endif
                @endif
            </div>

        </div>
    </div>
</x-app-layout>

