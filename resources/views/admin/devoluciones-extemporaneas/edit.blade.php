<x-app-layout>
    <div class="min-h-screen bg-[#F5F6F8] py-8 text-[#1F2937]">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Encabezado principal -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-orange-100 text-orange-900 border border-orange-300 mb-1">
                        Atención a corrección de devolución
                    </span>
                    <h1 class="text-2xl font-bold text-[#1F2937] tracking-tight">
                        Corregir devolución reportada fuera de tiempo
                    </h1>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Corrige la información o sustituye la evidencia a partir de las observaciones y reenvía el mismo evento a revisión.
                    </p>
                </div>

                <div>
                    <a href="{{ route('admin.devoluciones.show', $devolucion->id) }}"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition shadow-2xs">
                        <span>&larr; Volver a la devolución</span>
                    </a>
                </div>
            </div>

            {{-- Errores globales de validación --}}
            @if ($errors->any())
                <div class="p-4 sm:p-5 rounded-2xl bg-rose-50 border border-rose-300 shadow-2xs space-y-2" role="alert">
                    <div class="flex items-center gap-2 text-rose-900 font-black text-sm">
                        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span>Se encontraron errores en el formulario:</span>
                    </div>
                    <ul class="list-disc list-inside text-xs text-rose-800 space-y-1 font-medium pl-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Banner con el motivo de corrección solicitado --}}
            @if ($devolucion->motivo_correccion)
                <div class="p-4 sm:p-5 rounded-2xl bg-orange-50 border border-orange-300 shadow-2xs space-y-2">
                    <div class="flex items-center gap-2 text-orange-950 font-black text-sm">
                        <svg class="w-5 h-5 text-orange-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Observaciones que requieren corrección:</span>
                    </div>
                    <p class="text-xs text-orange-900 leading-relaxed font-medium pl-7 whitespace-pre-line">
                        {{ $devolucion->motivo_correccion }}
                    </p>
                    <div class="pl-7 text-[11px] text-orange-800">
                        Se conservará únicamente la última versión de los datos y de la fotografía de evidencia, manteniendo el historial de decisiones previas.
                    </div>
                </div>
            @endif

            <form id="form-editar-devolucion-extemporanea"
                  method="POST"
                  action="{{ route('admin.devoluciones-extemporaneas.update', $devolucion->id) }}"
                  enctype="multipart/form-data"
                  onsubmit="deshabilitarBotonEnvio()"
                  class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6">
                @csrf
                @method('PUT')

                <!-- Identificación no editable del reporte -->
                <div class="p-4 sm:p-5 bg-slate-50/90 rounded-2xl border border-slate-200 space-y-3.5">
                    <div class="flex items-center justify-between pb-2.5 border-b border-slate-200/80">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-600">
                            Identificación del reporte
                        </span>
                        <span class="px-2.5 py-0.5 rounded-lg bg-amber-50 border border-amber-200 text-[11px] font-black text-[#691C32]">
                            Datos del reporte protegidos
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <div class="p-3 rounded-xl bg-white border border-slate-200">
                            <span class="text-slate-500 font-semibold block uppercase text-[10px]">Folio del reporte</span>
                            <strong class="font-mono text-sm text-[#691C32] mt-0.5 block">{{ $reporte ? $reporte->folio : 'S/F' }}</strong>
                        </div>

                        <div class="p-3 rounded-xl bg-white border border-slate-200">
                            <span class="text-slate-500 font-semibold block uppercase text-[10px]">Objeto</span>
                            <strong class="text-sm text-slate-900 mt-0.5 block truncate" title="{{ $reporte ? ($reporte->nombre ?? $reporte->titulo) : 'Objeto' }}">
                                {{ $reporte ? ($reporte->nombre ?? $reporte->titulo) : 'Objeto' }}
                            </strong>
                        </div>

                        <div class="p-3 rounded-xl bg-white border border-slate-200">
                            <span class="text-slate-500 font-semibold block uppercase text-[10px]">Autor que realizó la entrega</span>
                            <strong class="text-sm text-slate-900 mt-0.5 block truncate" title="{{ $reporte?->user?->name ?? 'Autor' }}">
                                {{ $reporte?->user?->name ?? 'Autor' }}
                            </strong>
                            @if ($reporte?->user?->email)
                                <span class="text-[11px] text-slate-500 font-mono block truncate">{{ $reporte->user->email }}</span>
                            @endif
                        </div>

                        <div class="p-3 rounded-xl bg-rose-50/80 border border-rose-200">
                            <span class="text-rose-800 font-semibold block uppercase text-[10px]">Vencimiento original</span>
                            <strong class="text-sm text-rose-950 mt-0.5 block">
                                {{ $reporte && $reporte->fecha_vencimiento ? $reporte->fecha_vencimiento->format('d/m/Y • H:i') : 'Expirado' }}
                            </strong>
                            <span class="text-[11px] text-rose-700 block">
                                Hallazgo: {{ $reporte ? $reporte->fecha_hora_hallazgo_texto : 'No registrada' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 1. Motivo del registro tardío -->
                <div class="space-y-2 pt-2">
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                        1. Motivo del Registro Tardío
                    </h3>
                    <label for="motivo_registro_tardio" class="block text-xs font-bold text-slate-700">
                        Motivo por el que la devolución se reportó después del vencimiento <span class="text-rose-600 font-black">*</span>
                    </label>
                    <textarea
                        id="motivo_registro_tardio"
                        name="motivo_registro_tardio"
                        rows="3"
                        class="w-full text-xs sm:text-sm rounded-2xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 leading-relaxed @error('motivo_registro_tardio') border-rose-500 ring-1 ring-rose-500 @enderror"
                        required
                    >{{ old('motivo_registro_tardio', $devolucion->motivo_registro_tardio) }}</textarea>
                    @error('motivo_registro_tardio')
                        <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 2. Datos del Receptor -->
                <div class="space-y-4 pt-4 border-t border-slate-100">
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                        2. Datos del Receptor
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="receptor_nombre" class="block text-xs font-bold text-slate-700 mb-1">
                                Nombre completo del receptor <span class="text-rose-600 font-black">*</span>
                            </label>
                            <input
                                type="text"
                                id="receptor_nombre"
                                name="receptor_nombre"
                                value="{{ old('receptor_nombre', $devolucion->receptor_nombre) }}"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 @error('receptor_nombre') border-rose-500 ring-1 ring-rose-500 @enderror"
                                required
                            />
                            @error('receptor_nombre')
                                <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="receptor_identificador" class="block text-xs font-bold text-slate-700 mb-1">
                                Identificador institucional del receptor <span class="text-rose-600 font-black">*</span>
                            </label>
                            <input
                                type="text"
                                id="receptor_identificador"
                                name="receptor_identificador"
                                value="{{ old('receptor_identificador', $devolucion->receptor_identificador) }}"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 @error('receptor_identificador') border-rose-500 ring-1 ring-rose-500 @enderror"
                                required
                            />
                            @error('receptor_identificador')
                                <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- 3. Fecha y hora físicas reales de entrega -->
                @php
                    $fechaEntregaFormateada = '';
                    if ($devolucion->fecha_entrega) {
                        $fechaEntregaFormateada = $devolucion->fecha_entrega instanceof \Carbon\Carbon
                            ? $devolucion->fecha_entrega->format('Y-m-d')
                            : substr((string)$devolucion->fecha_entrega, 0, 10);
                    }
                    $horaEntregaFormateada = '';
                    if ($devolucion->hora_entrega) {
                        $horaEntregaFormateada = substr((string)$devolucion->hora_entrega, 0, 5);
                    }
                @endphp
                <div class="space-y-4 pt-4 border-t border-slate-100">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                            3. Fecha y Hora Reales de Entrega Física
                        </h3>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-900 border border-amber-300">
                            <span>Debe ser anterior al vencimiento original</span>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="fecha_entrega" class="block text-xs font-bold text-slate-700 mb-1">
                                Fecha real de entrega física <span class="text-rose-600 font-black">*</span>
                            </label>
                            <input
                                type="date"
                                id="fecha_entrega"
                                name="fecha_entrega"
                                value="{{ old('fecha_entrega', $fechaEntregaFormateada) }}"
                                @if ($minFechaEntrega) min="{{ $minFechaEntrega }}" @endif
                                @if ($maxFechaEntrega) max="{{ $maxFechaEntrega }}" @endif
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 @error('fecha_entrega') border-rose-500 ring-1 ring-rose-500 @enderror"
                                required
                            />
                            @error('fecha_entrega')
                                <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="hora_entrega" class="block text-xs font-bold text-slate-700 mb-1">
                                Hora real de entrega física <span class="text-rose-600 font-black">*</span>
                            </label>
                            <input
                                type="time"
                                id="hora_entrega"
                                name="hora_entrega"
                                value="{{ old('hora_entrega', $horaEntregaFormateada) }}"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 @error('hora_entrega') border-rose-500 ring-1 ring-rose-500 @enderror"
                                required
                            />
                            @error('hora_entrega')
                                <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- 4. Explicación de comprobación de propiedad -->
                <div class="space-y-2 pt-4 border-t border-slate-100">
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                        4. Comprobación de Propiedad
                    </h3>
                    <label for="explicacion_propiedad" class="block text-xs font-bold text-slate-700">
                        Explicación de cómo se comprobó la propiedad <span class="text-rose-600 font-black">*</span>
                    </label>
                    <textarea
                        id="explicacion_propiedad"
                        name="explicacion_propiedad"
                        rows="4"
                        class="w-full text-xs sm:text-sm rounded-2xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 leading-relaxed @error('explicacion_propiedad') border-rose-500 ring-1 ring-rose-500 @enderror"
                        required
                    >{{ old('explicacion_propiedad', $devolucion->explicacion_propiedad) }}</textarea>
                    @error('explicacion_propiedad')
                        <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- 5. Fotografía de evidencia -->
                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                        5. Fotografía de Evidencia
                    </h3>
                    <label class="block text-xs font-bold text-slate-700">
                        Fotografía de evidencia (opcional al corregir; sustituye la anterior)
                    </label>

                    {{-- Vista de la fotografía actual guardada --}}
                    @if ($devolucion->hasEvidenciaDisponible())
                        <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col sm:flex-row items-center gap-4">
                            <img src="{{ route('devoluciones.evidencia', $devolucion->id) }}"
                                 alt="Fotografía actual de evidencia"
                                 class="w-24 h-24 sm:w-28 sm:h-28 object-cover rounded-xl border border-slate-300 shadow-2xs shrink-0" />
                            <div class="space-y-1 text-xs text-slate-600 text-center sm:text-left">
                                <span class="font-bold text-slate-800 block">Fotografía actual registrada:</span>
                                <span class="font-mono text-[11px] text-slate-500 block truncate max-w-xs">{{ $devolucion->fotografia_nombre_original ?? 'evidencia_actual.jpg' }}</span>
                                <p class="text-[11px] text-slate-500 leading-tight">
                                    Se mantendrá esta evidencia a menos que selecciones una nueva fotografía. Al subir una nueva, se eliminará la anterior del almacenamiento y se conservará únicamente la última versión.
                                </p>
                            </div>
                        </div>
                    @endif

                    @error('fotografia')
                        <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span class="font-bold">{{ $message }}</span>
                        </div>
                    @enderror

                    <input
                        type="file"
                        id="fotografia"
                        name="fotografia"
                        accept=".jpg,.png"
                        class="hidden"
                        onchange="manejarVistaPrevia(event)"
                    />

                    <div
                        id="area-subida"
                        onclick="document.getElementById('fotografia').click()"
                        class="border-2 border-dashed border-slate-300 hover:border-[#691C32] rounded-2xl p-5 text-center cursor-pointer bg-slate-50/50 hover:bg-rose-50/20 transition-all group"
                    >
                        <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-white border border-slate-200 group-hover:border-rose-200 flex items-center justify-center text-slate-400 group-hover:text-[#691C32] transition-colors shadow-2xs">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span class="text-xs font-bold text-slate-700 block">
                            Haz clic aquí si deseas sustituir la fotografía de evidencia
                        </span>
                        <span class="text-[11px] text-slate-400 mt-0.5 block">
                            JPG o PNG de hasta 2 MB
                        </span>
                    </div>

                    <div id="contenedor-preview" class="hidden p-4 bg-slate-50 rounded-2xl border border-slate-200">
                        <div class="flex flex-col sm:flex-row items-center gap-4">
                            <img
                                id="img-preview"
                                src="#"
                                alt="Nueva vista previa"
                                class="w-28 h-28 rounded-xl object-cover border-2 border-slate-200 shadow-xs shrink-0"
                            />
                            <div class="flex-1 text-center sm:text-left space-y-2">
                                <div>
                                    <span class="text-xs font-bold text-slate-800 block" id="nombre-archivo-preview">nueva_foto.jpg</span>
                                    <span class="text-[11px] text-slate-400 block" id="peso-archivo-preview">0 KB</span>
                                </div>
                                <div class="flex items-center justify-center sm:justify-start gap-2 pt-1">
                                    <button
                                        type="button"
                                        onclick="document.getElementById('fotografia').click()"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors shadow-2xs"
                                    >
                                        <span>Seleccionar otra</span>
                                    </button>
                                    <button
                                        type="button"
                                        onclick="eliminarFotoSeleccionada()"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition-colors"
                                    >
                                        <span>Conservar la actual</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acciones del formulario -->
                <div class="pt-4 border-t border-slate-100 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                    <a href="{{ route('admin.devoluciones.show', $devolucion->id) }}"
                       class="px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold text-center transition-colors">
                        Cancelar y volver
                    </a>

                    <button
                        type="submit"
                        id="btn-submit-corregir"
                        class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition-all shadow-xs cursor-pointer"
                    >
                        <span>Guardar corrección y reenviar a revisión</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script>
        function manejarVistaPrevia(event) {
            const input = event.target;
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();

                reader.onload = function(e) {
                    const img = document.getElementById('img-preview');
                    img.src = e.target.result;
                    document.getElementById('nombre-archivo-preview').textContent = file.name;
                    document.getElementById('peso-archivo-preview').textContent = (file.size / 1024).toFixed(1) + ' KB';

                    document.getElementById('contenedor-preview').classList.remove('hidden');
                    document.getElementById('area-subida').classList.add('hidden');
                };

                reader.readAsDataURL(file);
            }
        }

        function eliminarFotoSeleccionada() {
            const input = document.getElementById('fotografia');
            input.value = '';
            document.getElementById('img-preview').src = '#';
            document.getElementById('contenedor-preview').classList.add('hidden');
            document.getElementById('area-subida').classList.remove('hidden');
        }

        function deshabilitarBotonEnvio() {
            const btn = document.getElementById('btn-submit-corregir');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
                btn.innerHTML = '<span>Reenviando a revisión...</span>';
            }
        }
    </script>
</x-app-layout>

