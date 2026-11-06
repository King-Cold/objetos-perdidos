<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 border border-emerald-300/80 text-[10px] font-black uppercase tracking-wider text-emerald-900">
                        Panel Administrativo &bull; Biblioteca
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full bg-tecnm-wine/10 text-[10px] font-bold text-tecnm-wine">
                        HU-12 Verificación
                    </span>
                </div>
                <h2 class="font-black text-2xl text-slate-800 leading-tight tracking-tight">
                    Verificación de devoluciones
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Revisión de evidencia y comprobación de entrega física registradas por los autores.
                </p>
            </div>

            <!-- Contador de pendientes -->
            <div class="flex items-center gap-3">
                <div class="px-4 py-2 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full {{ $devoluciones->total() > 0 ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500' }}"></span>
                    <span class="text-xs font-medium text-slate-600">
                        Pendientes de verificación: <strong class="text-slate-900 font-extrabold">{{ $devoluciones->total() }}</strong>
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
                </div>
            @endif

            <!-- Pestañas de filtrado por estado (HU-13: acceso a devoluciones aprobadas para consultar historial) -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="flex items-center gap-1 p-1.5 bg-slate-50/80 border-b border-slate-100">
                    <a href="{{ route('admin.devoluciones.index', ['filtro' => 'pendientes']) }}"
                       class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ ($filtroEstado ?? 'pendientes') === 'pendientes' ? 'bg-white text-tecnm-wine border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        Pendientes
                    </a>
                    <a href="{{ route('admin.devoluciones.index', ['filtro' => 'aprobadas']) }}"
                       class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ ($filtroEstado ?? 'pendientes') === 'aprobadas' ? 'bg-white text-emerald-700 border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        Aprobadas
                    </a>
                    <a href="{{ route('admin.devoluciones.index', ['filtro' => 'todas']) }}"
                       class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ ($filtroEstado ?? 'pendientes') === 'todas' ? 'bg-white text-slate-800 border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        Todas
                    </a>
                </div>
            </div>

            <!-- Contenedor Principal: Tabla Administrativa / Estado Vacío -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-gradient-to-r from-slate-50/60 to-white">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            @if (($filtroEstado ?? 'pendientes') === 'aprobadas')
                                Devoluciones aprobadas
                            @elseif (($filtroEstado ?? 'pendientes') === 'todas')
                                Todas las devoluciones
                            @else
                                Cola de devoluciones pendientes de verificación
                            @endif
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Ordenadas cronológicamente desde el envío o último reenvío más antiguo para atención prioritaria.
                        </p>
                    </div>

                    @if ($devoluciones->total() > 0)
                        <div class="text-[11px] font-medium text-slate-500 bg-white px-3 py-1.5 rounded-xl border border-slate-200 shadow-xs">
                            Criterio: <strong class="text-slate-700">Envío más antiguo primero</strong>
                        </div>
                    @endif
                </div>

                @if ($devoluciones->count() === 0)
                    <!-- ESTADO VACÍO EXACTO -->
                    <div class="py-16 px-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-3xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shadow-xs">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h4 class="text-lg font-black text-slate-800 tracking-tight">
                            @if (($filtroEstado ?? 'pendientes') === 'aprobadas')
                                No hay devoluciones aprobadas
                            @elseif (($filtroEstado ?? 'pendientes') === 'todas')
                                No hay devoluciones registradas
                            @else
                                No hay devoluciones pendientes de verificación
                            @endif
                        </h4>
                        <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto leading-relaxed">
                            @if (($filtroEstado ?? 'pendientes') === 'aprobadas')
                                No existen devoluciones aprobadas hasta el momento.
                            @elseif (($filtroEstado ?? 'pendientes') === 'todas')
                                No existen registros de entrega física en el sistema.
                            @else
                                No existen registros de entrega física pendientes de resolución por parte del personal de biblioteca.
                            @endif
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
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200/80 bg-slate-50/75 text-[11px] font-black uppercase tracking-wider text-slate-500">
                                    <th scope="col" class="py-3.5 px-4 sm:px-6">Folio</th>
                                    <th scope="col" class="py-3.5 px-4">Objeto</th>
                                    <th scope="col" class="py-3.5 px-4">Autor original</th>
                                    <th scope="col" class="py-3.5 px-4">Envío a revisión</th>
                                    <th scope="col" class="py-3.5 px-4 text-center">Estado</th>
                                    <th scope="col" class="py-3.5 px-4 sm:px-6 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @foreach ($devoluciones as $dev)
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <!-- Folio -->
                                        <td class="py-4 px-4 sm:px-6 font-mono font-bold text-[#691C32]">
                                            {{ $dev->publicacion ? $dev->publicacion->folio : 'S/F' }}
                                        </td>

                                        <!-- Nombre del objeto -->
                                        <td class="py-4 px-4">
                                            <div class="font-extrabold text-slate-900 line-clamp-1">
                                                {{ $dev->publicacion ? ($dev->publicacion->nombre ?? $dev->publicacion->titulo) : 'Objeto desconocido' }}
                                            </div>
                                            <div class="text-[11px] text-slate-400 line-clamp-1 mt-0.5">
                                                Receptor: {{ $dev->receptor_nombre }}
                                            </div>
                                        </td>

                                        <!-- Autor original -->
                                        <td class="py-4 px-4">
                                            <div class="font-semibold text-slate-800">
                                                {{ $dev->publicacion && $dev->publicacion->user ? $dev->publicacion->user->name : ($dev->user ? $dev->user->name : 'No identificado') }}
                                            </div>
                                            @if ($dev->publicacion && $dev->publicacion->user)
                                                <div class="text-[11px] text-slate-400">
                                                    {{ $dev->publicacion->user->email }}
                                                </div>
                                            @endif
                                        </td>

                                        <!-- Fecha y hora del registro o último reenvío a revisión -->
                                        <td class="py-4 px-4 text-slate-600 font-medium">
                                            {{ $dev->fecha_revision_texto }}
                                        </td>

                                        <!-- Estado -->
                                        <td class="py-4 px-4 text-center">
                                            @php
                                                $estadoBg = match($dev->estado) {
                                                    'Verificada', 'Aprobada' => 'bg-emerald-50 text-emerald-900 border-emerald-300',
                                                    'Requiere corrección' => 'bg-orange-50 text-orange-900 border-orange-300',
                                                    default => 'bg-amber-50 text-amber-900 border-amber-300',
                                                };
                                                $estadoDot = match($dev->estado) {
                                                    'Verificada', 'Aprobada' => 'bg-emerald-500',
                                                    'Requiere corrección' => 'bg-orange-500',
                                                    default => 'bg-amber-500 animate-pulse',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider {{ $estadoBg }} border">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $estadoDot }}"></span>
                                                {{ $dev->estado }}
                                            </span>
                                        </td>

                                        <!-- Acción: Revisar devolución e Historial -->
                                        <td class="py-4 px-4 sm:px-6 text-right">
                                            <a href="{{ route('admin.devoluciones.show', $dev->id) }}"
                                               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-2xs">
                                                <span>Revisar devolución</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </a>
                                            <!-- Botón Ver historial del objeto (admin) -->
                                            @if($dev->publicacion)
                                                <a href="{{ route('admin.reportes.historial', $dev->publicacion->id) }}"
                                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 hover:border-[#691C32] hover:bg-rose-50/40 text-slate-700 hover:text-[#691C32] font-semibold text-xs transition-all shadow-2xs ml-2"
                                                   title="Ver historial del objeto {{ $dev->publicacion->folio ?? ('#' . $dev->publicacion->id) }} (admin)"
                                                >
                                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    <span>Historial</span>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($devoluciones->hasPages())
                        <div class="p-4 sm:p-6 border-t border-slate-100 bg-slate-50/50">
                            {{ $devoluciones->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

