<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-orange-100 text-orange-900 border border-orange-300">
                        Corrección de Devolución
                    </span>
                    <span class="text-xs text-slate-400">&bull;</span>
                    <span class="text-xs font-semibold text-slate-500">Folio: {{ $publicacion->folio }}</span>
                </div>
                <h2 class="font-black text-xl sm:text-2xl text-slate-800 leading-tight">
                    Corregir devolución
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Atiende las observaciones del administrador y reenvía la entrega física para verificación
                </p>
            </div>
            <div>
                <a href="{{ route('devoluciones.show', $devolucion->id) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                    <span aria-hidden="true">&larr;</span>
                    <span>Volver al seguimiento</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-50/50 min-h-[calc(100vh-140px)]">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Errores de validación generales --}}
            @if ($errors->any())
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1 shadow-xs" role="alert">
                    <span class="font-bold block">Por favor corrige los siguientes errores antes de reenviar:</span>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- HU-12 Tarea 3: Banner con el Motivo Vigente de Corrección --}}
            @if (!empty($devolucion->motivo_correccion))
                <div class="p-5 sm:p-6 bg-orange-50/90 rounded-3xl border-2 border-orange-300 space-y-3 shadow-xs">
                    <div class="flex items-center gap-2 text-orange-950 font-black text-sm">
                        <svg class="w-5 h-5 text-orange-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Observación requerida por el personal administrativo</span>
                    </div>

                    <div class="space-y-1">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-orange-900 block">
                            Motivo vigente de la corrección:
                        </span>
                        <div class="p-4 bg-white rounded-2xl border border-orange-200 text-xs sm:text-sm text-orange-950 font-medium whitespace-pre-line leading-relaxed shadow-2xs">
                            {{ $devolucion->motivo_correccion }}
                        </div>
                    </div>

                    <p class="text-xs text-orange-900 leading-relaxed font-medium">
                        Modifica los campos necesarios a continuación. Si la observación requiere otra evidencia fotográfica, puedes seleccionarla en la sección correspondiente; si la fotografía actual es suficiente, se conservará automáticamente.
                    </p>
                </div>
            @endif

            {{-- Formulario de Corrección y Reenvío --}}
            <form id="form-corregir-devolucion" method="POST" action="{{ route('devoluciones.update', $devolucion->id) }}" enctype="multipart/form-data" onsubmit="deshabilitarBotonReenvio()" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-sm space-y-6">
                @csrf
                @method('PUT')

                {{-- Identificación no editable del objeto --}}
                <div class="p-4 sm:p-5 bg-slate-50/80 rounded-2xl border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between pb-2.5 border-b border-slate-200/80">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-500">
                            Objeto de la entrega
                        </span>
                        <span class="px-2.5 py-0.5 rounded-lg bg-amber-50 border border-amber-200 text-xs font-black text-[#691C32]">
                            No modificable
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <span class="block text-slate-400 font-semibold mb-0.5">Folio del reporte:</span>
                            <div class="text-sm font-black text-slate-800 bg-white px-3 py-2 rounded-xl border border-slate-200">
                                {{ $publicacion->folio }}
                            </div>
                        </div>
                        <div>
                            <span class="block text-slate-400 font-semibold mb-0.5">Nombre del objeto:</span>
                            <div class="text-sm font-black text-slate-800 bg-white px-3 py-2 rounded-xl border border-slate-200">
                                {{ $publicacion->nombre ?? $publicacion->titulo }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 1. Datos del Receptor --}}
                <div class="space-y-4 pt-2">
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <span>1. Datos del Receptor</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Nombre completo del receptor --}}
                        <div>
                            <label for="receptor_nombre" class="block text-xs font-bold text-slate-700 mb-1">
                                Nombre completo del receptor <span class="text-rose-600 font-black">*</span>
                            </label>
                            <input
                                type="text"
                                id="receptor_nombre"
                                name="receptor_nombre"
                                value="{{ old('receptor_nombre', $devolucion->receptor_nombre) }}"
                                placeholder="Ej. Juan Pérez García"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 @error('receptor_nombre') border-rose-500 ring-1 ring-rose-500 @enderror"
                                required
                            />
                            @error('receptor_nombre')
                                <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                            @else
                                <p class="text-[11px] text-slate-400 mt-1">Nombre y apellidos de quien recibió físicamente el objeto.</p>
                            @enderror
                        </div>

                        {{-- Identificador institucional del receptor --}}
                        <div>
                            <label for="receptor_identificador" class="block text-xs font-bold text-slate-700 mb-1">
                                Identificador institucional del receptor <span class="text-rose-600 font-black">*</span>
                            </label>
                            <input
                                type="text"
                                id="receptor_identificador"
                                name="receptor_identificador"
                                value="{{ old('receptor_identificador', $devolucion->receptor_identificador) }}"
                                placeholder="Ej. 19070045 o DOC-1042"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 @error('receptor_identificador') border-rose-500 ring-1 ring-rose-500 @enderror"
                                required
                            />
                            @error('receptor_identificador')
                                <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                            @else
                                <p class="text-[11px] text-slate-500 mt-1 leading-tight">
                                    Admite matrícula de estudiante o identificador institucional de docente/empleado.
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- 2. Fecha y hora de entrega física --}}
                <div class="space-y-4 pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <span>2. Fecha y Hora Reales de Entrega</span>
                        </h3>
                        <span class="text-[11px] text-slate-500 font-semibold">
                            Momento de entrega física
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="fecha_entrega" class="block text-xs font-bold text-slate-700 mb-1">
                                Fecha real de entrega <span class="text-rose-600 font-black">*</span>
                            </label>
                            <input
                                type="date"
                                id="fecha_entrega"
                                name="fecha_entrega"
                                value="{{ old('fecha_entrega', $devolucion->fecha_entrega ? ($devolucion->fecha_entrega instanceof \Carbon\Carbon ? $devolucion->fecha_entrega->format('Y-m-d') : substr((string) $devolucion->fecha_entrega, 0, 10)) : $maxFechaEntrega) }}"
                                max="{{ $maxFechaEntrega }}"
                                @if ($minFechaEntrega) min="{{ $minFechaEntrega }}" @endif
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 @error('fecha_entrega') border-rose-500 ring-1 ring-rose-500 @enderror"
                                required
                            />
                            @error('fecha_entrega')
                                <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                            @else
                                <p class="text-[11px] text-slate-400 mt-1">
                                    No puede ser una fecha futura ni anterior al hallazgo.
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label for="hora_entrega" class="block text-xs font-bold text-slate-700 mb-1">
                                Hora real de entrega <span class="text-rose-600 font-black">*</span>
                            </label>
                            <input
                                type="time"
                                id="hora_entrega"
                                name="hora_entrega"
                                value="{{ old('hora_entrega', substr((string) $devolucion->hora_entrega, 0, 5)) }}"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 @error('hora_entrega') border-rose-500 ring-1 ring-rose-500 @enderror"
                                required
                            />
                            @error('hora_entrega')
                                <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                            @else
                                <p class="text-[11px] text-slate-400 mt-1">
                                    Hora en que se entregó físicamente el objeto.
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- 3. Explicación de comprobación de propiedad --}}
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <label for="explicacion_propiedad" class="block text-xs font-bold text-slate-700">
                        Explicación de cómo se comprobó la propiedad <span class="text-rose-600 font-black">*</span>
                    </label>
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Describe qué información, características específicas o pruebas presentó el receptor y cómo permitieron relacionarlo con el objeto entregado.
                    </p>
                    <textarea
                        id="explicacion_propiedad"
                        name="explicacion_propiedad"
                        rows="4"
                        placeholder="Ejemplo: El receptor describió el contenido específico de la mochila, mostró su credencial institucional y desbloqueó el dispositivo frente a mí..."
                        class="w-full text-xs sm:text-sm rounded-2xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 leading-relaxed @error('explicacion_propiedad') border-rose-500 ring-1 ring-rose-500 @enderror"
                        required
                    >{{ old('explicacion_propiedad', $devolucion->explicacion_propiedad) }}</textarea>
                    @error('explicacion_propiedad')
                        <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- 4. Fotografía de Evidencia (Sustitución opcional segura) --}}
                <div class="space-y-4 pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-700">
                            Fotografía de evidencia de la entrega física
                        </label>
                        <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                            Opcional si conservas la actual
                        </span>
                    </div>

                    {{-- Visualización de fotografía vigente --}}
                    <div class="p-4 bg-slate-50/90 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span>Fotografía vigente actual</span>
                            </span>
                            <span class="text-[11px] text-slate-500 font-medium">
                                Se conservará si no seleccionas otra
                            </span>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-4">
                            <div class="relative rounded-xl overflow-hidden border border-slate-200 bg-white shrink-0 shadow-2xs">
                                <img
                                    src="{{ route('devoluciones.evidencia', $devolucion->id) }}"
                                    alt="Fotografía vigente actual"
                                    class="w-28 h-28 object-cover"
                                />
                            </div>
                            <div class="space-y-1 text-center sm:text-left text-xs">
                                <span class="font-bold text-slate-900 block truncate max-w-xs">
                                    {{ $devolucion->fotografia_nombre_original ?? 'evidencia_entrega.jpg' }}
                                </span>
                                <span class="text-[11px] text-slate-500 block">
                                    {{ $devolucion->fotografia_tamano_bytes ? round($devolucion->fotografia_tamano_bytes / 1024, 1) . ' KB' : 'Formato válido' }}
                                </span>
                                <p class="text-[11px] text-slate-400 leading-tight">
                                    Esta es la evidencia registrada actualmente. Si la observación del administrador no solicitó cambiarla, déjala intacta.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Selector de nueva fotografía para sustitución --}}
                    <div class="space-y-2">
                        <label for="fotografia" class="block text-xs font-bold text-slate-700">
                            Sustituir fotografía por una nueva (opcional):
                        </label>

                        <div
                            id="area-subida"
                            class="border-2 border-dashed border-slate-300 hover:border-[#691C32] rounded-2xl p-5 text-center cursor-pointer transition-colors bg-white group"
                            onclick="document.getElementById('fotografia').click()"
                        >
                            <input
                                type="file"
                                id="fotografia"
                                name="fotografia"
                                accept="image/jpeg,image/png,image/jpg"
                                class="hidden"
                                onchange="mostrarPreviewNuevaFoto(this)"
                            />

                            <div class="space-y-1.5">
                                <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 group-hover:bg-rose-50 text-slate-400 group-hover:text-[#691C32] flex items-center justify-center transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="text-xs font-bold text-slate-700">
                                    <span>Haz clic para seleccionar una nueva fotografía</span>
                                </div>
                                <p class="text-[11px] text-slate-400">
                                    Archivos JPG o PNG de hasta 2 MB. La fotografía anterior se sustituirá únicamente al guardar.
                                </p>
                            </div>
                        </div>

                        {{-- Previsualización de la nueva fotografía seleccionada --}}
                        <div id="contenedor-preview" class="hidden p-4 bg-emerald-50/70 rounded-2xl border border-emerald-300 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black uppercase tracking-wider text-emerald-950 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>Nueva fotografía seleccionada para sustitución</span>
                                </span>
                                <button
                                    type="button"
                                    onclick="restaurarFotoVigente()"
                                    class="text-xs font-bold text-rose-600 hover:text-rose-800 underline cursor-pointer"
                                >
                                    Deshacer cambio (conservar foto vigente)
                                </button>
                            </div>

                            <div class="flex items-center gap-4">
                                <div class="w-24 h-24 rounded-xl overflow-hidden border border-emerald-200 bg-white shrink-0">
                                    <img id="img-preview" src="#" alt="Previsualización de nueva evidencia" class="w-full h-full object-cover" />
                                </div>
                                <div class="text-xs space-y-1">
                                    <span id="nombre-archivo-preview" class="font-bold text-slate-900 block truncate max-w-xs"></span>
                                    <span id="peso-archivo-preview" class="text-slate-500 block text-[11px]"></span>
                                    <span class="text-[11px] font-semibold text-emerald-800 block">
                                        ✓ Se reemplazará la evidencia actual al pulsar «Guardar y reenviar».
                                    </span>
                                </div>
                            </div>
                        </div>

                        @error('fotografia')
                            <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Acciones del formulario: Cancelar y Guardar y reenviar --}}
                <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <a
                        href="{{ route('devoluciones.show', $devolucion->id) }}"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold text-center transition-colors shadow-2xs"
                    >
                        Cancelar edición sin guardar
                    </a>

                    <button
                        type="submit"
                        id="btn-submit-reenvio"
                        class="w-full sm:w-auto px-7 py-3 rounded-xl bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] text-white text-xs sm:text-sm font-extrabold shadow-sm transition-all active:scale-[0.98] cursor-pointer inline-flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Guardar y reenviar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Script para previsualización dinámica de la nueva fotografía --}}
    <script>
        function mostrarPreviewNuevaFoto(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];

                if (file.size > 2 * 1024 * 1024) {
                    alert('El archivo supera los 2 MB permitidos. Por favor selecciona una imagen más liviana.');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('img-preview').src = e.target.result;
                    document.getElementById('nombre-archivo-preview').textContent = file.name;
                    document.getElementById('peso-archivo-preview').textContent = (file.size / 1024).toFixed(1) + ' KB';

                    document.getElementById('contenedor-preview').classList.remove('hidden');
                    document.getElementById('area-subida').classList.add('hidden');
                };

                reader.readAsDataURL(file);
            }
        }

        function restaurarFotoVigente() {
            const input = document.getElementById('fotografia');
            input.value = '';
            document.getElementById('img-preview').src = '#';
            document.getElementById('contenedor-preview').classList.add('hidden');
            document.getElementById('area-subida').classList.remove('hidden');
        }

        function deshabilitarBotonReenvio() {
            const btn = document.getElementById('btn-submit-reenvio');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
                btn.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Guardando y reenviando...</span>
                `;
            }
        }
    </script>
</x-app-layout>

