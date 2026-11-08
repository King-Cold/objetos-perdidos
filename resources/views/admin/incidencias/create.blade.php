<x-app-layout>
    <div class="py-8 sm:py-10 bg-[#F8F9FA] min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Navegación superior --}}
            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('admin.incidencias.index') }}"
                   class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-[#4E0B1B] transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Volver al listado de incidencias
                </a>
                <span class="px-3 py-1 rounded-full bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold">
                    Apertura de incidencia
                </span>
            </div>

            {{-- Encabezado --}}
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-slate-100 pb-6">
                    <div>
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">
                            Abrir incidencia de entrega
                        </h1>
                        <p class="mt-1 text-sm text-slate-600">
                            Registra una duda relacionada exclusivamente con objetos que tengan estado «Devuelto» y que no cuenten ya con una incidencia abierta.
                        </p>
                    </div>
                </div>

                {{-- Aviso de no presunción de culpabilidad --}}
                <div class="mt-6 rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900 leading-relaxed">
                    <p class="font-bold text-amber-950 mb-1">Criterio institucional de neutralidad y disponibilidad preventiva</p>
                    <p>
                        La apertura de una incidencia mantiene el objeto fuera del muro público y del detalle general mientras se aclara la situación. <strong>No señala automáticamente al receptor como responsable de una entrega indebida ni confirma por sí misma una irregularidad.</strong>
                    </p>
                </div>

                @if ($errors->any())
                    <div class="mt-6 rounded-2xl bg-rose-50 border border-rose-200 p-4 text-xs text-rose-900 space-y-1">
                        <p class="font-bold">Por favor corrige los siguientes errores antes de continuar:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (!$publicacion && $publicacionesDisponibles->isEmpty())
                    <div class="mt-6 rounded-2xl bg-slate-50 border border-slate-200 p-8 text-center space-y-2">
                        <h2 class="text-sm font-extrabold text-slate-800">
                            No hay objetos disponibles para registrar incidencias
                        </h2>
                        <p class="text-xs text-slate-600 max-w-lg mx-auto">
                            Actualmente no existen objetos con estado «Devuelto» sin incidencia previa. Únicamente se puede abrir una incidencia por objeto cuyo estado sea «Devuelto».
                        </p>
                    </div>
                @else
                    <form method="POST"
                          action="{{ $publicacion ? route('admin.incidencias.store', $publicacion->id) : route('admin.incidencias.store') }}"
                          class="mt-6 space-y-6">
                        @csrf

                        @if ($publicacion)
                            <input type="hidden" name="publicacion_id" value="{{ $publicacion->id }}">

                            {{-- Resumen del reporte seleccionado --}}
                            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-5 space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Reporte original (Estado: Devuelto)</span>
                                    <span class="px-3 py-1 rounded-lg bg-[#FDF8E7] border border-[#E9D8A6] text-xs font-extrabold text-[#4E0B1B]">
                                        {{ $publicacion->folio }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <span class="text-slate-500 block">Nombre del objeto:</span>
                                        <span class="font-bold text-slate-900 text-sm">{{ $publicacion->nombre ?? $publicacion->titulo }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block">Autor del reporte:</span>
                                        <span class="font-semibold text-slate-800">{{ $publicacion->user?->name ?? 'Usuario institucional' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block">Estado actual:</span>
                                        <span class="font-semibold text-emerald-800">{{ $publicacion->estado }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block">Situación de resguardo:</span>
                                        <span class="font-semibold text-slate-800">{{ $publicacion->situacion_resguardo ?? 'En resguardo del autor' }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Selección del evento de devolución correspondiente --}}
                            @if ($devolucionSeleccionada)
                                <input type="hidden" name="devolucion_id" value="{{ $devolucionSeleccionada->id }}">
                                <div class="rounded-2xl bg-emerald-50/60 border border-emerald-200 p-4 text-xs space-y-1.5">
                                    <div class="font-bold text-emerald-950">
                                        Evento de devolución correspondiente (#{{ $devolucionSeleccionada->id }})
                                    </div>
                                    <div class="text-emerald-900">
                                        Receptor registrado: <strong>{{ $devolucionSeleccionada->receptor_nombre }}</strong>
                                        ({{ $devolucionSeleccionada->receptor_identificador }}) • Estado: <strong>{{ $devolucionSeleccionada->estado }}</strong>
                                    </div>
                                </div>
                            @elseif ($publicacion->devoluciones && $publicacion->devoluciones->isNotEmpty())
                                <div>
                                    <label for="devolucion_id" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                        Evento de devolución correspondiente
                                    </label>
                                    <select id="devolucion_id"
                                            name="devolucion_id"
                                            class="w-full rounded-xl border-slate-300 text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                                        @foreach ($publicacion->devoluciones as $dev)
                                            <option value="{{ $dev->id }}" @selected(old('devolucion_id') == $dev->id)>
                                                Devolución #{{ $dev->id }} — Receptor: {{ $dev->receptor_nombre }} ({{ $dev->estado }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        @else
                            <div x-data="{ selectedPub: '{{ old('publicacion_id', '') }}' }" class="space-y-4">
                                <div>
                                    <label for="publicacion_id" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                        Selecciona el objeto con estado «Devuelto» <span class="text-rose-600">*</span>
                                    </label>
                                    <select id="publicacion_id"
                                            name="publicacion_id"
                                            x-model="selectedPub"
                                            required
                                            class="w-full rounded-xl border-slate-300 text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                                        <option value="">-- Selecciona un objeto devuelto por folio y nombre --</option>
                                        @foreach ($publicacionesDisponibles as $pubItem)
                                            <option value="{{ $pubItem->id }}" @selected(old('publicacion_id') == $pubItem->id)>
                                                {{ $pubItem->folio }} — {{ $pubItem->nombre ?? $pubItem->titulo }} ({{ $pubItem->estado }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="devolucion_id" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                        Evento de devolución correspondiente (opcional si deseas especificar uno en particular)
                                    </label>
                                    <select id="devolucion_id"
                                            name="devolucion_id"
                                            class="w-full rounded-xl border-slate-300 text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                                        <option value="">Vincular automáticamente con el evento de devolución vigente del reporte</option>
                                        @foreach ($publicacionesDisponibles as $pubItem)
                                            @foreach ($pubItem->devoluciones as $devItem)
                                                <option value="{{ $devItem->id }}"
                                                        x-show="!selectedPub || selectedPub == '{{ $pubItem->id }}'"
                                                        @selected(old('devolucion_id') == $devItem->id)>
                                                    [{{ $pubItem->folio }}] Devolución #{{ $devItem->id }} — Receptor: {{ $devItem->receptor_nombre }} ({{ $devItem->estado }})
                                                </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif

                        {{-- Motivo obligatorio --}}
                        <div>
                            <label for="motivo" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                Motivo obligatorio de apertura <span class="text-rose-600">*</span>
                            </label>
                            <textarea id="motivo"
                                      name="motivo"
                                      rows="4"
                                      required
                                      placeholder="Describe con claridad la duda relacionada con la entrega o devolución del objeto..."
                                      class="w-full rounded-2xl border-slate-300 text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">{{ old('motivo') }}</textarea>
                            <p class="mt-1.5 text-xs text-slate-500">
                                Se registrará automáticamente tu usuario como responsable de apertura, la fecha y hora actuales y el estado inicial «Abierta». No se aceptan motivos vacíos o compuestos únicamente por espacios.
                            </p>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <a href="{{ route('admin.incidencias.index') }}"
                               class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition">
                                Cancelar
                            </a>
                            <button type="submit"
                                    class="px-6 py-2.5 rounded-xl bg-[#4E0B1B] hover:bg-[#3A0814] text-white text-xs font-bold shadow-sm transition">
                                Confirmar apertura de incidencia
                            </button>
                        </div>
                    </form>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>

