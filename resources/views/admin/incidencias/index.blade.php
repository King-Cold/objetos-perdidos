<x-app-layout>
    <div class="py-8 sm:py-10 bg-[#F8F9FA] min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Encabezado institucional --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#FDF8E7] border border-[#E9D8A6] text-xs font-bold text-[#7A5C00] uppercase tracking-wider mb-2">
                        Panel Administrativo • Biblioteca
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        Incidencias y dudas de entrega
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Registro, consulta y seguimiento de incidencias vinculadas a reportes con estado «Devuelto» y sus eventos de devolución.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 shadow-sm text-xs font-bold text-slate-700">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                        Activas: <span class="text-slate-900">{{ $totalActivas }}</span>
                    </div>
                    <a href="{{ route('admin.incidencias.create') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover active:bg-tecnm-wine-dark text-white text-xs font-bold shadow hover:shadow-md active:translate-y-px transition cursor-pointer">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>Abrir incidencia</span>
                    </a>
                </div>
            </div>

            {{-- Alertas de sesión --}}
            @if (session('status'))
                <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-900 font-medium flex items-center gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-900 font-medium flex items-center gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"></path>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            {{-- Pestañas de filtrado por estado (Igual a Moderación y Devoluciones) --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="flex items-center gap-1.5 p-1.5 bg-slate-50/80 border-b border-slate-100 flex-wrap">
                    {{-- 1. Todas --}}
                    @php
                        $esTodas = ($filtroEstado === '' || $filtroEstado === 'todas');
                    @endphp
                    <a href="{{ route('admin.incidencias.index', array_filter(['q' => $busqueda])) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2 {{ $esTodas ? 'bg-white text-tecnm-wine border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        <span>Todas</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $esTodas ? 'bg-tecnm-wine/10 text-tecnm-wine' : 'bg-slate-200/80 text-slate-600' }}">
                            {{ $contadores['todas'] ?? $totalIncidencias }}
                        </span>
                    </a>

                    {{-- 2. Abiertas --}}
                    @php
                        $esAbierta = ($filtroEstado === \App\Models\Incidencia::ESTADO_ABIERTA);
                    @endphp
                    <a href="{{ route('admin.incidencias.index', array_filter(['estado' => \App\Models\Incidencia::ESTADO_ABIERTA, 'q' => $busqueda])) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2 {{ $esAbierta ? 'bg-white text-tecnm-wine border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        <span>Abiertas</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $esAbierta ? 'bg-tecnm-wine/10 text-tecnm-wine' : 'bg-slate-200/80 text-slate-600' }}">
                            {{ $contadores['abiertas'] ?? 0 }}
                        </span>
                    </a>

                    {{-- 3. En espera de corrección --}}
                    @php
                        $esCorreccion = ($filtroEstado === \App\Models\Incidencia::ESTADO_ESPERA_CORRECCION);
                    @endphp
                    <a href="{{ route('admin.incidencias.index', array_filter(['estado' => \App\Models\Incidencia::ESTADO_ESPERA_CORRECCION, 'q' => $busqueda])) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2 {{ $esCorreccion ? 'bg-white text-tecnm-wine border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        <span>En espera de corrección</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $esCorreccion ? 'bg-tecnm-wine/10 text-tecnm-wine' : 'bg-slate-200/80 text-slate-600' }}">
                            {{ $contadores['correccion'] ?? 0 }}
                        </span>
                    </a>

                    {{-- 4. En espera de recuperación --}}
                    @php
                        $esRecuperacion = ($filtroEstado === \App\Models\Incidencia::ESTADO_ESPERA_RECUPERACION);
                    @endphp
                    <a href="{{ route('admin.incidencias.index', array_filter(['estado' => \App\Models\Incidencia::ESTADO_ESPERA_RECUPERACION, 'q' => $busqueda])) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2 {{ $esRecuperacion ? 'bg-white text-tecnm-wine border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        <span>En espera de recuperación</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $esRecuperacion ? 'bg-tecnm-wine/10 text-tecnm-wine' : 'bg-slate-200/80 text-slate-600' }}">
                            {{ $contadores['recuperacion'] ?? 0 }}
                        </span>
                    </a>

                    {{-- 5. Sin resolver y canalizada --}}
                    @php
                        $esCanalizada = ($filtroEstado === \App\Models\Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA);
                    @endphp
                    <a href="{{ route('admin.incidencias.index', array_filter(['estado' => \App\Models\Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA, 'q' => $busqueda])) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2 {{ $esCanalizada ? 'bg-white text-tecnm-wine border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        <span>Sin resolver y canalizada</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $esCanalizada ? 'bg-tecnm-wine/10 text-tecnm-wine' : 'bg-slate-200/80 text-slate-600' }}">
                            {{ $contadores['canalizadas'] ?? 0 }}
                        </span>
                    </a>

                    {{-- 6. Cerradas --}}
                    @php
                        $esCerrada = ($filtroEstado === \App\Models\Incidencia::ESTADO_CERRADA);
                    @endphp
                    <a href="{{ route('admin.incidencias.index', array_filter(['estado' => \App\Models\Incidencia::ESTADO_CERRADA, 'q' => $busqueda])) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2 {{ $esCerrada ? 'bg-white text-tecnm-wine border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        <span>Cerradas</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $esCerrada ? 'bg-tecnm-wine/10 text-tecnm-wine' : 'bg-slate-200/80 text-slate-600' }}">
                            {{ $contadores['cerradas'] ?? $totalCerradas }}
                        </span>
                    </a>
                </div>
            </div>

            {{-- Listado administrativo de incidencias --}}
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-gradient-to-r from-slate-50/60 to-white">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            Listado administrativo de incidencias registradas
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Los objetos con incidencia activa permanecen fuera del muro público mientras se aclara la entrega.
                        </p>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <form method="GET" action="{{ route('admin.incidencias.index') }}" class="flex items-center gap-2">
                            @if ($filtroEstado !== '')
                                <input type="hidden" name="estado" value="{{ $filtroEstado }}">
                            @endif
                            <div class="relative">
                                <input type="text"
                                       name="q"
                                       value="{{ $busqueda }}"
                                       placeholder="Buscar por folio, objeto o motivo..."
                                       class="w-full sm:w-60 pl-8 pr-3 py-1.5 rounded-xl border border-slate-300 text-xs focus:border-tecnm-wine focus:ring-tecnm-wine shadow-2xs">
                                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <button type="submit"
                                    class="px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shadow-xs transition cursor-pointer">
                                Buscar
                            </button>
                            @if ($busqueda !== '')
                                <a href="{{ route('admin.incidencias.index', $filtroEstado ? ['estado' => $filtroEstado] : []) }}"
                                   class="px-2.5 py-1.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-600 hover:text-rose-700 text-xs font-bold shadow-2xs transition"
                                   title="Limpiar búsqueda">
                                    ✕
                                </a>
                            @endif
                        </form>
                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold">
                            Registros mostrados: {{ $incidencias->count() }}
                        </span>
                    </div>
                </div>

                @if ($incidencias->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-500 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800">No se encontraron incidencias registradas</h3>
                        <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                            Cuando exista alguna duda relacionada con la entrega de un objeto con estado «Devuelto», podrás abrir una incidencia para darle seguimiento sin ofrecer el objeto en el muro.
                        </p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-slate-50/70 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3.5">Folio del reporte</th>
                                    <th class="px-6 py-3.5">Objeto</th>
                                    <th class="px-6 py-3.5">Motivo de apertura</th>
                                    <th class="px-6 py-3.5">Fecha de apertura</th>
                                    <th class="px-6 py-3.5">Estado</th>
                                    <th class="px-6 py-3.5 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach ($incidencias as $incidencia)
                                    @php
                                        $pub = $incidencia->publicacion;
                                    @endphp
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-3 py-1 rounded-lg bg-[#FDF8E7] border border-[#E9D8A6] text-xs font-extrabold text-[#4E0B1B]">
                                                {{ $pub?->folio ?? 'SIN-FOLIO' }}
                                            </span>
                                            @if ($incidencia->devolucion_id)
                                                <div class="text-[11px] text-slate-500 mt-1 font-medium">
                                                    Vinculada a devolución #{{ $incidencia->devolucion_id }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-slate-900">
                                                {{ $pub?->nombre ?? $pub?->titulo ?? 'Objeto reportado' }}
                                            </div>
                                            <div class="text-xs text-slate-500 mt-0.5">
                                                Disponibilidad: <span class="font-semibold text-rose-700">{{ $pub && $pub->isDisponible() ? 'Disponible en el muro' : 'Fuera del muro' }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 max-w-md">
                                            <p class="text-xs text-slate-700 line-clamp-2 font-medium">
                                                {{ $incidencia->motivo }}
                                            </p>
                                            <p class="text-[11px] text-slate-400 mt-1">
                                                Responsable: {{ $incidencia->user?->name ?? 'Administrador' }}
                                            </p>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">
                                                {{ $incidencia->fecha_apertura_texto }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if ($incidencia->estado === \App\Models\Incidencia::ESTADO_CERRADA)
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    {{ $incidencia->estado }}
                                                </span>
                                            @elseif ($incidencia->estado === \App\Models\Incidencia::ESTADO_ESPERA_CORRECCION)
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                    {{ $incidencia->estado }}
                                                </span>
                                            @elseif ($incidencia->estado === \App\Models\Incidencia::ESTADO_ESPERA_RECUPERACION)
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-orange-50 border border-orange-200 text-orange-800 text-xs font-bold">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                                                    {{ $incidencia->estado }}
                                                </span>
                                            @elseif ($incidencia->estado === \App\Models\Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA)
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 border border-slate-300 text-slate-800 text-xs font-bold">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-600"></span>
                                                    {{ $incidencia->estado }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                    {{ $incidencia->estado }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <div class="inline-flex items-center gap-2">
                                                @if ($pub)
                                                    <a href="{{ route('admin.reportes.historial', $pub->id) }}"
                                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition">
                                                        Historial
                                                    </a>
                                                @endif
                                                <a href="{{ route('admin.incidencias.show', $incidencia->id) }}"
                                                   class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-[#4E0B1B] hover:bg-[#3A0814] text-white text-xs font-bold shadow-sm transition">
                                                    Ver detalle
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
