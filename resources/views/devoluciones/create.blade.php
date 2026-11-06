<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-[#691C32] border border-rose-300">
                        Registro de Entrega
                    </span>
                    <span class="text-xs text-slate-400">&bull;</span>
                    <span class="text-xs font-semibold text-slate-500">Folio: {{ $publicacion->folio }}</span>
                </div>
                <h2 class="font-black text-2xl text-slate-800 leading-tight">
                    Registrar Devolución
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Documenta la entrega física del objeto al propietario para solicitar la verificación administrativa.
                </p>
            </div>
            <div>
                <a href="{{ route('reportes.mis-reportes') }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-xs">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Volver a Mis Reportes</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8 bg-slate-50/50 min-h-[calc(100vh-140px)]">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('info'))
                <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-sm font-semibold text-blue-800 flex items-center gap-3 shadow-2xs">
                    <svg class="w-5 h-5 text-blue-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('info') }}</span>
                </div>
            @endif

            {{-- Errores globales de validación (CA-10) --}}
            @if ($errors->any())
                <div class="p-4 sm:p-5 rounded-2xl bg-rose-50 border border-rose-300 shadow-2xs space-y-2">
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

            {{-- CA-02: Advertencia mandataria sobre entrega física realizada --}}
            <div class="p-4 sm:p-5 rounded-2xl bg-amber-50 border-2 border-amber-300 shadow-xs flex items-start gap-3.5">
                <div class="p-2.5 rounded-xl bg-amber-100 text-amber-900 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-black text-amber-950 uppercase tracking-wide">
                        Atención Importante
                    </h3>
                    <p class="text-sm font-bold text-amber-900 mt-0.5 leading-snug">
                        Registra esta devolución únicamente si ya entregaste físicamente el objeto.
                    </p>
                    <p class="text-xs text-amber-800/90 mt-1 leading-relaxed">
                        Una llamada telefónica, un mensaje de contacto, un acuerdo previo o una entrega programada no deben registrarse como devolución realizada. El registro quedará pendiente de verificación administrativa.
                    </p>
                </div>
            </div>

            {{-- Formulario principal --}}
            <form id="form-devolucion" method="POST" action="{{ route('devoluciones.store', $publicacion->id) }}" enctype="multipart/form-data" onsubmit="deshabilitarBotonEnvio()" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-sm space-y-6">
                @csrf

                {{-- CA-02: Identificación no editable del objeto --}}
                <div class="p-4 sm:p-5 bg-slate-50/80 rounded-2xl border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between pb-2.5 border-b border-slate-200/80">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-500">
                            Objeto bajo tu resguardo
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

                {{-- CA-03, CA-04: Datos del receptor --}}
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

                        {{-- Identificador institucional del receptor --}}
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

                {{-- Fecha y hora de entrega física automática --}}
                <div class="space-y-4 pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <span>2. Fecha y Hora Reales de Entrega</span>
                        </h3>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Automático del sistema</span>
                        </span>
                    </div>

                    <div class="p-4 sm:p-5 bg-emerald-50/60 rounded-2xl border border-emerald-200/90 text-xs text-emerald-950 space-y-2.5">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="font-bold text-sm">Registro simultáneo con la entrega en curso</span>
                        </div>
                        <p class="text-xs text-emerald-900 leading-relaxed font-medium">
                            La fecha y la hora de entrega física se sincronizan automáticamente con el momento exacto en que envías este formulario. No tienes que ingresar fecha ni hora manualmente.
                        </p>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 pt-1 font-mono text-xs font-black text-emerald-900">
                            <span>Momento institucional asignado al enviar:</span>
                            <span class="bg-white px-3 py-1.5 rounded-xl border border-emerald-300 shadow-2xs inline-flex items-center gap-2 text-slate-800" id="contenedor-reloj-vivo">
                                <svg class="w-3.5 h-3.5 text-emerald-600 animate-spin" style="animation-duration: 3s;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span id="texto-reloj-vivo">{{ now(\App\Models\Publicacion::TIMEZONE)->format('d/m/Y • h:i a') }}</span>
                            </span>
                        </div>
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
                                value="{{ old('fecha_entrega', now(\App\Models\Publicacion::TIMEZONE)->format('Y-m-d')) }}"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-slate-50 text-slate-700 font-medium cursor-default focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs @error('fecha_entrega') border-rose-500 ring-1 ring-rose-500 @enderror"
                                readonly
                            />
                            @error('fecha_entrega')
                                <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                            @else
                                <p class="text-[11px] text-slate-400 mt-1">
                                    Asignada automáticamente por el sistema al momento del intercambio.
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
                                value="{{ old('hora_entrega', now(\App\Models\Publicacion::TIMEZONE)->format('H:i')) }}"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-slate-50 text-slate-700 font-medium cursor-default focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs @error('hora_entrega') border-rose-500 ring-1 ring-rose-500 @enderror"
                                readonly
                            />
                            @error('hora_entrega')
                                <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                            @else
                                <p class="text-[11px] text-slate-400 mt-1">
                                    Se actualiza automáticamente al segundo exacto en que envías el registro.
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- CA-03, CA-04: Explicación de comprobación de propiedad --}}
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <label for="explicacion_propiedad" class="block text-xs font-bold text-slate-700">
                        Explicación de cómo se comprobó la propiedad <span class="text-rose-600 font-black">*</span>
                    </label>
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Describe qué información, características específicas o pruebas presentó el receptor y cómo permitieron relacionarlo con el objeto entregado. No te limites únicamente a señalar que lo solicitó.
                    </p>
                    <textarea
                        id="explicacion_propiedad"
                        name="explicacion_propiedad"
                        rows="4"
                        placeholder="Ejemplo: El receptor describió el contenido específico de la mochila, mostró su credencial institucional y desbloqueó el dispositivo frente a mí..."
                        class="w-full text-xs sm:text-sm rounded-2xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 leading-relaxed @error('explicacion_propiedad') border-rose-500 ring-1 ring-rose-500 @enderror"
                        required
                    >{{ old('explicacion_propiedad') }}</textarea>
                    @error('explicacion_propiedad')
                        <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- CA-03, CA-06: Fotografía de evidencia con vista previa interactiva --}}
                <div class="space-y-3 pt-2 border-t border-slate-100">
                    <label class="block text-xs font-bold text-slate-700">
                        Fotografía de evidencia de la entrega <span class="text-rose-600 font-black">*</span>
                    </label>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 space-y-1">
                        <p class="font-bold text-slate-700">Indicaciones para la fotografía:</p>
                        <ul class="list-disc list-inside text-[11px] text-slate-600 space-y-0.5">
                            <li>Debe documentar el objeto y a quien lo recibió durante la entrega física.</li>
                            <li>Formato admitido: JPG o PNG con peso máximo de hasta 2 MB.</li>
                            <li>No se exige una credencial legible ni contraseñas del dispositivo.</li>
                            <li>Permanece estrictamente privada entre tú y el personal de biblioteca.</li>
                        </ul>
                    </div>

                    {{-- Contenedor de subida y vista previa --}}
                    <div>
                        {{-- Mensaje de advertencia de fotografía si hubo error de validación (CA-10) --}}
                        @if ($errors->any() && ! $errors->has('fotografia'))
                            <div class="mb-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex items-center gap-2">
                                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>Por motivos de seguridad del navegador, es necesario seleccionar nuevamente la fotografía de evidencia antes de enviar.</span>
                            </div>
                        @endif

                        @error('fotografia')
                            <div class="mb-3 p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-center gap-2">
                                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span class="font-bold">{{ $message }}</span>
                            </div>
                        @enderror

                        <input
                            type="file"
                            id="fotografia"
                            name="fotografia"
                            class="hidden"
                            onchange="manejarVistaPrevia(event)"
                        />

                        {{-- Área clickeable de selección cuando no hay foto --}}
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
                                Haz clic aquí para seleccionar la fotografía de evidencia
                            </span>
                            <span class="text-[11px] text-slate-400 mt-0.5 block">
                                Formatos JPG o PNG de hasta 2 MB
                            </span>
                        </div>

                        {{-- Contenedor de Vista Previa y Sustitución (CA-06) --}}
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
                                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            <span>Sustituir fotografía</span>
                                        </button>
                                        <button
                                            type="button"
                                            onclick="eliminarFotoSeleccionada()"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition-colors"
                                        >
                                            <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            <span>Quitar</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Botones de acción --}}
                <div class="pt-4 border-t border-slate-100 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                    <a href="{{ route('reportes.mis-reportes') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold text-center transition-colors">
                        Cancelar y volver
                    </a>

                    <button
                        type="submit"
                        id="btn-submit-devolucion"
                        class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] text-white text-xs font-bold transition-all shadow-xs cursor-pointer"
                    >
                        <span>Registrar devolución</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Script de manejo de vista previa e indicaciones de Tarea 1 --}}
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
            actualizarRelojEntrega();
            const btn = document.getElementById('btn-submit-devolucion');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
                btn.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Procesando devolución...</span>
                `;
            }
        }

        function actualizarRelojEntrega() {
            const el = document.getElementById('texto-reloj-vivo');
            const fechaInput = document.getElementById('fecha_entrega');
            const horaInput = document.getElementById('hora_entrega');
            if (el) {
                const now = new Date();
                const dia = String(now.getDate()).padStart(2, '0');
                const mes = String(now.getMonth() + 1).padStart(2, '0');
                const anio = now.getFullYear();
                let horas = now.getHours();
                const minutos = String(now.getMinutes()).padStart(2, '0');
                const ampm = horas >= 12 ? 'p. m.' : 'a. m.';
                horas = horas % 12;
                horas = horas ? horas : 12;
                const horasStr = String(horas).padStart(2, '0');
                el.textContent = `${dia}/${mes}/${anio} • ${horasStr}:${minutos} ${ampm}`;

                if (fechaInput) fechaInput.value = `${anio}-${mes}-${dia}`;
                if (horaInput) horaInput.value = `${String(now.getHours()).padStart(2, '0')}:${minutos}`;
            }
        }
        setInterval(actualizarRelojEntrega, 1000);
        actualizarRelojEntrega();
    </script>
</x-app-layout>

