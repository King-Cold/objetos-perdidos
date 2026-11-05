<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                        Seguimiento Privado
                    </span>
                    <span class="text-xs text-slate-400">&bull;</span>
                    <span class="text-xs font-semibold text-slate-500">Folio: {{ $devolucion->publicacion->folio }}</span>
                </div>
                <h2 class="font-black text-xl sm:text-2xl text-slate-800 leading-tight">
                    Seguimiento de Devolución
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Consulta privada de los datos registrados y evidencia de entrega física
                </p>
            </div>
            <div>
                @if (Auth::user()->isAdmin())
                    <a href="{{ route('admin.moderacion.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                        <span aria-hidden="true">&larr;</span>
                        <span>Volver a Moderación</span>
                    </a>
                @else
                    <a href="{{ route('reportes.mis-reportes') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                        <span aria-hidden="true">&larr;</span>
                        <span>Volver a Mis Reportes</span>
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-50/50 min-h-[calc(100vh-140px)]">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- CA-07, CA-08, CA-13: Panel de Estado Actual --}}
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-sm space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-slate-100">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">
                            Estado de la entrega
                        </span>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-black uppercase tracking-wider
                                @if ($devolucion->estado === 'Pendiente de verificación') bg-amber-50 text-amber-900 border border-amber-300 ring-2 ring-amber-100
                                @elseif ($devolucion->estado === 'Verificada') bg-emerald-50 text-emerald-900 border border-emerald-300
                                @else bg-slate-100 text-slate-700 @endif"
                            >
                                <span class="w-2 h-2 rounded-full @if ($devolucion->estado === 'Pendiente de verificación') bg-amber-500 animate-pulse @else bg-emerald-500 @endif"></span>
                                <span>{{ $devolucion->estado }}</span>
                            </span>

                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                Fuera del muro público
                            </span>
                        </div>
                    </div>

                    <div class="text-left sm:text-right">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">
                            Folio del objeto
                        </span>
                        <span class="text-sm font-black text-[#691C32] tracking-wider bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-200 inline-block">
                            {{ $devolucion->publicacion->folio }}
                        </span>
                    </div>
                </div>

                {{-- Advertencia institucional CA-08: No garantía automática --}}
                <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200 text-xs text-amber-900 space-y-1">
                    <div class="flex items-center gap-2 font-bold text-amber-950">
                        <svg class="w-4 h-4 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Resultado provisional en revisión administrativa</span>
                    </div>
                    <p class="leading-relaxed">
                        La fotografía y el contacto previo no representan una garantía automática de propiedad. La devolución continuará en revisión por el personal de biblioteca sin marcar el objeto como devuelto hasta contar con resolución administrativa.
                    </p>
                </div>

                {{-- Datos del objeto --}}
                <div class="p-4 sm:p-5 bg-slate-50 rounded-2xl border border-slate-100 space-y-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
                        Objeto entregado
                    </span>
                    <h3 class="text-lg font-black text-slate-900">
                        {{ $devolucion->publicacion->nombre ?? $devolucion->publicacion->titulo }}
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        {{ $devolucion->publicacion->descripcion }}
                    </p>
                </div>

                {{-- Cuadrícula de Datos Registrados (CA-03, CA-04, CA-05) --}}
                <div>
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-700 mb-3">
                        Datos del Receptor y Entrega Física
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="p-3.5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-1">
                            <span class="text-slate-400 font-semibold block">Nombre completo del receptor:</span>
                            <span class="font-bold text-slate-900 text-sm block">
                                {{ $devolucion->receptor_nombre }}
                            </span>
                        </div>

                        <div class="p-3.5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-1">
                            <span class="text-slate-400 font-semibold block">Identificador institucional:</span>
                            <span class="font-bold text-slate-900 text-sm block">
                                {{ $devolucion->receptor_identificador }}
                            </span>
                        </div>

                        <div class="p-3.5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-1">
                            <span class="text-slate-400 font-semibold block">Fecha y hora real de entrega:</span>
                            <span class="font-bold text-slate-900 text-sm block">
                                {{ $devolucion->fecha_entrega_texto }}
                            </span>
                        </div>

                        <div class="p-3.5 bg-slate-50/80 rounded-xl border border-slate-200 space-y-1">
                            <span class="text-slate-400 font-semibold block">Momento de captura en sistema:</span>
                            <span class="font-bold text-slate-900 text-sm block">
                                {{ $devolucion->fecha_captura_texto }}
                            </span>
                        </div>

                        <div class="p-3.5 bg-slate-50/80 rounded-xl border border-slate-200 sm:col-span-2 space-y-1">
                            <span class="text-slate-400 font-semibold block">Autor responsable que entregó:</span>
                            <span class="font-bold text-slate-900 text-sm block">
                                {{ $devolucion->user ? $devolucion->user->name : 'Autor original' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Explicación de comprobación de propiedad (CA-03, CA-04) --}}
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-700 block">
                        Explicación de comprobación de propiedad
                    </span>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs text-slate-800 leading-relaxed font-medium whitespace-pre-line">
                        {{ $devolucion->explicacion_propiedad }}
                    </div>
                </div>

                {{-- Fotografía de Evidencia (CA-06, CA-09) --}}
                <div class="space-y-3 pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-700 block">
                            Fotografía de Evidencia
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                            Acceso Privado Protegido
                        </span>
                    </div>

                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col sm:flex-row items-center gap-4">
                        <div class="relative group rounded-xl overflow-hidden border border-slate-200 bg-white shrink-0">
                            <img
                                src="{{ route('devoluciones.evidencia', $devolucion->id) }}"
                                alt="Evidencia de devolución"
                                class="w-36 h-36 object-cover"
                                loading="lazy"
                            />
                        </div>

                        <div class="flex-1 space-y-2 text-center sm:text-left">
                            <div>
                                <span class="text-xs font-bold text-slate-900 block truncate max-w-xs">
                                    {{ $devolucion->fotografia_nombre_original ?? 'evidencia_entrega.jpg' }}
                                </span>
                                <span class="text-[11px] text-slate-400 block">
                                    {{ $devolucion->fotografia_tamano_bytes ? round($devolucion->fotografia_tamano_bytes / 1024, 1) . ' KB' : 'Formato JPG/PNG' }}
                                </span>
                            </div>

                            <a
                                href="{{ route('devoluciones.evidencia', $devolucion->id) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold transition-all shadow-2xs"
                            >
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                                <span>Ver fotografía en tamaño completo</span>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Pie de tarjeta --}}
                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                    @if (Auth::user()->isAdmin())
                        <a href="{{ route('admin.moderacion.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                            &larr; Volver a Moderación
                        </a>
                    @else
                        <a href="{{ route('reportes.mis-reportes') }}" class="px-4 py-2 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                            &larr; Volver a Mis Reportes
                        </a>
                    @endif

                    <span class="text-[11px] font-semibold text-slate-400">
                        Consulta privada exclusiva para autor y administración
                    </span>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

