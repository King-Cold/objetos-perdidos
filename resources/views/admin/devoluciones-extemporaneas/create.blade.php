<x-app-layout>
    <div class="min-h-screen bg-[#F5F6F8] py-8 text-[#1F2937]">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Encabezado principal -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300 mb-1">
                        Procedimiento exclusivo de biblioteca
                    </span>
                    <h1 class="text-2xl font-bold text-[#1F2937] tracking-tight">
                        Registrar devolución reportada fuera de tiempo
                    </h1>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Documentación administrativa de entregas realizadas por el autor antes del vencimiento original pero reportadas posteriormente.
                    </p>
                </div>

                <div>
                    <a href="{{ route('admin.moderacion.show', $reporte->id) }}"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition shadow-2xs">
                        <span>&larr; Volver al reporte</span>
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

            {{-- Aviso normativo sobre fechas verdaderas y separación de responsables --}}
            <div class="p-4 sm:p-5 rounded-2xl bg-amber-50 border border-amber-300 shadow-2xs flex items-start gap-3.5">
                <div class="p-2 rounded-xl bg-amber-100 text-amber-900 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="space-y-1 text-xs">
                    <h2 class="text-sm font-black text-amber-950">
                        Registro con fechas verdaderas y responsables diferenciados
                    </h2>
                    <p class="text-amber-900 leading-relaxed font-medium">
                        Captura la información proporcionada por el autor y las aclaraciones obtenidas. La entrega física debe haber ocurrido <strong>antes del vencimiento original</strong>. El sistema conservará por separado la fecha y hora reales de entrega, el momento actual de captura, el autor que entregó el objeto y el bibliotecario responsable que lo documenta.
                    </p>
                </div>
            </div>

            <form id="form-devolucion-extemporanea"
                  method="POST"
                  action="{{ route('admin.devoluciones-extemporaneas.store', $reporte->id) }}"
                  enctype="multipart/form-data"
                  onsubmit="deshabilitarBotonEnvio()"
                  class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-xs space-y-6">
                @csrf

                <!-- Identificación no editable del reporte expirado -->
                <div class="p-4 sm:p-5 bg-slate-50/90 rounded-2xl border border-slate-200 space-y-3.5">
                    <div class="flex items-center justify-between pb-2.5 border-b border-slate-200/80">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-600">
                            Identificación del reporte expirado
                        </span>
                        <span class="px-2.5 py-0.5 rounded-lg bg-amber-50 border border-amber-200 text-[11px] font-black text-[#691C32]">
                            Datos originales protegidos
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <div class="p-3 rounded-xl bg-white border border-slate-200">
                            <span class="text-slate-500 font-semibold block uppercase text-[10px]">Folio del reporte</span>
                            <strong class="font-mono text-sm text-[#691C32] mt-0.5 block">{{ $reporte->folio }}</strong>
                        </div>

                        <div class="p-3 rounded-xl bg-white border border-slate-200">
                            <span class="text-slate-500 font-semibold block uppercase text-[10px]">Objeto</span>
                            <strong class="text-sm text-slate-900 mt-0.5 block truncate" title="{{ $reporte->nombre ?? $reporte->titulo }}">
                                {{ $reporte->nombre ?? $reporte->titulo }}
                            </strong>
                        </div>

                        <div class="p-3 rounded-xl bg-white border border-slate-200">
                            <span class="text-slate-500 font-semibold block uppercase text-[10px]">Autor que realizó la entrega</span>
                            <strong class="text-sm text-slate-900 mt-0.5 block truncate" title="{{ $reporte->user?->name ?? 'Autor' }}">
                                {{ $reporte->user?->name ?? 'Autor' }}
                            </strong>
                            @if ($reporte->user?->email)
                                <span class="text-[11px] text-slate-500 font-mono block truncate">{{ $reporte->user->email }}</span>
                            @endif
                        </div>

                        <div class="p-3 rounded-xl bg-rose-50/80 border border-rose-200">
                            <span class="text-rose-800 font-semibold block uppercase text-[10px]">Vencimiento original</span>
                            <strong class="text-sm text-rose-950 mt-0.5 block">
                                {{ $reporte->fecha_vencimiento ? $reporte->fecha_vencimiento->format('d/m/Y • H:i') : 'Expirado' }}
                            </strong>
                            <span class="text-[11px] text-rose-700 block">
                                Hallazgo: {{ $reporte->fecha_hora_hallazgo_texto }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 1. Motivo obligatorio del registro tardío -->
                <div class="space-y-2 pt-2">
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                        1. Motivo del Registro Tardío
                    </h3>
                    <label for="motivo_registro_tardio" class="block text-xs font-bold text-slate-700">
                        Motivo por el que la devolución se reportó después del vencimiento <span class="text-rose-600 font-black">*</span>
                    </label>
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Registra el motivo explicado por el autor y las aclaraciones obtenidas por biblioteca sobre por qué la entrega realizada antes del vencimiento no fue capturada a tiempo.
                    </p>
                    <textarea
                        id="motivo_registro_tardio"
                        name="motivo_registro_tardio"
                        rows="3"
                        placeholder="Ej. El autor entregó el objeto al propietario en el aula antes del vencimiento y tomó la evidencia fotográfica, pero olvidó capturar la devolución en la plataforma antes de que venciera el plazo..."
                        class="w-full text-xs sm:text-sm rounded-2xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 leading-relaxed @error('motivo_registro_tardio') border-rose-500 ring-1 ring-rose-500 @enderror"
                        required
                    >{{ old('motivo_registro_tardio') }}</textarea>
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
                                value="{{ old('receptor_nombre') }}"
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

                        <div>
                            <label for="receptor_identificador" class="block text-xs font-bold text-slate-700 mb-1">
                                Identificador institucional del receptor <span class="text-rose-600 font-black">*</span>
                            </label>
                            <input
                                type="text"
                                id="receptor_identificador"
                                name="receptor_identificador"
                                value="{{ old('receptor_identificador') }}"
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

                <!-- 3. Fecha y hora físicas reales de entrega y trazabilidad separada -->
                <div class="space-y-4 pt-4 border-t border-slate-100">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                            3. Fecha y Hora Reales de Entrega vs. Captura Actual
                        </h3>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-900 border border-amber-300">
                            <span>Momentos y responsables separados</span>
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
                                value="{{ old('fecha_entrega') }}"
                                @if ($minFechaEntrega) min="{{ $minFechaEntrega }}" @endif
                                @if ($maxFechaEntrega) max="{{ $maxFechaEntrega }}" @endif
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 @error('fecha_entrega') border-rose-500 ring-1 ring-rose-500 @enderror"
                                required
                            />
                            @error('fecha_entrega')
                                <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                            @else
                                <p class="text-[11px] text-slate-500 mt-1">
                                    Debe ubicarse entre el hallazgo ({{ $reporte->fecha_hallazgo_texto }}) y antes del vencimiento ({{ $reporte->fecha_vencimiento ? $reporte->fecha_vencimiento->format('d/m/Y') : '' }}).
                                </p>
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
                                value="{{ old('hora_entrega') }}"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 @error('hora_entrega') border-rose-500 ring-1 ring-rose-500 @enderror"
                                required
                            />
                            @error('hora_entrega')
                                <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                            @else
                                <p class="text-[11px] text-slate-500 mt-1">
                                    Hora verdadera en que ocurrió la entrega física antes de vencer el plazo ({{ $reporte->fecha_vencimiento ? $reporte->fecha_vencimiento->format('H:i') : '' }}).
                                </p>
                            @enderror
                        </div>
                    </div>

                    <!-- Resumen de registro separado (momento de captura actual y bibliotecario responsable) -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">
                                Momento actual de captura en sistema
                            </span>
                            <strong class="text-slate-900 font-mono text-xs sm:text-sm mt-0.5 block">
                                {{ now(\App\Models\Publicacion::TIMEZONE)->format('d/m/Y • H:i') }}
                            </strong>
                            <span class="text-[11px] text-slate-500">Se guarda por separado de la fecha real de entrega.</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">
                                Bibliotecario responsable que documenta
                            </span>
                            <strong class="text-slate-900 text-xs sm:text-sm mt-0.5 block">
                                {{ Auth::user()->name }}
                            </strong>
                            <span class="text-[11px] text-slate-500">Distinguido del autor que entregó el objeto.</span>
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
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Describe qué información, señas particulares o pruebas presentó el receptor ante el autor y cómo permitieron acreditar la propiedad del objeto entregado.
                    </p>
                    <textarea
                        id="explicacion_propiedad"
                        name="explicacion_propiedad"
                        rows="4"
                        placeholder="Ejemplo: El receptor describió el contenido específico del objeto, mostró su credencial institucional y comprobó características no visibles en la publicación..."
                        class="w-full text-xs sm:text-sm rounded-2xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 leading-relaxed @error('explicacion_propiedad') border-rose-500 ring-1 ring-rose-500 @enderror"
                        required
                    >{{ old('explicacion_propiedad') }}</textarea>
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
                        Fotografía de evidencia de la entrega física (JPG o PNG de hasta 2 MB) <span class="text-rose-600 font-black">*</span>
                    </label>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 space-y-1">
                        <p class="font-bold text-slate-700">Requisitos de la evidencia:</p>
                        <ul class="list-disc list-inside text-[11px] text-slate-600 space-y-0.5">
                            <li>Debe documentar la entrega física proporcionada por el autor.</li>
                            <li>Formatos admitidos: JPG o PNG con peso máximo de hasta 2 MB.</li>
                            <li>Permanece disponible únicamente para el autor original y el administrador/bibliotecario.</li>
                        </ul>
                    </div>

                    <div>
                        @if ($errors->any() && ! $errors->has('fotografia'))
                            <div class="mb-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex items-center gap-2">
                                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>Por motivos de seguridad del navegador, selecciona nuevamente la fotografía de evidencia antes de guardar.</span>
                            </div>
                        @endif

                        @error('fotografia')
                            <div class="mb-3 p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-center gap-2">
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
                            class="border-2 border-dashed border-slate-300 hover:border-[#691C32] rounded-2xl p-6 text-center cursor-pointer bg-slate-50/50 hover:bg-rose-50/20 transition-all group"
                        >
                            <div class="w-12 h-12 mx-auto mb-2 rounded-xl bg-white border border-slate-200 group-hover:border-rose-200 flex items-center justify-center text-slate-400 group-hover:text-[#691C32] transition-colors shadow-2xs">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 block">
                                Haz clic aquí para adjuntar la fotografía de evidencia
                            </span>
                            <span class="text-[11px] text-slate-400 mt-0.5 block">
                                Formatos JPG o PNG de hasta 2 MB
                            </span>
                        </div>

                        <div id="contenedor-preview" class="hidden mt-3 p-4 bg-slate-50 rounded-2xl border border-slate-200">
                            <div class="flex flex-col sm:flex-row items-center gap-4">
                                <img
                                    id="img-preview"
                                    src="#"
                                    alt="Vista previa de evidencia"
                                    class="w-32 h-32 sm:w-40 sm:h-40 rounded-xl object-cover border-2 border-slate-200 shadow-xs shrink-0"
                                />
                                <div class="flex-1 text-center sm:text-left space-y-2">
                                    <div>
                                        <span class="text-xs font-bold text-slate-800 block" id="nombre-archivo-preview">archivo.jpg</span>
                                        <span class="text-[11px] text-slate-400 block" id="peso-archivo-preview">0 KB</span>
                                    </div>
                                    <div class="flex items-center justify-center sm:justify-start gap-2 pt-1">
                                        <button
                                            type="button"
                                            onclick="document.getElementById('fotografia').click()"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors shadow-2xs"
                                        >
                                            <span>Sustituir fotografía</span>
                                        </button>
                                        <button
                                            type="button"
                                            onclick="eliminarFotoSeleccionada()"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition-colors"
                                        >
                                            <span>Quitar</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acciones del formulario -->
                <div class="pt-4 border-t border-slate-100 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                    <a href="{{ route('admin.moderacion.show', $reporte->id) }}"
                       class="px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold text-center transition-colors">
                        Cancelar y volver
                    </a>

                    <button
                        type="submit"
                        id="btn-submit-extemporanea"
                        class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-[#691C32] hover:bg-[#520B1D] text-white text-xs font-bold transition-all shadow-xs cursor-pointer"
                    >
                        <span>Registrar devolución fuera de tiempo</span>
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
            const btn = document.getElementById('btn-submit-extemporanea');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
                btn.innerHTML = '<span>Guardando registro...</span>';
            }
        }
    </script>
</x-app-layout>
