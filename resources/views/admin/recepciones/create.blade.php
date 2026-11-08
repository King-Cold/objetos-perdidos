<x-app-layout>
    <div class="min-h-screen bg-[#F5F6F8] py-8 text-[#1F2937]"
         x-data="{
             activeImg: '{{ $fotografiaPrincipal ? route('fotografias.show', $fotografiaPrincipal->id) : '' }}',
             activeId: {{ $fotografiaPrincipal ? $fotografiaPrincipal->id : 'null' }},
             modalFotoOpen: false,
             confirmado: {{ old('confirmacion_cotejo') ? 'true' : 'false' }}
         }">
        <div class="max-w-[1120px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- 1. ENCABEZADO Y RETORNO -->
            <div class="space-y-2">
                <div>
                    <a href="{{ route('admin.moderacion.show', $reporte->id) }}"
                       class="inline-flex items-center gap-1.5 text-xs sm:text-[13px] font-medium text-[#64748B] hover:text-[#691C32] transition-colors focus:outline-none focus:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Volver al reporte {{ $reporte->folio }}</span>
                    </a>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-2xl sm:text-[26px] font-bold text-[#1F2937] tracking-tight leading-tight">
                            Registrar recepción física en biblioteca
                        </h1>
                        <p class="text-xs sm:text-[13px] font-medium text-[#64748B] mt-0.5">
                            Cotejo físico y formalización del resguardo institucional por vencimiento de publicación
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-900 border border-rose-300">
                            Publicación expirada
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-900 border border-amber-300">
                            Pendiente de recepción física
                        </span>
                    </div>
                </div>
            </div>

            <!-- ERRORES GENERALES O DE VALIDACIÓN -->
            @if ($errors->any())
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-300 text-rose-900 flex items-start gap-3 shadow-xs" role="alert">
                    <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="text-xs sm:text-sm">
                        <p class="font-bold">Por favor corrige los siguientes errores antes de continuar:</p>
                        <ul class="list-disc list-inside mt-1 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <!-- CONTENIDO PRINCIPAL: COTEJO FÍSICO Y FORMULARIO -->
            <form action="{{ route('admin.recepciones.store', $reporte->id) }}" method="POST" class="space-y-6">
                @csrf

                <!-- SECCIÓN 1: COTEJO FÍSICO OBLIGATORIO (2 Columnas) -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-[#691C32] text-white flex items-center justify-center font-bold text-xs">
                                1
                            </span>
                            <h2 class="text-base font-bold text-[#1F2937]">
                                Cotejo físico obligatorio del objeto
                            </h2>
                        </div>
                        <span class="text-xs text-[#64748B]">Verifica que el objeto físico corresponda al reporte</span>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                            <!-- Fotografías originales del reporte (5 columnas) -->
                            <div class="lg:col-span-5 space-y-3">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 block">
                                    Fotografías originales registradas
                                </label>

                                <div class="relative w-full h-64 sm:h-72 rounded-xl overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center">
                                    @if ($fotografiaPrincipal)
                                        <img :src="activeImg"
                                             alt="Fotografía de {{ $reporte->nombre ?? $reporte->titulo }}"
                                             class="w-full h-full object-contain p-2 cursor-zoom-in"
                                             @click="modalFotoOpen = true" />

                                        <button type="button"
                                                @click="modalFotoOpen = true"
                                                class="absolute bottom-2.5 right-2.5 inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-white/90 hover:bg-white text-xs font-semibold text-slate-700 shadow-xs border border-slate-200">
                                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                            </svg>
                                            <span>Ampliar</span>
                                        </button>
                                    @else
                                        <div class="text-center p-6 text-slate-400">
                                            <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <span class="text-xs">Sin fotografías adjuntas</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Miniaturas si hay más de una foto -->
                                @if ($reporte->fotografias->count() > 1)
                                    <div class="grid grid-cols-4 gap-2">
                                        @foreach ($reporte->fotografias as $foto)
                                            <button type="button"
                                                    @click="activeImg = '{{ route('fotografias.show', $foto->id) }}'; activeId = {{ $foto->id }}"
                                                    class="relative aspect-square rounded-lg overflow-hidden bg-slate-50 border-2 transition-all cursor-pointer focus:outline-none"
                                                    :class="activeId === {{ $foto->id }} ? 'border-[#691C32] ring-1 ring-[#691C32]' : 'border-slate-200 hover:border-slate-400'">
                                                <img src="{{ route('fotografias.show', $foto->id) }}"
                                                     alt="Miniatura {{ $loop->iteration }}"
                                                     class="w-full h-full object-cover" />
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <!-- Información descriptiva del hallazgo (7 columnas) -->
                            <div class="lg:col-span-7 flex flex-col justify-between space-y-4">
                                <div class="space-y-3.5">
                                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                                        <div>
                                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Folio institucional</span>
                                            <span class="font-mono text-base font-bold text-[#691C32]">{{ $reporte->folio }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Fecha del hallazgo</span>
                                            <span class="text-sm font-semibold text-slate-800">
                                                {{ $reporte->fecha_hallazgo ? $reporte->fecha_hallazgo->format('d/m/Y') : 'No registrada' }}
                                                @if (! empty($reporte->hora_hallazgo))
                                                    <span class="text-xs text-slate-500 font-normal">({{ substr($reporte->hora_hallazgo, 0, 5) }} hrs)</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Nombre del objeto</span>
                                        <h3 class="text-lg font-bold text-slate-900 leading-snug">
                                            {{ $reporte->nombre ?? $reporte->titulo }}
                                        </h3>
                                    </div>

                                    <div>
                                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Descripción original</span>
                                        <p class="text-xs sm:text-sm text-slate-700 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-200/80 whitespace-pre-line">
                                            {{ $reporte->descripcion }}
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 text-xs">
                                        <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200/60">
                                            <span class="text-slate-400 block text-[10px] font-bold uppercase">Lugar de hallazgo</span>
                                            <span class="font-medium text-slate-800">{{ $reporte->lugar_hallazgo ?? $reporte->area_zona ?? 'No especificado' }}</span>
                                        </div>
                                        <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200/60">
                                            <span class="text-slate-400 block text-[10px] font-bold uppercase">Autor que entrega</span>
                                            <span class="font-medium text-slate-800 break-words">{{ $reporte->user->name ?? 'Usuario' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Casilla de confirmación obligatoria -->
                                <div class="mt-4 p-4 rounded-xl bg-amber-50/80 border-2 border-amber-300 space-y-2">
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="checkbox"
                                               name="confirmacion_cotejo"
                                               value="1"
                                               x-model="confirmado"
                                               class="w-5 h-5 mt-0.5 rounded border-amber-400 text-[#691C32] focus:ring-[#691C32] cursor-pointer"
                                               id="check-confirmacion-cotejo"
                                               required />
                                        <span class="text-xs sm:text-sm font-bold text-amber-950 leading-snug select-none">
                                            Confirmo que he recibido físicamente el objeto y que corresponde al reporte mostrado.
                                        </span>
                                    </label>
                                    <p class="text-[11px] text-amber-800 pl-8 leading-tight">
                                        Una promesa de entrega o el simple vencimiento de la publicación no deben generar una recepción. Es requisito haber recibido físicamente el objeto.
                                    </p>
                                    @error('confirmacion_cotejo')
                                        <p class="text-xs text-rose-600 font-semibold pl-8 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                            </div>

                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: FORMULARIO DE RECEPCIÓN FÍSICA -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-[#691C32] text-white flex items-center justify-center font-bold text-xs">
                                2
                            </span>
                            <h2 class="text-base font-bold text-[#1F2937]">
                                Datos del resguardo físico en biblioteca
                            </h2>
                        </div>
                        <span class="text-xs text-[#64748B]">Información física y de ubicación institucional</span>
                    </div>

                    <div class="p-6 space-y-5">

                        <!-- Franja informativa de metadatos automáticos del sistema -->
                        <div class="p-3.5 rounded-xl bg-slate-100/80 border border-slate-200 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div>
                                <span class="text-slate-500 block text-[11px] font-medium">Bibliotecario responsable:</span>
                                <strong class="text-slate-800">{{ auth()->user()->name }}</strong>
                            </div>
                            <div>
                                <span class="text-slate-500 block text-[11px] font-medium">Fecha y hora de captura:</span>
                                <strong class="text-slate-800 font-mono">{{ \Carbon\Carbon::now(\App\Models\Publicacion::TIMEZONE)->format('d/m/Y H:i') }} hrs</strong>
                            </div>
                            <div>
                                <span class="text-slate-500 block text-[11px] font-medium">Vigencia máxima en resguardo:</span>
                                <strong class="text-[#691C32]">1 año natural desde la recepción</strong>
                            </div>
                        </div>

                        <!-- Fila 1: Fecha y Hora real de recepción física -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="fecha_recepcion" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                    Fecha real de recepción <span class="text-rose-600">*</span>
                                </label>
                                <input type="date"
                                       name="fecha_recepcion"
                                       id="fecha_recepcion"
                                       value="{{ old('fecha_recepcion', $fechaRecepcionDefault) }}"
                                       max="{{ $fechaRecepcionDefault }}"
                                       min="{{ $reporte->fecha_hallazgo ? $reporte->fecha_hallazgo->format('Y-m-d') : '' }}"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-medium focus:border-[#691C32] focus:ring-1 focus:ring-[#691C32] @error('fecha_recepcion') border-rose-400 @enderror"
                                       required />
                                <span class="text-[11px] text-slate-500 mt-0.5 block">Fecha en que el objeto fue entregado físicamente a la biblioteca</span>
                                @error('fecha_recepcion')
                                    <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="hora_recepcion" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                    Hora real de recepción <span class="text-rose-600">*</span>
                                </label>
                                <input type="time"
                                       name="hora_recepcion"
                                       id="hora_recepcion"
                                       value="{{ old('hora_recepcion', $horaRecepcionDefault) }}"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-medium focus:border-[#691C32] focus:ring-1 focus:ring-[#691C32] @error('hora_recepcion') border-rose-400 @enderror"
                                       required />
                                <span class="text-[11px] text-slate-500 mt-0.5 block">Hora física aproximada de la recepción (formato 24 hrs)</span>
                                @error('hora_recepcion')
                                    <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Fila 2: Ubicación de resguardo asignada en biblioteca -->
                        <div>
                            <label for="ubicacion_resguardo" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Ubicación de resguardo asignada en biblioteca <span class="text-rose-600">*</span>
                            </label>
                            <input type="text"
                                   name="ubicacion_resguardo"
                                   id="ubicacion_resguardo"
                                   value="{{ old('ubicacion_resguardo') }}"
                                   placeholder="Ej. Estante A-3, Gaveta de electrónicos, Caja 2"
                                   maxlength="255"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-medium focus:border-[#691C32] focus:ring-1 focus:ring-[#691C32] @error('ubicacion_resguardo') border-rose-400 @enderror"
                                   required />
                            <span class="text-[11px] text-slate-500 mt-0.5 block">Espacio físico o gaveta específica donde el objeto quedará resguardado</span>
                            @error('ubicacion_resguardo')
                                <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Fila 3: Condición del objeto al momento de la entrega -->
                        <div>
                            <label for="condicion_objeto" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Condición del objeto al momento de la entrega <span class="text-rose-600">*</span>
                            </label>
                            <textarea name="condicion_objeto"
                                      id="condicion_objeto"
                                      rows="3"
                                      placeholder="Describe el estado físico del objeto al recibirlo (marcas de uso, rayones, funcionalidad aparente, accesorios incluidos, etc.)"
                                      maxlength="1000"
                                      class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-medium focus:border-[#691C32] focus:ring-1 focus:ring-[#691C32] @error('condicion_objeto') border-rose-400 @enderror"
                                      required>{{ old('condicion_objeto') }}</textarea>
                            <span class="text-[11px] text-slate-500 mt-0.5 block">Registro del estado físico visible para salvaguarda institucional</span>
                            @error('condicion_objeto')
                                <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Fila 4: Observaciones físicas (opcional) -->
                        <div>
                            <label for="observaciones" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Observaciones físicas adicionales <span class="text-xs text-slate-400 font-normal lowercase">(opcional)</span>
                            </label>
                            <textarea name="observaciones"
                                      id="observaciones"
                                      rows="2"
                                      placeholder="Cualquier nota adicional relevante sobre la entrega, el empaque o comentarios del autor"
                                      maxlength="1000"
                                      class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-medium focus:border-[#691C32] focus:ring-1 focus:ring-[#691C32] @error('observaciones') border-rose-400 @enderror">{{ old('observaciones') }}</textarea>
                            @error('observaciones')
                                <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>

                    <!-- BOTONES DE ACCIÓN DEL FORMULARIO -->
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex flex-col-reverse sm:flex-row items-center justify-end gap-3">
                        <a href="{{ route('admin.moderacion.show', $reporte->id) }}"
                           class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-white font-semibold text-xs transition-colors text-center">
                            Cancelar y volver
                        </a>

                        <button type="submit"
                                :disabled="!confirmado"
                                :class="!confirmado ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'bg-[#691C32] hover:bg-[#520B1D] active:bg-[#3B0715] shadow-xs cursor-pointer'"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl text-white font-bold text-xs sm:text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#691C32]">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Guardar recepción física</span>
                        </button>
                    </div>
                </div>

            </form>

        </div>

        <!-- MODAL PARA AMPLIAR FOTOGRAFÍA -->
        <div x-show="modalFotoOpen"
             x-cloak
             @keydown.escape.window="modalFotoOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs transition-opacity"
             role="dialog"
             aria-modal="true">
            <div class="relative max-w-4xl w-full bg-white rounded-2xl overflow-hidden shadow-2xl"
                 @click.away="modalFotoOpen = false">
                <div class="flex items-center justify-between p-4 border-b border-slate-200">
                    <h3 class="font-bold text-sm text-slate-800">
                        Fotografía de {{ $reporte->nombre ?? $reporte->titulo }}
                    </h3>
                    <button type="button" @click="modalFotoOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-4 bg-slate-900 flex items-center justify-center max-h-[75vh]">
                    <img :src="activeImg" alt="Fotografía ampliada" class="max-h-[70vh] max-w-full object-contain rounded-lg" />
                </div>
            </div>
        </div>

    </div>
</x-app-layout>

