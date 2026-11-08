<x-app-layout>
    @php
        $pub = $incidencia->publicacion;
        $dev = $incidencia->devolucion;
    @endphp
    <div class="py-8 sm:py-10 bg-[#F8F9FA] min-h-screen">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Barra superior de navegación --}}
            <div class="flex flex-wrap items-center justify-between gap-4">
                <a href="{{ route('admin.incidencias.index') }}"
                   class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-[#4E0B1B] transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Volver al listado de incidencias
                </a>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($pub)
                        <a href="{{ route('admin.reportes.historial', $pub->id) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-sm transition">
                            Consultar historial del objeto
                        </a>
                    @endif
                    @if ($dev)
                        <a href="{{ route('admin.devoluciones.show', $dev->id) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-[#E9D8A6] bg-[#FDF8E7] hover:bg-[#F9EFC7] text-[#4E0B1B] text-xs font-bold shadow-sm transition">
                            Ver evento de devolución #{{ $dev->id }}
                        </a>
                    @endif
                </div>
            </div>

            {{-- Mensajes de confirmación --}}
            @if (session('status'))
                <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-900 font-medium flex items-center gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            {{-- Encabezado principal del detalle de la incidencia --}}
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 border-b border-slate-100 pb-6">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-3 py-1 rounded-lg bg-slate-900 text-white text-xs font-extrabold">
                                Incidencia #{{ $incidencia->id }}
                            </span>
                            <span class="px-3 py-1 rounded-lg bg-[#FDF8E7] border border-[#E9D8A6] text-xs font-extrabold text-[#4E0B1B]">
                                {{ $pub?->folio ?? 'SIN-FOLIO' }}
                            </span>
                            <span class="px-3 py-1 rounded-full bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold">
                                Estado: {{ $incidencia->estado }}
                            </span>
                            <span class="px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-700 text-xs font-bold">
                                Disponibilidad: {{ $pub && $pub->isDisponible() ? 'Disponible en el muro' : 'Fuera del muro' }}
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                            {{ $pub?->nombre ?? $pub?->titulo ?? 'Objeto reportado' }}
                        </h1>
                        <p class="text-xs text-slate-500">
                            Identificador de la incidencia: <strong>#{{ $incidencia->id }}</strong> • Seguimiento autorizado para el autor: <strong>{{ $incidencia->seguimiento_autorizado }}</strong>
                        </p>
                    </div>
                </div>

                {{-- Datos clave de la incidencia --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="rounded-2xl bg-slate-50 border border-slate-200/80 p-4">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Fecha y hora de apertura</span>
                        <span class="text-sm font-extrabold text-slate-900 mt-1 block">{{ $incidencia->fecha_apertura_texto }}</span>
                    </div>
                    <div class="rounded-2xl bg-slate-50 border border-slate-200/80 p-4">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Administrador/bibliotecario responsable</span>
                        <span class="text-sm font-extrabold text-slate-900 mt-1 block">{{ $incidencia->user?->name ?? 'Administrador / Bibliotecario' }}</span>
                        <span class="text-xs text-slate-500">{{ $incidencia->user?->email }}</span>
                    </div>
                    <div class="rounded-2xl bg-slate-50 border border-slate-200/80 p-4">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Condición en el muro público</span>
                        <span class="text-sm font-extrabold text-rose-700 mt-1 block">
                            {{ $pub && $pub->isDisponible() ? 'Disponible en el muro' : 'Fuera del muro institucional' }}
                        </span>
                        <span class="text-xs text-slate-500">Permanece fuera del muro mientras exista duda de entrega</span>
                    </div>
                </div>

                {{-- Motivo obligatorio registrado --}}
                <div class="rounded-2xl bg-rose-50/50 border border-rose-200 p-5 space-y-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-rose-900 block">
                        Motivo registrado de la incidencia
                    </span>
                    <p class="text-sm text-slate-900 font-medium whitespace-pre-line leading-relaxed">{{ $incidencia->motivo }}</p>
                </div>

                {{-- Garantía de neutralidad sobre el receptor --}}
                <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900 leading-relaxed">
                    <p class="font-bold text-amber-950 mb-1">Garantía institucional de no presunción de culpabilidad</p>
                    <p>
                        Esta incidencia mantiene el objeto fuera del muro público y del detalle general mientras se aclara la duda de entrega. <strong>Su apertura no señala automáticamente al receptor como responsable de una entrega indebida ni confirma una irregularidad.</strong>
                    </p>
                </div>
            </div>

            {{-- Información del reporte original y referencia al evento de devolución --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <h2 class="text-base font-extrabold text-slate-900 border-b border-slate-100 pb-3">
                        Datos del reporte original
                    </h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <dt class="text-slate-500">Folio del reporte original</dt>
                            <dd class="font-bold text-slate-900 mt-0.5">{{ $pub?->folio ?? 'SIN-FOLIO' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Nombre del objeto</dt>
                            <dd class="font-bold text-slate-900 mt-0.5">{{ $pub?->nombre ?? $pub?->titulo }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Autor del reporte</dt>
                            <dd class="font-semibold text-slate-800 mt-0.5">{{ $pub?->user?->name ?? 'Autor' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Estado del objeto</dt>
                            <dd class="font-semibold text-slate-800 mt-0.5">{{ $pub?->estado }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Situación de resguardo</dt>
                            <dd class="font-semibold text-slate-800 mt-0.5">{{ $pub?->situacion_resguardo ?? 'En posesión del autor' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Disponibilidad general</dt>
                            <dd class="font-semibold text-rose-700 mt-0.5">{{ $pub && $pub->isDisponible() ? 'Disponible' : 'Fuera del muro' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <h2 class="text-base font-extrabold text-slate-900 border-b border-slate-100 pb-3">
                        Referencia al evento de devolución relacionado
                    </h2>
                    @if ($dev)
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <dt class="text-slate-500">Evento de devolución</dt>
                                <dd class="font-bold text-slate-900 mt-0.5">Devolución #{{ $dev->id }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Receptor registrado</dt>
                                <dd class="font-bold text-slate-900 mt-0.5">{{ $dev->receptor_nombre }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Identificador / Contacto</dt>
                                <dd class="font-semibold text-slate-800 mt-0.5">{{ $dev->receptor_identificador }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Momento físico de entrega</dt>
                                <dd class="font-semibold text-slate-800 mt-0.5">{{ $dev->momento_entrega_texto }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Estado de la devolución</dt>
                                <dd class="font-semibold text-slate-800 mt-0.5">{{ $dev->estado }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-slate-500">Explicación de comprobación de propiedad</dt>
                                <dd class="font-medium text-slate-800 mt-0.5">{{ $dev->explicacion_propiedad }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="text-xs text-slate-500">
                            No se especificó un evento de devolución individual.
                        </p>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

