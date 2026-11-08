<x-app-layout>
    <div class="min-h-screen bg-[#F5F6F8] py-8 text-[#1F2937]"
         x-data="{
             activeImg: '{{ $fotografiaPrincipal ? route('fotografias.show', $fotografiaPrincipal->id) : '' }}',
             activeId: {{ $fotografiaPrincipal ? $fotografiaPrincipal->id : 'null' }},
             modalFotoOpen: false,
             modalConfirmarOpen: false,
             procesando: false,
             confirmarEntrega() {
                 const form = document.getElementById('form-devolucion-biblioteca');
                 if (form.checkValidity()) {
                     this.modalConfirmarOpen = true;
                 } else {
                     form.reportValidity();
                 }
             },
             ejecutarEnvio() {
                 this.procesando = true;
                 document.getElementById('form-devolucion-biblioteca').submit();
             }
         }">
        <div class="max-w-[1120px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- 1. ENCABEZADO Y RETORNO -->
            <div class="space-y-2">
                <div>
                    <a href="{{ route('admin.inventario.show', $recepcion?->id ?? $reporte->id) }}"
                       class="inline-flex items-center gap-1.5 text-xs sm:text-[13px] font-medium text-[#64748B] hover:text-[#691C32] transition-colors focus:outline-none focus:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Volver al resguardo de biblioteca</span>
                    </a>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#691C32] text-white">
                                Gestión de biblioteca
                            </span>
                            <span class="text-xs text-slate-400">&bull;</span>
                            <span class="text-xs font-semibold text-slate-500">Folio: <strong class="font-mono text-[#691C32]">{{ $reporte->folio }}</strong></span>
                        </div>
                        <h1 class="text-2xl sm:text-[26px] font-bold text-[#1F2937] tracking-tight leading-tight">
                            Registrar devolución desde biblioteca
                        </h1>
                        <p class="text-xs sm:text-[13px] font-medium text-[#64748B] mt-0.5">
                            Comprobación presencial de propiedad y formalización de entrega al dueño del objeto en resguardo.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 flex-wrap">
                        @if ($recepcion && $recepcion->isAnualVencido())
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-900 border border-rose-300">
                                Resguardo vencido
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-900 border border-emerald-300">
                                Bajo resguardo
                            </span>
                        @endif

                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-300">
                            En biblioteca
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
                        <span class="font-bold block mb-1">Por favor verifica los campos obligatorios del registro:</span>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <!-- SECCIÓN 1: IDENTIFICACIÓN DEL OBJETO (CA-02: Folio, Nombre, Fotos y Ubicación Interna no editable) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-[#691C32] text-white flex items-center justify-center font-bold text-xs">
                            1
                        </span>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">
                            Identificación del objeto bajo resguardo
                        </h2>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-lg bg-amber-50 border border-amber-200 text-xs font-bold text-[#691C32]">
                        Objeto fijo / No modificable
                    </span>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                        
                        <!-- Columna Izquierda: Fotografías de referencia (CA-02) -->
                        <div class="lg:col-span-5 space-y-3">
                            <span class="text-slate-400 font-bold block uppercase text-[10px]">Fotografías de referencia del objeto</span>

                            <div class="space-y-3">
                                <!-- Imagen Principal Interactiva -->
                                <div class="relative aspect-4/3 rounded-xl overflow-hidden bg-slate-100 border border-slate-200 group flex items-center justify-center">
                                    @if ($fotografiaPrincipal)
                                        <img :src="activeImg"
                                             alt="{{ $reporte->nombre ?? $reporte->titulo }}"
                                             class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105" />
                                        <button type="button"
                                                @click="modalFotoOpen = true"
                                                class="absolute bottom-2.5 right-2.5 px-2.5 py-1.5 rounded-lg bg-black/70 hover:bg-black/90 text-white text-[11px] font-semibold flex items-center gap-1.5 backdrop-blur-xs transition-colors"
                                                title="Ampliar fotografía">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                            </svg>
                                            <span>Ampliar</span>
                                        </button>
                                    @else
                                        <div class="flex flex-col items-center justify-center text-slate-400 p-6 text-center">
                                            <svg class="w-12 h-12 mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <span class="text-xs">Sin imagen registrada</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Miniaturas Adicionales -->
                                @if ($reporte->fotografias->count() > 1)
                                    <div class="flex items-center gap-2 overflow-x-auto pb-1">
                                        @foreach ($reporte->fotografias as $foto)
                                            <button type="button"
                                                    @click="activeImg = '{{ route('fotografias.show', $foto->id) }}'; activeId = {{ $foto->id }}"
                                                    :class="activeId === {{ $foto->id }} ? 'ring-2 ring-[#691C32] border-[#691C32]' : 'border-slate-200 opacity-80 hover:opacity-100'"
                                                    class="relative w-14 h-14 rounded-lg overflow-hidden border shrink-0 transition-all bg-slate-100">
                                                <img src="{{ route('fotografias.show', $foto->id) }}"
                                                     alt="Miniatura {{ $loop->iteration }}"
                                                     class="w-full h-full object-cover" />
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Columna Derecha: Folio, Nombre, Ubicación Interna y Datos del Resguardo (CA-02) -->
                        <div class="lg:col-span-7 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                <div>
                                    <span class="text-slate-400 font-bold block uppercase text-[10px] mb-1">Folio institucional</span>
                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 font-mono font-bold text-sm text-[#691C32]">
                                        {{ $reporte->folio }}
                                    </div>
                                </div>

                                <div>
                                    <span class="text-slate-400 font-bold block uppercase text-[10px] mb-1">Nombre del objeto</span>
                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 font-bold text-sm text-slate-800 truncate" title="{{ $reporte->nombre ?? $reporte->titulo }}">
                                        {{ $reporte->nombre ?? $reporte->titulo }}
                                    </div>
                                </div>

                                <!-- CA-02: Ubicación interna del objeto bajo resguardo -->
                                <div class="sm:col-span-2">
                                    <span class="text-slate-400 font-bold block uppercase text-[10px] mb-1">Ubicación interna en biblioteca</span>
                                    <div class="p-3 rounded-xl bg-amber-50/70 border border-amber-200 flex items-center gap-2.5 text-amber-950 font-bold text-xs sm:text-sm">
                                        <svg class="w-4 h-4 text-[#691C32] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                        <span id="ubicacion-interna-objeto">{{ $recepcion?->ubicacion_resguardo ?? 'Módulo central de resguardo de biblioteca' }}</span>
                                    </div>
                                </div>

                                <div>
                                    <span class="text-slate-400 font-bold block uppercase text-[10px] mb-1">Fecha de recepción física</span>
                                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 font-medium text-slate-700">
                                        {{ $recepcion?->fecha_recepcion ? $recepcion->fecha_recepcion->format('d/m/Y') : 'Registrada en resguardo' }}
                                        @if ($recepcion?->hora_recepcion)
                                            <span class="text-slate-400 text-[11px]">({{ substr($recepcion->hora_recepcion, 0, 5) }} hrs)</span>
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <span class="text-slate-400 font-bold block uppercase text-[10px] mb-1">Límite de resguardo anual</span>
                                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 font-medium text-slate-700">
                                        {{ $recepcion?->fecha_limite_anual ? $recepcion->fecha_limite_anual->format('d/m/Y') : '1 año natural' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- SECCIÓN 2: FORMULARIO PRIVADO DE DEVOLUCIÓN DESDE BIBLIOTECA (CA-03, CA-04) -->
            <form id="form-devolucion-biblioteca"
                  method="POST"
                  action="{{ route('admin.devoluciones-biblioteca.store', $reporte->id) }}"
                  enctype="multipart/form-data"
                  class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden space-y-6">
                @csrf

                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-[#691C32] text-white flex items-center justify-center font-bold text-xs">
                            2
                        </span>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">
                            Datos de la entrega y comprobación de propiedad
                        </h2>
                    </div>
                    <span class="text-xs font-semibold text-slate-500">
                        Campos obligatorios <span class="text-rose-600">*</span>
                    </span>
                </div>

                <div class="p-6 space-y-6">

                    <!-- CA-04: DATOS DEL RECEPTOR (Color Azul Institucional / Identidad) -->
                    <div class="p-5 rounded-2xl bg-blue-50/40 border border-blue-200/80 shadow-2xs space-y-4">
                        <div class="flex items-center gap-2.5 pb-2.5 border-b border-blue-200/70">
                            <span class="w-7 h-7 rounded-lg bg-blue-600 text-white flex items-center justify-center font-black text-xs shadow-xs ring-2 ring-blue-200">
                                A
                            </span>
                            <div>
                                <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-blue-950">
                                    Datos del receptor
                                </h3>
                                <p class="text-[11px] text-blue-700/80 font-medium">Acreditación e identificación de la persona receptora</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Nombre completo del receptor -->
                            <div>
                                <label for="receptor_nombre" class="block text-xs font-bold text-slate-700 mb-1">
                                    Nombre completo del receptor <span class="text-rose-600 font-bold">*</span>
                                </label>
                                <input type="text"
                                       id="receptor_nombre"
                                       name="receptor_nombre"
                                       value="{{ old('receptor_nombre') }}"
                                       placeholder="Ej. Juan Carlos Pérez López"
                                       class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-blue-600 focus:ring focus:ring-blue-200/50 shadow-2xs font-medium text-slate-800 @error('receptor_nombre') border-rose-500 ring-1 ring-rose-500 @enderror"
                                       required />
                                @error('receptor_nombre')
                                    <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                                @else
                                    <p class="text-[11px] text-slate-400 mt-1">Nombre y apellidos completos de la persona que se presenta a recoger el objeto.</p>
                                @enderror
                            </div>

                            <!-- Identificador institucional del receptor -->
                            <div>
                                <label for="receptor_identificador" class="block text-xs font-bold text-slate-700 mb-1">
                                    Identificador institucional del receptor <span class="text-rose-600 font-bold">*</span>
                                </label>
                                <input type="text"
                                       id="receptor_identificador"
                                       name="receptor_identificador"
                                       value="{{ old('receptor_identificador') }}"
                                       placeholder="Ej. 19080442 o DOC-1082"
                                       class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-blue-600 focus:ring focus:ring-blue-200/50 shadow-2xs font-medium text-slate-800 @error('receptor_identificador') border-rose-500 ring-1 ring-rose-500 @enderror"
                                       required />
                                @error('receptor_identificador')
                                    <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                                @else
                                    <p class="text-[11px] text-slate-400 mt-1">Matrícula estudiantil o clave docente institucional del receptor.</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- CA-04, CA-05: FECHA Y HORA REALES DE ENTREGA (Color Índigo / Temporalidad) -->
                    <div class="p-5 rounded-2xl bg-indigo-50/40 border border-indigo-200/80 shadow-2xs space-y-4">
                        <div class="flex items-center gap-2.5 pb-2.5 border-b border-indigo-200/70">
                            <span class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-black text-xs shadow-xs ring-2 ring-indigo-200">
                                B
                            </span>
                            <div>
                                <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-indigo-950">
                                    Fecha y hora reales de entrega
                                </h3>
                                <p class="text-[11px] text-indigo-700/80 font-medium">Momento físico exacto del acto de entrega en biblioteca</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Fecha real de entrega -->
                            <div>
                                <label for="fecha_entrega" class="block text-xs font-bold text-slate-700 mb-1">
                                    Fecha real de entrega <span class="text-rose-600 font-bold">*</span>
                                </label>
                                <input type="date"
                                       id="fecha_entrega"
                                       name="fecha_entrega"
                                       value="{{ old('fecha_entrega', $fechaEntregaDefault) }}"
                                       class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-indigo-600 focus:ring focus:ring-indigo-200/50 shadow-2xs font-medium text-slate-800 @error('fecha_entrega') border-rose-500 ring-1 ring-rose-500 @enderror"
                                       required />
                                @error('fecha_entrega')
                                    <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                                @else
                                    <p class="text-[11px] text-slate-400 mt-1">Día real en que se efectúa la entrega física en biblioteca (no posterior a hoy).</p>
                                @enderror
                            </div>

                            <!-- Hora real de entrega -->
                            <div>
                                <label for="hora_entrega" class="block text-xs font-bold text-slate-700 mb-1">
                                    Hora real de entrega <span class="text-rose-600 font-bold">*</span>
                                </label>
                                <input type="time"
                                       id="hora_entrega"
                                       name="hora_entrega"
                                       value="{{ old('hora_entrega', $horaEntregaDefault) }}"
                                       class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-indigo-600 focus:ring focus:ring-indigo-200/50 shadow-2xs font-medium text-slate-800 @error('hora_entrega') border-rose-500 ring-1 ring-rose-500 @enderror"
                                       required />
                                @error('hora_entrega')
                                    <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                                @else
                                    <p class="text-[11px] text-slate-400 mt-1">Hora real del momento de entrega presencial.</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- CA-03, CA-04: COMPROBACIÓN PRESENCIAL DE PROPIEDAD (Color Vino Institucional / Comprobación) -->
                    <div class="p-5 rounded-2xl bg-rose-50/40 border border-rose-200/80 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between pb-2.5 border-b border-rose-200/70">
                            <div class="flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-[#691C32] text-white flex items-center justify-center font-black text-xs shadow-xs ring-2 ring-rose-200">
                                    C
                                </span>
                                <div>
                                    <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-[#691C32]">
                                        Comprobación presencial de propiedad
                                    </h3>
                                    <p class="text-[11px] text-rose-700/80 font-medium">Descripción fundamentada de las pruebas y verificación de pertenencia</p>
                                </div>
                            </div>
                            <span class="text-rose-600 text-xs font-bold">*</span>
                        </div>

                        <div class="p-4 bg-white/90 rounded-xl border border-rose-100 text-xs text-slate-600 space-y-1.5 shadow-2xs">
                            <p class="font-bold text-slate-800">Criterio de verificación presencial:</p>
                            <p class="leading-relaxed">
                                Describe las pruebas directas presentadas por el receptor: rasgos particulares no visibles en la publicación, marcas de uso, contenido interno, desbloqueo presencial de equipos o documentación de compra/garantía.
                            </p>
                        </div>

                        <div>
                            <textarea id="explicacion_propiedad"
                                      name="explicacion_propiedad"
                                      rows="4"
                                      placeholder="Ej. El receptor describió con precisión los rayones específicos en la tapa trasera, el llavero interior y desbloqueó el dispositivo introduciendo su PIN frente al bibliotecario sin ninguna inconsistencia."
                                      class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:border-[#691C32] focus:ring focus:ring-rose-200/50 shadow-2xs font-medium text-slate-800 leading-relaxed @error('explicacion_propiedad') border-rose-500 ring-1 ring-rose-500 @enderror"
                                      required>{{ old('explicacion_propiedad') }}</textarea>
                            @error('explicacion_propiedad')
                                <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                            @else
                                <p class="text-[11px] text-slate-400 mt-1">La explicación detallada de cómo se acreditó presencialmente la propiedad es obligatoria.</p>
                            @enderror
                        </div>
                    </div>

                    <!-- CA-03: CONFIRMACIÓN DE RESOLUCIÓN DE CONTRADICCIONES / PROCEDIMIENTO DE INCIDENCIAS (Color Ámbar / Resolución) -->
                    <div class="p-5 rounded-2xl bg-amber-50/40 border border-amber-200/80 shadow-2xs space-y-4">
                        <div class="flex items-center gap-2.5 pb-2.5 border-b border-amber-200/70">
                            <span class="w-7 h-7 rounded-lg bg-amber-600 text-white flex items-center justify-center font-black text-xs shadow-xs ring-2 ring-amber-200">
                                D
                            </span>
                            <div>
                                <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-amber-950">
                                    Ausencia de contradicciones y procedencia
                                </h3>
                                <p class="text-[11px] text-amber-700/80 font-medium">Validación de congruencia y prevención de incidencias</p>
                            </div>
                        </div>

                        <!-- Casilla obligatoria: Sin contradicciones -->
                        <div class="p-4 rounded-xl bg-white border border-amber-200/90 space-y-2 shadow-2xs">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox"
                                       name="declaracion_sin_contradicciones"
                                       value="1"
                                       {{ old('declaracion_sin_contradicciones') ? 'checked' : '' }}
                                       class="w-4 h-4 mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500 cursor-pointer"
                                       required />
                                <span class="text-xs sm:text-[13px] font-bold text-slate-800 leading-snug select-none">
                                    Confirmo que verifiqué presencialmente la propiedad del objeto con el receptor y no existen contradicciones ni dudas pendientes sobre su identidad. <span class="text-rose-600">*</span>
                                </span>
                            </label>
                            @error('declaracion_sin_contradicciones')
                                <p class="text-xs text-rose-600 font-semibold pl-7 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Orientación al procedimiento de incidencias si existen contradicciones (CA-03) -->
                        <div class="p-4 rounded-xl bg-amber-100/70 border border-amber-300 space-y-2 text-xs text-amber-950">
                            <div class="flex items-center gap-2 font-bold text-amber-900">
                                <svg class="w-4 h-4 text-amber-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>¿Existen inconsistencias o dudas sin aclarar sobre la propiedad?</span>
                            </div>
                            <p class="leading-relaxed">
                                Si el solicitante no acredita fehacientemente ser el propietario, presenta información contradictoria o dos personas reclaman el mismo bien, <strong>no confirmes la entrega</strong>.
                            </p>
                            <p class="font-semibold text-amber-900 leading-relaxed">
                                Orienta el caso inmediatamente al <strong>procedimiento de incidencias</strong> correspondiente para su investigación y aclaración formal en biblioteca, conservando el objeto bajo resguardo.
                            </p>
                        </div>
                    </div>

                    <!-- CA-04: FOTOGRAFÍA DE EVIDENCIA CON VISTA PREVIA (Color Esmeralda / Evidencia Documental) -->
                    <div class="p-5 rounded-2xl bg-emerald-50/40 border border-emerald-200/80 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between pb-2.5 border-b border-emerald-200/70">
                            <div class="flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-black text-xs shadow-xs ring-2 ring-emerald-200">
                                    E
                                </span>
                                <div>
                                    <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-emerald-950">
                                        Fotografía de evidencia de la entrega
                                    </h3>
                                    <p class="text-[11px] text-emerald-700/80 font-medium">Constancia fotográfica protegida del acto de entrega</p>
                                </div>
                            </div>
                            <span class="text-rose-600 text-xs font-bold">*</span>
                        </div>

                        <div class="p-3.5 bg-white/90 rounded-xl border border-emerald-100 text-xs text-slate-600 space-y-1 shadow-2xs">
                            <p class="font-bold text-slate-700">Requisitos de la fotografía:</p>
                            <ul class="list-disc list-inside text-[11px] text-slate-600 space-y-0.5">
                                <li>Formato admitido: <strong>JPG o PNG</strong> con tamaño máximo de hasta <strong>2 MB</strong>.</li>
                                <li>Debe documentar el acto de entrega física en biblioteca al receptor.</li>
                                <li>No se solicitan credenciales legibles ni contraseñas privadas.</li>
                            </ul>
                        </div>

                        @error('fotografia')
                            <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 font-bold flex items-center gap-2">
                                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span>{{ $message }}</span>
                            </div>
                        @enderror

                        <div>
                            <input type="file"
                                   id="fotografia"
                                   name="fotografia"
                                   accept=".jpg,.png,image/jpeg,image/png"
                                   class="hidden"
                                   onchange="manejarVistaPreviaBiblioteca(event)"
                                   required />

                            <!-- Área clickeable para seleccionar fotografía -->
                            <div id="area-subida-biblioteca"
                                 onclick="document.getElementById('fotografia').click()"
                                 class="border-2 border-dashed border-slate-300 hover:border-[#691C32] rounded-2xl p-6 text-center cursor-pointer bg-slate-50/50 hover:bg-rose-50/20 transition-all group">
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

                            <!-- Contenedor de Vista Previa Interactivo con sustitución y eliminación -->
                            <div id="contenedor-preview-biblioteca" class="hidden mt-3 p-4 bg-slate-50 rounded-2xl border border-slate-200">
                                <div class="flex flex-col sm:flex-row items-center gap-4">
                                    <img id="img-preview-biblioteca"
                                         src="#"
                                         alt="Vista previa de evidencia"
                                         class="w-32 h-32 sm:w-40 sm:h-40 rounded-xl object-cover border-2 border-slate-200 shadow-xs shrink-0" />
                                    <div class="flex-1 text-center sm:text-left space-y-2">
                                        <div>
                                            <span class="text-xs font-bold text-slate-800 block" id="nombre-archivo-preview-biblioteca">archivo.jpg</span>
                                            <span class="text-[11px] text-slate-400 block" id="peso-archivo-preview-biblioteca">0 KB</span>
                                        </div>
                                        <div class="flex items-center justify-center sm:justify-start gap-2 pt-1">
                                            <button type="button"
                                                    onclick="document.getElementById('fotografia').click()"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors shadow-2xs">
                                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                                <span>Sustituir fotografía</span>
                                            </button>
                                            <button type="button"
                                                    onclick="eliminarFotoSeleccionadaBiblioteca()"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition-colors">
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

                </div>

                <!-- BOTONES DE ACCIÓN DEL FORMULARIO -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex flex-col-reverse sm:flex-row items-center justify-between gap-3">
                    <a href="{{ route('admin.inventario.show', $recepcion?->id ?? $reporte->id) }}"
                       class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold text-center transition-colors">
                        Cancelar y volver
                    </a>

                    <!-- Botón disparador del modal de confirmación (CA-06, CA-10) -->
                    <button type="button"
                            id="btn-preconfirmar-devolucion"
                            @click="confirmarEntrega()"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] text-white text-xs font-bold transition-all shadow-xs cursor-pointer">
                        <span>Registrar devolución desde biblioteca</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </form>

        </div>

        <!-- MODAL DE CONFIRMACIÓN PREVIA DE LA DEVOLUCIÓN (CA-06) -->
        <div x-show="modalConfirmarOpen"
             x-cloak
             @keydown.escape.window="if (!procesando) modalConfirmarOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs transition-opacity"
             role="dialog"
             aria-modal="true">
            <div class="relative max-w-lg w-full bg-white rounded-3xl overflow-hidden shadow-2xl border border-slate-100 text-left p-6 sm:p-7 space-y-5"
                 @click.away="if (!procesando) modalConfirmarOpen = false">
                
                <!-- Franja decorativa vino/dorado -->
                <div class="absolute top-0 inset-x-0 h-1.5 bg-gradient-to-r from-[#691C32] via-[#851838] to-[#C59B3F]"></div>

                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-[#691C32]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>

                    <div class="space-y-1">
                        <h3 class="text-lg font-black text-slate-800 tracking-tight leading-snug">
                            ¿Confirmar devolución desde biblioteca?
                        </h3>
                        <p class="text-xs text-slate-500">
                            Folio: <strong class="font-mono text-[#691C32]">{{ $reporte->folio }}</strong> &bull; {{ $reporte->nombre ?? $reporte->titulo }}
                        </p>
                    </div>
                </div>

                <!-- Advertencia institucional de cambio de estado y retiro del inventario (CA-06) -->
                <div class="p-4 rounded-2xl bg-rose-50/70 border border-rose-200 text-xs text-rose-950 space-y-2">
                    <p class="font-bold text-rose-900 leading-snug">
                        Consecuencias del registro de devolución:
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-[11px] text-rose-800 font-medium">
                        <li>El objeto quedará registrado en estado institucional <strong>«Devuelto»</strong>.</li>
                        <li><strong>Saldrá inmediatamente del inventario activo</strong> de biblioteca y del muro público si estaba visible.</li>
                        <li>No requerirá una segunda aprobación administrativa (cierre directo).</li>
                        <li>No permitirá nuevos registros de entrega sobre el mismo resguardo.</li>
                    </ul>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed font-medium">
                    Al confirmar, se registrarán simultáneamente la devolución, la salida física del objeto, la evidencia fotográfica y tu usuario como bibliotecario responsable.
                </p>

                <!-- Botones de Acción del Modal -->
                <div class="pt-2 flex flex-col-reverse sm:flex-row items-center justify-end gap-2.5">
                    <button type="button"
                            @click="modalConfirmarOpen = false"
                            :disabled="procesando"
                            class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold text-center transition-colors">
                        Cancelar
                    </button>

                    <!-- Botón submit real con prevención de doble clic (CA-10) -->
                    <button type="button"
                            id="btn-confirmar-final-devolucion"
                            @click="ejecutarEnvio()"
                            :disabled="procesando"
                            :class="procesando ? 'opacity-75 cursor-not-allowed' : 'cursor-pointer'"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] text-white text-xs font-bold transition-all shadow-xs">
                        <template x-if="!procesando">
                            <span class="inline-flex items-center gap-1.5">
                                <span>Sí, confirmar devolución</span>
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                        </template>
                        <template x-if="procesando">
                            <span class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Registrando devolución...</span>
                            </span>
                        </template>
                    </button>
                </div>

            </div>
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

    <!-- SCRIPT DE VISTA PREVIA Y VALIDACIÓN CLIENTE -->
    <script>
        function manejarVistaPreviaBiblioteca(event) {
            const input = event.target;
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();

                reader.onload = function(e) {
                    const img = document.getElementById('img-preview-biblioteca');
                    img.src = e.target.result;
                    document.getElementById('nombre-archivo-preview-biblioteca').textContent = file.name;
                    document.getElementById('peso-archivo-preview-biblioteca').textContent = (file.size / 1024).toFixed(1) + ' KB';

                    document.getElementById('contenedor-preview-biblioteca').classList.remove('hidden');
                    document.getElementById('area-subida-biblioteca').classList.add('hidden');
                };

                reader.readAsDataURL(file);
            }
        }

        function eliminarFotoSeleccionadaBiblioteca() {
            const input = document.getElementById('fotografia');
            input.value = '';
            document.getElementById('img-preview-biblioteca').src = '#';
            document.getElementById('contenedor-preview-biblioteca').classList.add('hidden');
            document.getElementById('area-subida-biblioteca').classList.remove('hidden');
        }
    </script>
</x-app-layout>

