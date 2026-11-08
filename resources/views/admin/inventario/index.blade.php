<x-app-layout>
    <div class="min-h-screen bg-[#F5F6F8] py-8 text-[#1F2937]">
        <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- 1. ENCABEZADO PRINCIPAL -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <h1 class="text-2xl sm:text-[26px] font-bold text-[#1F2937] tracking-tight leading-tight">
                        Inventario de biblioteca
                    </h1>
                    <p class="text-xs sm:text-[13px] font-medium text-[#64748B] mt-1">
                        Control físico, ubicaciones internas y plazos de resguardo de objetos no reclamados
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0 flex-wrap">
                    @if (isset($pendientesRecepcion) && $pendientesRecepcion->isNotEmpty())
                        <a href="#pendientes-recepcion" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-50 border border-amber-200 text-amber-900 hover:bg-amber-100 transition-colors shadow-2xs">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            <span>Pendientes de recepción: <strong>{{ $pendientesRecepcion->count() }}</strong></span>
                        </a>
                    @endif

                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-white border border-slate-200 shadow-2xs text-slate-700">
                        <span class="w-2 h-2 rounded-full bg-[#691C32]"></span>
                        <span>Total en resguardo: <strong>{{ $recepciones->total() ?? $recepciones->count() }}</strong></span>
                    </span>
                </div>
            </div>

            <!-- 2. MENSAJES DE ESTADO -->
            @if (session('status'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-start gap-3 shadow-xs" role="alert">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-xs sm:text-sm font-semibold">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            <!-- 2.1 SECCIÓN DESTACADA: PUBLICACIONES VENCIDAS PENDIENTES DE RECEPCIÓN FÍSICA -->
            @if (isset($pendientesRecepcion) && $pendientesRecepcion->isNotEmpty())
                <div id="pendientes-recepcion" class="bg-gradient-to-br from-amber-50/80 via-white to-amber-50/40 border-2 border-amber-300/80 rounded-2xl p-5 shadow-xs space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-amber-200/70 pb-3">
                        <div class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-xl bg-[#691C32] text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2">
                                    <span>Publicaciones vencidas pendientes de entrega física</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-200 text-amber-950 border border-amber-300">
                                        {{ $pendientesRecepcion->count() }} {{ $pendientesRecepcion->count() === 1 ? 'objeto' : 'objetos' }}
                                    </span>
                                </h2>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    Estos reportes cumplieron sus 14 días sin reclamo. Pulsa <strong>«Registrar recepción»</strong> para verificar el objeto físicamente e integrarlo al inventario.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                        @foreach ($pendientesRecepcion as $pendiente)
                            @php
                                $foto = $pendiente->foto_principal ?? $pendiente->fotografias->first();
                            @endphp
                            <div class="bg-white rounded-xl border border-amber-200/90 p-4 shadow-2xs flex flex-col justify-between gap-3 hover:border-[#691C32]/40 transition-all">
                                <div class="flex items-start gap-3">
                                    <div class="w-12 h-12 rounded-lg overflow-hidden bg-slate-100 border border-slate-200 shrink-0 flex items-center justify-center">
                                        @if ($foto)
                                            <img src="{{ route('fotografias.show', $foto->id) }}"
                                                 alt="{{ $pendiente->nombre ?? $pendiente->titulo }}"
                                                 class="w-full h-full object-cover" />
                                        @else
                                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-mono text-[11px] font-bold text-[#691C32] truncate">
                                                {{ $pendiente->folio }}
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 shrink-0">
                                                Venció el {{ $pendiente->fecha_vencimiento ? $pendiente->fecha_vencimiento->format('d/m/Y') : '' }}
                                            </span>
                                        </div>
                                        <h3 class="font-bold text-slate-900 text-xs sm:text-sm truncate mt-0.5" title="{{ $pendiente->nombre ?? $pendiente->titulo }}">
                                            {{ $pendiente->nombre ?? $pendiente->titulo }}
                                        </h3>
                                        <span class="text-[11px] text-slate-500 block truncate">
                                            Autor: {{ $pendiente->user->name ?? 'Usuario' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="pt-2.5 border-t border-slate-100 flex flex-col gap-1.5">
                                    <a href="{{ route('admin.recepciones.create', $pendiente->id) }}"
                                       class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#691C32] hover:bg-[#520B1D] text-white text-xs font-bold shadow-2xs transition-all">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span>Registrar recepción</span>
                                    </a>

                                    @if ($pendiente->puedeRegistrarDevolucionExtemporanea())
                                        <a href="{{ route('admin.devoluciones-extemporaneas.create', $pendiente->id) }}"
                                           class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-800 hover:bg-amber-900 text-white text-[11px] font-bold shadow-2xs transition-all">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>Registrar devolución reportada fuera de tiempo</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- 3. LISTADO / TABLA DEL INVENTARIO -->
            @if ($recepciones->isEmpty())
                <!-- ESTADO VACÍO -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-12 text-center space-y-3">
                    <div class="w-14 h-14 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">
                        No hay objetos bajo resguardo en biblioteca
                    </h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">
                        Actualmente no se encuentran objetos físicos registrados en el resguardo institucional de biblioteca.
                    </p>
                </div>
            @else
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <!-- Vista de tabla para escritorio -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse table-auto">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3">Folio</th>
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3">Objeto</th>
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3">Recepción física</th>
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3">Condición</th>
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3">Ubicación interna</th>
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3">Límite de resguardo</th>
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3">Estado</th>
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @foreach ($recepciones as $rec)
                                    @php
                                        $pub = $rec->publicacion;
                                        $foto = $pub?->foto_principal ?? $pub?->fotografias?->first();
                                    @endphp
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <!-- Folio -->
                                        <td class="py-2.5 px-2.5 sm:px-3 font-mono font-bold text-[#691C32] whitespace-nowrap text-xs">
                                            {{ $pub->folio ?? 'N/A' }}
                                        </td>

                                        <!-- Objeto con imagen de referencia -->
                                        <td class="py-2.5 px-2.5 sm:px-3">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-lg overflow-hidden bg-slate-100 border border-slate-200 shrink-0 flex items-center justify-center">
                                                    @if ($foto)
                                                        <img src="{{ route('fotografias.show', $foto->id) }}"
                                                             alt="{{ $pub->nombre ?? $pub->titulo }}"
                                                             class="w-full h-full object-cover" />
                                                    @else
                                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    @endif
                                                </div>
                                                <div class="min-w-0 max-w-[140px] lg:max-w-[180px]">
                                                    <span class="font-bold text-slate-900 block truncate" title="{{ $pub->nombre ?? $pub->titulo }}">
                                                        {{ $pub->nombre ?? $pub->titulo }}
                                                    </span>
                                                    <span class="text-[10px] text-slate-400 block truncate">
                                                        Autor: {{ $pub->user->name ?? 'Usuario' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Fecha de recepción física -->
                                        <td class="py-2.5 px-2.5 sm:px-3 whitespace-nowrap">
                                            <span class="font-semibold text-slate-800 block text-xs">
                                                {{ $rec->fecha_recepcion ? $rec->fecha_recepcion->format('d/m/Y') : 'No especificada' }}
                                            </span>
                                            @if ($rec->hora_recepcion)
                                                <span class="text-[10px] text-slate-400 block">{{ substr($rec->hora_recepcion, 0, 5) }} hrs</span>
                                            @endif
                                        </td>

                                        <!-- Condición -->
                                        <td class="py-2.5 px-2.5 sm:px-3 max-w-[120px] lg:max-w-[160px]">
                                            <span class="text-slate-700 block truncate text-xs" title="{{ $rec->condicion_objeto }}">
                                                {{ $rec->condicion_objeto }}
                                            </span>
                                        </td>

                                        <!-- Ubicación interna -->
                                        <td class="py-2.5 px-2.5 sm:px-3 font-medium text-slate-800">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200 text-xs">
                                                <svg class="w-3 h-3 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                </svg>
                                                <span class="truncate max-w-[110px] lg:max-w-[140px]" title="{{ $rec->ubicacion_resguardo }}">{{ $rec->ubicacion_resguardo }}</span>
                                            </span>
                                        </td>

                                        <!-- Fecha límite de resguardo -->
                                        <td class="py-2.5 px-2.5 sm:px-3 whitespace-nowrap">
                                            <span class="font-semibold text-xs {{ $rec->isAnualVencido() ? 'text-rose-700' : 'text-slate-800' }}">
                                                {{ $rec->fecha_limite_anual ? $rec->fecha_limite_anual->format('d/m/Y') : '1 año' }}
                                            </span>
                                            @if ($rec->isAnualVencido())
                                                <span class="text-[10px] text-rose-600 block">(Vencido)</span>
                                            @endif
                                        </td>

                                        <!-- Estado del resguardo -->
                                        <td class="py-2.5 px-2.5 sm:px-3 whitespace-nowrap">
                                            @if ($rec->isAnualVencido())
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-900 border border-rose-300">
                                                    Resguardo vencido
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-300">
                                                    Bajo resguardo
                                                </span>
                                            @endif
                                        </td>

                                        <!-- Acción Ver detalle -->
                                        <td class="py-2.5 px-2.5 sm:px-3 text-right whitespace-nowrap">
                                            <a href="{{ route('admin.inventario.show', $rec->id) }}"
                                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 hover:text-[#691C32] font-bold text-xs shadow-2xs transition-all">
                                                <span>Ver detalle</span>
                                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Vista de tarjetas para dispositivos móviles -->
                    <div class="md:hidden divide-y divide-slate-100">
                        @foreach ($recepciones as $rec)
                            @php
                                $pub = $rec->publicacion;
                                $foto = $pub?->foto_principal ?? $pub?->fotografias?->first();
                            @endphp
                            <div class="p-4 space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg overflow-hidden bg-slate-100 border border-slate-200 shrink-0 flex items-center justify-center">
                                            @if ($foto)
                                                <img src="{{ route('fotografias.show', $foto->id) }}"
                                                     alt="{{ $pub->nombre ?? $pub->titulo }}"
                                                     class="w-full h-full object-cover" />
                                            @else
                                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="font-mono text-xs font-bold text-[#691C32] block">{{ $pub->folio ?? 'N/A' }}</span>
                                            <h4 class="font-bold text-slate-900 text-sm leading-tight">{{ $pub->nombre ?? $pub->titulo }}</h4>
                                        </div>
                                    </div>

                                    @if ($rec->isAnualVencido())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-900 border border-rose-300 shrink-0">
                                            Resguardo vencido
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-300 shrink-0">
                                            Bajo resguardo
                                        </span>
                                    @endif
                                </div>

                                <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 block uppercase">Recepción</span>
                                        <span class="font-semibold text-slate-800">{{ $rec->fecha_recepcion ? $rec->fecha_recepcion->format('d/m/Y') : '' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 block uppercase">Límite anual</span>
                                        <span class="font-semibold {{ $rec->isAnualVencido() ? 'text-rose-700' : 'text-slate-800' }}">{{ $rec->fecha_limite_anual ? $rec->fecha_limite_anual->format('d/m/Y') : '' }}</span>
                                    </div>
                                    <div class="col-span-2">
                                        <span class="text-[10px] font-bold text-slate-400 block uppercase">Ubicación</span>
                                        <span class="font-medium text-slate-800">{{ $rec->ubicacion_resguardo }}</span>
                                    </div>
                                    <div class="col-span-2">
                                        <span class="text-[10px] font-bold text-slate-400 block uppercase">Condición</span>
                                        <span class="text-slate-700 truncate block">{{ $rec->condicion_objeto }}</span>
                                    </div>
                                </div>

                                <div>
                                    <a href="{{ route('admin.inventario.show', $rec->id) }}"
                                       class="w-full inline-flex items-center justify-center gap-1.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 hover:text-[#691C32] font-bold text-xs shadow-2xs transition-all">
                                        <span>Ver detalle</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Paginación si aplica -->
                    @if (method_exists($recepciones, 'hasPages') && $recepciones->hasPages())
                        <div class="p-4 border-t border-slate-200">
                            {{ $recepciones->links() }}
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>
</x-app-layout>

