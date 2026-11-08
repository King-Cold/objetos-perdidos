<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full bg-amber-50 border border-amber-300/80 text-[10px] font-black uppercase tracking-wider text-amber-900">
                        Panel Administrativo &bull; Biblioteca
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full bg-tecnm-wine/10 text-[10px] font-bold text-tecnm-wine">
                        Gestión de Objetos
                    </span>
                </div>
                <h2 class="font-black text-2xl text-slate-800 leading-tight tracking-tight">
                    Moderación de reportes
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Gestión integral de objetos y publicaciones por estado: pendientes de revisión, activas en muro, vencidas, resguardo y canceladas.
                </p>
            </div>

            <!-- Contador de pendientes -->
            <div class="flex items-center gap-3">
                <div class="px-4 py-2 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full {{ ($contadores['pendientes'] ?? $pendientes->total()) > 0 ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500' }}"></span>
                    <span class="text-xs font-medium text-slate-600">
                        Total pendientes: <strong class="text-slate-900 font-extrabold">{{ $contadores['pendientes'] ?? $pendientes->total() }}</strong>
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

            <!-- Pestañas de filtrado por estado (Igual a Entregas y Devoluciones) -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="flex items-center gap-1.5 p-1.5 bg-slate-50/80 border-b border-slate-100 flex-wrap">
                    <!-- 1. Pendientes de revisión -->
                    <a href="{{ route('admin.moderacion.index', ['filtro' => 'pendientes']) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2 {{ ($filtroEstado ?? 'pendientes') === 'pendientes' ? 'bg-white text-tecnm-wine border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        <span>Pendientes de revisión</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($filtroEstado ?? 'pendientes') === 'pendientes' ? 'bg-tecnm-wine/10 text-tecnm-wine' : 'bg-slate-200/80 text-slate-600' }}">
                            {{ $contadores['pendientes'] ?? 0 }}
                        </span>
                    </a>

                    <!-- 2. Activas en el muro -->
                    <a href="{{ route('admin.moderacion.index', ['filtro' => 'activas']) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2 {{ ($filtroEstado ?? 'pendientes') === 'activas' ? 'bg-white text-emerald-700 border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        <span>Activas en el muro</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($filtroEstado ?? 'pendientes') === 'activas' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200/80 text-slate-600' }}">
                            {{ $contadores['activas'] ?? 0 }}
                        </span>
                    </a>

                    <!-- 3. Vencidas (Pendientes biblioteca) -->
                    <a href="{{ route('admin.moderacion.index', ['filtro' => 'vencidas']) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2 {{ ($filtroEstado ?? 'pendientes') === 'vencidas' ? 'bg-white text-amber-900 border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        <span>Vencidas (Pendientes biblioteca)</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($filtroEstado ?? 'pendientes') === 'vencidas' ? 'bg-amber-200 text-amber-950' : 'bg-slate-200/80 text-slate-600' }}">
                            {{ $contadores['vencidas'] ?? 0 }}
                        </span>
                    </a>

                    <!-- 4. Bajo resguardo biblioteca -->
                    <a href="{{ route('admin.moderacion.index', ['filtro' => 'resguardo']) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2 {{ ($filtroEstado ?? 'pendientes') === 'resguardo' ? 'bg-white text-blue-700 border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        <span>En resguardo de biblioteca</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($filtroEstado ?? 'pendientes') === 'resguardo' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200/80 text-slate-600' }}">
                            {{ $contadores['resguardo'] ?? 0 }}
                        </span>
                    </a>

                    <!-- 5. Canceladas -->
                    <a href="{{ route('admin.moderacion.index', ['filtro' => 'canceladas']) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2 {{ ($filtroEstado ?? 'pendientes') === 'canceladas' ? 'bg-white text-rose-700 border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        <span>Canceladas</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($filtroEstado ?? 'pendientes') === 'canceladas' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200/80 text-slate-600' }}">
                            {{ $contadores['canceladas'] ?? 0 }}
                        </span>
                    </a>

                    <!-- 6. Todas -->
                    <a href="{{ route('admin.moderacion.index', ['filtro' => 'todas']) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-2 {{ ($filtroEstado ?? 'pendientes') === 'todas' ? 'bg-white text-slate-800 border border-slate-200 shadow-xs' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
                        <span>Todas</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($filtroEstado ?? 'pendientes') === 'todas' ? 'bg-slate-200 text-slate-800' : 'bg-slate-200/80 text-slate-600' }}">
                            {{ $contadores['todas'] ?? 0 }}
                        </span>
                    </a>
                </div>
            </div>

            <!-- Contenedor Principal: Tabla Administrativa / Estado Vacío -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-gradient-to-r from-slate-50/60 to-white">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            @if (($filtroEstado ?? 'pendientes') === 'activas')
                                Publicaciones activas en el muro público
                            @elseif (($filtroEstado ?? 'pendientes') === 'vencidas')
                                Publicaciones vencidas (Pendientes de entrega a biblioteca)
                            @elseif (($filtroEstado ?? 'pendientes') === 'resguardo')
                                Objetos bajo resguardo de biblioteca
                            @elseif (($filtroEstado ?? 'pendientes') === 'canceladas')
                                Reportes cancelados
                            @elseif (($filtroEstado ?? 'pendientes') === 'todas')
                                Todas las publicaciones
                            @else
                                Lista de reportes pendientes de revisión
                            @endif
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            @if (($filtroEstado ?? 'pendientes') === 'activas')
                                Reportes autorizados y vigentes que actualmente se exhiben en el muro para la comunidad del campus.
                            @elseif (($filtroEstado ?? 'pendientes') === 'vencidas')
                                Publicaciones que superaron su vigencia ordinaria de 14 días sin reclamo. Listas para recepción física en biblioteca.
                            @elseif (($filtroEstado ?? 'pendientes') === 'resguardo')
                                Objetos que fueron entregados formalmente y permanecen bajo resguardo institucional de biblioteca.
                            @elseif (($filtroEstado ?? 'pendientes') === 'canceladas')
                                Reportes que fueron cancelados por duplicado o decisión administrativa de moderación.
                            @elseif (($filtroEstado ?? 'pendientes') === 'todas')
                                Visión integral de todas las publicaciones del sistema (excluyendo entregas en proceso).
                            @else
                                Ordenados cronológicamente desde el envío a revisión más antiguo para garantizar atención prioritaria.
                            @endif
                        </p>
                    </div>

                    @if ($pendientes->total() > 0)
                        <div class="text-[11px] font-medium text-slate-500 bg-white px-3 py-1.5 rounded-xl border border-slate-200 shadow-xs shrink-0">
                            @if (($filtroEstado ?? 'pendientes') === 'pendientes')
                                Criterio: <strong class="text-slate-700">Envío más antiguo primero</strong>
                            @elseif (($filtroEstado ?? 'pendientes') === 'activas')
                                Criterio: <strong class="text-slate-700">Aprobación más reciente primero</strong>
                            @elseif (($filtroEstado ?? 'pendientes') === 'vencidas')
                                Criterio: <strong class="text-slate-700">Vencimiento más antiguo primero</strong>
                            @else
                                Mostrando: <strong class="text-slate-700">{{ $pendientes->total() }} registros</strong>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($pendientes->count() === 0)
                    <!-- ESTADO VACÍO EXACTO -->
                    <div class="py-16 px-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-3xl bg-slate-50 border border-slate-200 text-slate-400 flex items-center justify-center shadow-xs">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h4 class="text-lg font-black text-slate-800 tracking-tight">
                            @if (($filtroEstado ?? 'pendientes') === 'activas')
                                No hay publicaciones activas en el muro
                            @elseif (($filtroEstado ?? 'pendientes') === 'vencidas')
                                No hay publicaciones vencidas pendientes
                            @elseif (($filtroEstado ?? 'pendientes') === 'resguardo')
                                No hay objetos bajo resguardo en biblioteca
                            @elseif (($filtroEstado ?? 'pendientes') === 'canceladas')
                                No hay reportes cancelados
                            @elseif (($filtroEstado ?? 'pendientes') === 'todas')
                                No hay publicaciones registradas
                            @else
                                No hay reportes pendientes de revisión
                            @endif
                        </h4>
                        <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto leading-relaxed">
                            @if (($filtroEstado ?? 'pendientes') === 'activas')
                                Actualmente no existen objetos vigentes exhibidos públicamente en el muro.
                            @elseif (($filtroEstado ?? 'pendientes') === 'vencidas')
                                No se encuentran reportes vencidos pendientes de entrega física a biblioteca en este momento.
                            @elseif (($filtroEstado ?? 'pendientes') === 'resguardo')
                                No se encuentran objetos bajo resguardo físico de biblioteca en este filtro.
                            @elseif (($filtroEstado ?? 'pendientes') === 'canceladas')
                                No se registran publicaciones canceladas en el sistema.
                            @elseif (($filtroEstado ?? 'pendientes') === 'todas')
                                No se encontraron reportes con los criterios seleccionados.
                            @else
                                Todos los reportes de hallazgos han sido moderados y se encuentran debidamente autorizados o devueltos.
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
                    <!-- TABLA RESPONSIVA DE PUBLICACIONES -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse table-auto text-xs">
                            <thead>
                                <tr class="bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-600 border-b border-slate-200/80">
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3 w-[12%]">Folio del reporte</th>
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3 w-[26%]">Nombre del objeto</th>
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3 w-[18%]">Autor</th>
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3 w-[14%]">Fecha de captura</th>
                                    @if (($filtroEstado ?? 'pendientes') === 'pendientes')
                                        <th scope="col" class="py-2.5 px-2.5 sm:px-3 w-[16%]">Fecha y hora del último envío a revisión</th>
                                    @else
                                        <th scope="col" class="py-2.5 px-2.5 sm:px-3 w-[16%]">Estado / Situación actual</th>
                                    @endif
                                    <th scope="col" class="py-2.5 px-2.5 sm:px-3 text-right w-[14%]">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                                @foreach ($pendientes as $rep)
                                    @php
                                        $fechaCapturaTexto = $rep->fecha_captura 
                                            ? $rep->fecha_captura->format('d/m/Y H:i') 
                                            : ($rep->created_at ? $rep->created_at->format('d/m/Y H:i') : '-');

                                        $fechaEnvioTexto = $rep->fecha_envio_revision 
                                            ? $rep->fecha_envio_revision->format('d/m/Y H:i') 
                                            : $fechaCapturaTexto;

                                        $foto = $rep->foto_principal ?? $rep->fotografias->first();
                                    @endphp
                                    <tr class="hover:bg-amber-50/30 transition-colors group">
                                        <!-- 1. Folio del reporte -->
                                        <td class="py-2.5 px-2.5 sm:px-3 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-amber-50/80 border border-amber-200/80 font-mono text-[11px] font-black text-[#691C32] tracking-wider shadow-2xs">
                                                {{ $rep->folio ?? ('#' . $rep->id) }}
                                            </span>
                                        </td>

                                        <!-- 2. Nombre del objeto con imagen miniatura -->
                                        <td class="py-2.5 px-2.5 sm:px-3">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-lg overflow-hidden bg-slate-100 border border-slate-200 shrink-0 flex items-center justify-center">
                                                    @if ($foto)
                                                        <img src="{{ route('fotografias.show', $foto->id) }}"
                                                             alt="{{ $rep->nombre ?? $rep->titulo }}"
                                                             class="w-full h-full object-cover" />
                                                    @else
                                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    @endif
                                                </div>
                                                <div class="min-w-0 max-w-[140px] lg:max-w-[200px]">
                                                    <div class="font-bold text-slate-900 group-hover:text-tecnm-wine transition-colors truncate" title="{{ $rep->nombre ?? $rep->titulo }}">
                                                        {{ $rep->nombre ?? $rep->titulo }}
                                                    </div>
                                                    @if ($rep->categoria)
                                                        <span class="text-[10px] font-semibold text-slate-400 block truncate">
                                                            {{ $rep->categoria }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 3. Autor -->
                                        <td class="py-2.5 px-2.5 sm:px-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-slate-100 border border-slate-200 text-slate-600 flex items-center justify-center text-[10px] font-bold shrink-0">
                                                    {{ strtoupper(substr($rep->user->name ?? 'U', 0, 1)) }}
                                                </div>
                                                <div class="truncate max-w-[110px] lg:max-w-[140px]">
                                                    <span class="font-semibold text-slate-800 block truncate" title="{{ $rep->user->name ?? 'Usuario no disponible' }}">
                                                        {{ $rep->user->name ?? 'Usuario no disponible' }}
                                                    </span>
                                                    <span class="text-[10px] text-slate-400 block truncate">
                                                        {{ $rep->user->email ?? '' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 4. Fecha de captura (original) -->
                                        <td class="py-2.5 px-2.5 sm:px-3 whitespace-nowrap text-slate-600">
                                            <div class="flex items-center gap-1 font-medium text-xs">
                                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>{{ $fechaCapturaTexto }}</span>
                                            </div>
                                        </td>

                                        <!-- 5. Fecha de envío o Estado actual -->
                                        @if (($filtroEstado ?? 'pendientes') === 'pendientes')
                                            <td class="py-2.5 px-2.5 sm:px-3 whitespace-nowrap">
                                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-50 border border-slate-200/80 font-semibold text-slate-800 text-xs">
                                                    <svg class="w-3 h-3 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>{{ $fechaEnvioTexto }}</span>
                                                </div>
                                            </td>
                                        @else
                                            <td class="py-2.5 px-2.5 sm:px-3 whitespace-nowrap">
                                                @if ($rep->situacion_resguardo === 'En biblioteca')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-900 border border-blue-200">
                                                        En resguardo biblioteca
                                                    </span>
                                                @elseif ($rep->isVencida())
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-950 border border-amber-300">
                                                        Vencida (Pendiente entrega)
                                                    </span>
                                                @elseif ($rep->isCancelado())
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-900 border border-rose-300">
                                                        Cancelada
                                                    </span>
                                                @elseif ($rep->disponible)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-300">
                                                        Activa en el muro
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                                        {{ $rep->estado }}
                                                    </span>
                                                @endif
                                            </td>
                                        @endif

                                        <!-- 6. Acciones -->
                                        <td class="py-2.5 px-2.5 sm:px-3 whitespace-nowrap text-right">
                                            <div class="inline-flex items-center justify-end gap-1.5">
                                                <a
                                                    href="{{ route('reportes.historial', $rep->id) }}"
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-200 hover:border-[#691C32] hover:bg-rose-50/40 text-slate-700 hover:text-[#691C32] font-semibold text-xs transition-all shadow-2xs"
                                                    title="Consultar historial del objeto {{ $rep->folio ?? ('#' . $rep->id) }}"
                                                >
                                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>Historial</span>
                                                </a>

                                                @if (($filtroEstado ?? 'pendientes') === 'vencidas' && $rep->esElegibleParaRecepcionFisica())
                                                    <!-- Acción directa HU-16: Registrar recepción física -->
                                                    <a
                                                        href="{{ route('admin.recepciones.create', $rep->id) }}"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#691C32] hover:bg-[#520B1D] text-white font-bold text-xs tracking-wide shadow-2xs hover:shadow transition-all"
                                                        title="Registrar recepción física en biblioteca para {{ $rep->folio }}"
                                                    >
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                        <span>Registrar recepción</span>
                                                    </a>
                                                @elseif (($filtroEstado ?? 'pendientes') === 'resguardo')
                                                    <!-- Acción para ir al inventario de biblioteca -->
                                                    <a
                                                        href="{{ route('admin.inventario.show', $rep->id) }}"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs tracking-wide shadow-2xs hover:shadow transition-all"
                                                        title="Ver resguardo institucional de {{ $rep->folio }}"
                                                    >
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                        </svg>
                                                        <span>Ver resguardo</span>
                                                    </a>
                                                @else
                                                    <!-- Acción Revisar o Ver reporte -->
                                                    <a
                                                        href="{{ route('admin.moderacion.show', $rep->id) }}"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-tecnm-wine hover:bg-tecnm-wine-hover active:bg-[#430918] text-white font-bold text-xs tracking-wide shadow-2xs hover:shadow transition-all focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-tecnm-gold cursor-pointer"
                                                        title="Revisar reporte {{ $rep->folio ?? ('#' . $rep->id) }}"
                                                    >
                                                        <svg class="w-3.5 h-3.5 text-amber-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        <span>{{ ($filtroEstado ?? 'pendientes') === 'pendientes' ? 'Revisar' : 'Ver reporte' }}</span>
                                                    </a>
                                                @endif
                                            </div>
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
