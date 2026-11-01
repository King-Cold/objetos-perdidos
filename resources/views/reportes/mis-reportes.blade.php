<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-100 text-[#691C32] flex items-center justify-center shrink-0 shadow-2xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="font-black text-xl text-slate-900 leading-tight">
                        Mis Reportes de Objetos
                    </h2>
                    <p class="text-xs text-slate-500 font-medium">
                        Historial de objetos perdidos o encontrados registrados por tu cuenta
                    </p>
                </div>
            </div>
            <div>
                <a href="{{ route('reportes.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] text-white text-xs font-bold transition-all shadow-xs">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Encontré un objeto</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-50/60 min-h-[calc(100vh-140px)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

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
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-sm font-semibold text-emerald-800 flex items-center gap-3 shadow-2xs">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('warning'))
                <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-300 text-sm font-semibold text-amber-900 flex items-center gap-3 shadow-2xs">
                    <svg class="w-5 h-5 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('warning') }}</span>
                </div>
            @endif

            @if (isset($reportes) && $reportes->count() > 0)
                <!-- Distribución en cuadrícula con tarjetas compactas, borde guinda y fotografías mostradas abajito -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($reportes as $rep)
                        <div class="bg-white rounded-3xl border border-slate-200/90 border-l-4 border-l-[#691C32] p-5 shadow-xs hover:shadow-md transition-all flex flex-col justify-between">
                            <div>
                                <!-- Fila 1: Folio a la izquierda y Estado oficial en píldora a la derecha -->
                                <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 gap-2">
                                    <span class="font-black text-sm text-slate-900 tracking-tight">
                                        {{ $rep->folio }}
                                    </span>

                                    @if ($rep->estado === 'Aprobada')
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            Aprobada
                                        </span>
                                    @elseif ($rep->estado === 'Pendiente de aprobación')
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[#FDF2E9] text-[#C05621] border border-orange-100">
                                            Pendiente de aprobación
                                        </span>
                                    @elseif ($rep->estado === 'Requiere corrección' || $rep->estado === 'Corrección solicitada')
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-800 border border-rose-200">
                                            {{ $rep->estado }}
                                        </span>
                                    @elseif ($rep->estado === 'Cancelado')
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                            Cancelado
                                        </span>
                                    @else
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                                            {{ $rep->estado }}
                                        </span>
                                    @endif
                                </div>

                                <!-- Fila 2: Foto destacada cuadrada y Título/Descripción -->
                                <div class="py-3.5 border-b border-slate-100 flex items-start gap-3.5">
                                    <!-- Contenedor cuadrado de la imagen (proporcionado y estilizado) -->
                                    <div class="w-20 h-20 sm:w-22 sm:h-22 rounded-2xl bg-slate-50 border border-slate-100 p-1 flex items-center justify-center shrink-0 overflow-hidden">
                                        @php
                                            $fotoPrincipal = $rep->foto_principal ?? $rep->fotografias->first();
                                        @endphp
                                        @if ($fotoPrincipal)
                                            <img
                                                src="{{ $fotoPrincipal->url }}"
                                                alt="{{ $rep->nombre ?? $rep->titulo }}"
                                                class="w-full h-full object-contain"
                                                loading="lazy"
                                            />
                                        @else
                                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        @endif
                                    </div>

                                    <!-- Nombre, Descripción e Indicador de Muro -->
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-sm font-bold text-slate-900 leading-snug truncate">
                                            {{ $rep->nombre ?? $rep->titulo }}
                                        </h3>
                                        <p class="text-xs text-slate-500 line-clamp-2 mt-1 leading-relaxed">
                                            {{ $rep->descripcion }}
                                        </p>
                                        <div class="mt-2">
                                            @if ($rep->isDisponible())
                                                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    <span>Disponible en el muro</span>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-500">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                                    <span>Fuera del muro</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 3: Información estructurada con iconos institucionales en tono vino -->
                                <div class="py-3.5 space-y-2.5 text-xs border-b border-slate-100">
                                    <!-- Fecha y Hora del Hallazgo (Encontrado) -->
                                    <div class="flex items-start gap-2.5">
                                        <svg class="w-4 h-4 text-[#691C32] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <span class="text-[11px] font-medium text-slate-400 block leading-tight">Encontrado</span>
                                            <span class="font-bold text-slate-900 text-xs">
                                                {{ $rep->fecha_hallazgo_texto }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Fecha de Registro en Servidor -->
                                    <div class="flex items-start gap-2.5">
                                        <svg class="w-4 h-4 text-[#691C32] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <div>
                                            <span class="text-[11px] font-medium text-slate-400 block leading-tight">Fecha de registro</span>
                                            <span class="font-bold text-slate-900 text-xs">
                                                {{ $rep->fecha_registro_texto }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Ubicación del Hallazgo -->
                                    <div class="flex items-start gap-2.5">
                                        <svg class="w-4 h-4 text-[#691C32] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <div class="min-w-0">
                                            <span class="text-[11px] font-medium text-slate-400 block leading-tight">Ubicación</span>
                                            <span class="font-bold text-slate-900 text-xs block truncate">
                                                {{ $rep->area_zona }}@if ($rep->ubicacion_especifica) - {{ $rep->ubicacion_especifica }}@endif
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 4: Detalles de Resguardo, Vigencia y Contacto organizados ordenadamente -->
                                <div class="py-2.5 space-y-1.5 text-xs">
                                    <!-- Situación de resguardo -->
                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="text-slate-500 font-medium">Situación de resguardo:</span>
                                        <span class="font-bold text-slate-800">{{ $rep->situacion_resguardo ?? 'En posesión del autor' }}</span>
                                    </div>

                                    <!-- Vigencia del reporte -->
                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="text-slate-500 font-medium">Vigencia:</span>
                                        <span class="font-bold {{ $rep->fecha_primera_aprobacion ? 'text-slate-800' : 'text-amber-700' }}">{{ $rep->vigencia_texto }}</span>
                                    </div>

                                    <!-- Teléfono de contacto registrado -->
                                    @if (Auth::user()->phone ?? ($rep->user ? $rep->user->phone : null))
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="text-slate-500 font-medium">Teléfono de contacto:</span>
                                            <span class="font-bold text-slate-800">{{ Auth::user()->phone ?? $rep->user->phone }}</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Observaciones Administrativas de Biblioteca (CA-07, CA-08) -->
                                @if ($rep->isEnCorreccion() && ($rep->motivo_correccion || $rep->observaciones_admin || $rep->observaciones))
                                    <div class="mt-2 p-2.5 bg-amber-50 border border-amber-300 rounded-2xl text-[11px] text-amber-950 shadow-2xs">
                                        <div class="font-bold flex items-center gap-1.5 text-amber-900 mb-0.5">
                                            <svg class="w-3.5 h-3.5 text-amber-700 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                            </svg>
                                            <span>Observaciones de Biblioteca:</span>
                                        </div>
                                        <p class="leading-tight font-medium text-amber-800">
                                            {{ $rep->motivo_correccion ?? $rep->observaciones_admin ?? $rep->observaciones }}
                                        </p>
                                    </div>
                                @endif

                                @if ($rep->estado === 'Cancelado' && $rep->motivo_cancelacion)
                                    <div class="mt-2 p-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-[11px] text-slate-600">
                                        <span class="font-bold text-slate-700 block text-[10px] uppercase tracking-wider mb-0.5">Motivo de cancelación:</span>
                                        <p class="leading-tight text-slate-600">{{ $rep->motivo_cancelacion }}</p>
                                    </div>
                                @endif

                                <!-- Fotografías asociadas al reporte (mostradas abajito) -->
                                @if ($rep->fotografias && $rep->fotografias->isNotEmpty())
                                    <div class="pt-3 mt-1 border-t border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">
                                            Fotografías ({{ $rep->fotografias->count() }})
                                        </span>
                                        <div class="flex items-center gap-2 overflow-x-auto">
                                @foreach ($rep->fotografias as $foto)
                                                <a
                                                    href="{{ route('fotografias.show', $foto->id) }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="relative block shrink-0 group"
                                                    title="Ver fotografía"
                                                >
                                                    <img
                                                        src="{{ $foto->url }}"
                                                        alt="{{ $foto->nombre_original ?? 'Foto del objeto' }}"
                                                        class="w-14 h-14 rounded-lg object-cover border border-slate-200 bg-slate-50 group-hover:border-[#691C32] transition-all"
                                                        loading="lazy"
                                                    />

                                                    @if ($fotoPrincipal && $foto->id === $fotoPrincipal->id)
                                                        <span class="absolute bottom-0.5 left-0.5 px-1 py-0.5 rounded text-[8px] font-bold bg-[#722F37] text-white leading-none shadow-xs">
                                                            Principal
                                                        </span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Fila 5: Pie de tarjeta con acciones permitidas (CA-03, CA-06, CA-09, CA-31, CA-38) -->
                            <div class="pt-3.5 border-t border-slate-100 mt-2 space-y-2">
                                <div class="grid {{ $rep->puedeEditarse() ? 'grid-cols-2' : 'grid-cols-1' }} gap-2">
                                    <!-- Botón Ver detalle (CA-06: abre detalle privado) -->
                                    <a href="{{ route('reportes.show', $rep->id) }}" class="inline-flex items-center justify-center gap-1.5 w-full py-2 px-3 rounded-2xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-all shadow-2xs">
                                        <svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>Ver detalle</span>
                                    </a>

                                    <!-- Botón Editar reporte (CA-09) -->
                                    @if ($rep->puedeEditarse())
                                        <a href="{{ route('reportes.edit', $rep->id) }}" class="inline-flex items-center justify-center gap-1.5 w-full py-2 px-3 rounded-2xl border border-slate-200 hover:border-[#691C32] hover:bg-rose-50/40 text-slate-700 hover:text-[#691C32] text-xs font-bold transition-all shadow-2xs">
                                            <span>Editar reporte</span>
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </a>
                                    @endif
                                </div>

                                <!-- Botón Cancelar reporte (CA-31) -->
                                @if ($rep->puedeCancelarse())
                                    <a href="{{ route('reportes.cancelar', $rep->id) }}" class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-3 rounded-xl border border-transparent hover:border-rose-200 hover:bg-rose-50/60 text-slate-500 hover:text-rose-700 text-xs font-semibold transition-all">
                                        <svg class="w-3.5 h-3.5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <span>Cancelar reporte</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <!-- Estado Vacío Estándar (CA-05) -->
                <div class="bg-white rounded-3xl p-12 border border-slate-200 text-center max-w-md mx-auto shadow-sm">
                    <div class="w-14 h-14 rounded-2xl bg-rose-50 text-[#691C32] border border-rose-100 flex items-center justify-center mx-auto mb-4 shadow-2xs">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-black text-slate-800 mb-1.5 tracking-tight">
                        Aún no has registrado objetos encontrados
                    </h3>
                    <p class="text-xs text-slate-500 mb-6 leading-relaxed">
                        Cuando reportes un objeto perdido o encontrado en el campus aparecerá en este listado.
                    </p>
                    <a href="{{ route('reportes.create') }}" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] text-white text-xs font-bold transition-all shadow-sm hover:shadow-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Encontré un objeto</span>
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
