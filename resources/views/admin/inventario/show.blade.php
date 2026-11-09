<x-app-layout>
    <div class="min-h-screen bg-[#F5F6F8] py-8 text-[#1F2937]"
         x-data="{
             activeImg: '{{ $fotografiaPrincipal ? route('fotografias.show', $fotografiaPrincipal->id) : '' }}',
             activeId: {{ $fotografiaPrincipal ? $fotografiaPrincipal->id : 'null' }},
             modalFotoOpen: false,
             editUbicacionOpen: {{ $errors->has('ubicacion_resguardo') ? 'true' : 'false' }}
         }">
        <div class="max-w-[1140px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- 1. ENCABEZADO Y RETORNO -->
            <div class="space-y-2">
                <div>
                    <a href="{{ route('admin.inventario.index') }}"
                       class="inline-flex items-center gap-1.5 text-xs sm:text-[13px] font-medium text-[#64748B] hover:text-[#691C32] transition-colors focus:outline-none focus:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Volver al inventario de biblioteca</span>
                    </a>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-2xl sm:text-[26px] font-bold text-[#1F2937] tracking-tight leading-tight">
                            Detalle de resguardo institucional
                        </h1>
                        <p class="text-xs sm:text-[13px] font-medium text-[#64748B] mt-0.5">
                            <span class="text-[#1F2937] font-semibold">{{ $reporte->nombre ?? $reporte->titulo }}</span>
                            <span class="mx-1.5 text-slate-300">•</span>
                            <span>Folio: <strong class="font-mono text-[#691C32]">{{ $reporte->folio }}</strong></span>
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
                        @if ($reporte->puedeRegistrarDevolucionDesdeBiblioteca())
                            <a href="{{ route('admin.devoluciones-biblioteca.create', $reporte->id) }}"
                               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] text-white text-xs font-bold transition-all shadow-2xs cursor-pointer"
                               title="Registrar devolución desde biblioteca para el objeto {{ $reporte->folio }}">
                                <svg class="w-3.5 h-3.5 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Registrar devolución desde biblioteca</span>
                            </a>
                        @endif

                        <a href="{{ route('reportes.historial', $reporte->id) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 hover:text-[#691C32] text-xs font-bold transition-all shadow-2xs">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Historial del objeto</span>
                        </a>

                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-300">
                            En biblioteca
                        </span>
                    </div>
                </div>
            </div>

            <!-- MENSAJES DE ESTADO / ALERTA -->
            @if (session('status'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-start gap-3 shadow-xs" role="alert">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-xs sm:text-sm font-semibold">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 flex items-start gap-3 shadow-xs" role="alert">
                    <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="text-xs sm:text-sm font-semibold">
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            <!-- 2. CONTENIDO PRINCIPAL EN DOS COLUMNAS -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- COLUMNA IZQUIERDA (5 Columnas): Información del objeto y Fotografías originales -->
                <div class="lg:col-span-5 space-y-6">

                    <!-- Tarjeta de Fotografías Originales -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                                <svg class="w-4 h-4 text-[#691C32]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span>Fotografías originales</span>
                            </h2>
                            <span class="text-xs text-slate-400">
                                {{ $reporte->fotografias->count() }} {{ $reporte->fotografias->count() === 1 ? 'imagen' : 'imágenes' }}
                            </span>
                        </div>

                        <!-- Visor de Fotografía -->
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
                                    <span class="text-xs">Sin fotografías registradas</span>
                                </div>
                            @endif
                        </div>

                        <!-- Miniaturas de la galería -->
                        @if ($reporte->fotografias->count() > 1)
                            <div class="grid grid-cols-4 gap-2 pt-1">
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

                    <!-- Tarjeta de Información General del Objeto -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 space-y-4">
                        <div class="border-b border-slate-100 pb-3">
                            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                                <svg class="w-4 h-4 text-[#691C32]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Información del objeto</span>
                            </h2>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div>
                                <span class="text-slate-400 font-bold block uppercase text-[10px]">Folio institucional</span>
                                <span class="font-mono text-sm font-bold text-[#691C32]">{{ $reporte->folio }}</span>
                            </div>

                            <div>
                                <span class="text-slate-400 font-bold block uppercase text-[10px]">Nombre del objeto</span>
                                <span class="font-bold text-slate-900 text-sm leading-tight block">{{ $reporte->nombre ?? $reporte->titulo }}</span>
                            </div>

                            <div>
                                <span class="text-slate-400 font-bold block uppercase text-[10px]">Descripción original</span>
                                <p class="text-slate-700 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-100 whitespace-pre-line mt-1">
                                    {{ $reporte->descripcion }}
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-1">
                                <div>
                                    <span class="text-slate-400 font-bold block uppercase text-[10px]">Autor del reporte</span>
                                    <span class="font-semibold text-slate-800 block break-words">{{ $reporte->user->name ?? 'Usuario no disponible' }}</span>
                                    @if (! empty($reporte->user->email))
                                        <span class="text-slate-400 text-[11px] block break-all">{{ $reporte->user->email }}</span>
                                    @endif
                                </div>

                                <div>
                                    <span class="text-slate-400 font-bold block uppercase text-[10px]">Fecha de hallazgo</span>
                                    <span class="font-semibold text-slate-800 block">
                                        {{ $reporte->fecha_hallazgo ? $reporte->fecha_hallazgo->format('d/m/Y') : 'No especificada' }}
                                    </span>
                                    @if ($reporte->hora_hallazgo)
                                        <span class="text-slate-400 text-[11px] block">{{ substr($reporte->hora_hallazgo, 0, 5) }} hrs</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- COLUMNA DERECHA (7 Columnas): Información de Recepción y Resguardo Físico -->
                <div class="lg:col-span-7 space-y-6">

                    <!-- Tarjeta de Información de Recepción -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-5">
                        <div class="border-b border-slate-100 pb-3.5 flex items-center justify-between">
                            <h2 class="text-base font-bold text-[#1F2937] flex items-center gap-2">
                                <span class="w-7 h-7 rounded-lg bg-[#691C32] text-white flex items-center justify-center font-bold text-xs">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                </span>
                                <span>Información de recepción en biblioteca</span>
                            </h2>
                            <span class="text-xs text-[#64748B]">Datos internos de custodia</span>
                        </div>

                        <!-- 1. DISTINCIÓN CLARA ENTRE FECHA FÍSICA Y FECHA DE CAPTURA -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Fecha y hora física de recepción -->
                            <div class="p-4 rounded-xl bg-amber-50/70 border border-amber-200 space-y-1">
                                <span class="text-[11px] font-bold text-amber-900 block uppercase tracking-wider">
                                    Fecha y hora física de entrega
                                </span>
                                <span class="text-base font-bold text-slate-900 block">
                                    {{ $recepcion->fecha_recepcion ? $recepcion->fecha_recepcion->format('d/m/Y') : 'No especificada' }}
                                </span>
                                <span class="text-xs text-amber-900 block font-medium">
                                    Hora real: <strong class="font-mono text-slate-800">{{ $recepcion->hora_recepcion ? substr($recepcion->hora_recepcion, 0, 5) . ' hrs' : 'No especificada' }}</strong>
                                </span>
                                <p class="text-[10px] text-amber-800 pt-1">
                                    Momento en que el autor entregó físicamente el objeto en biblioteca.
                                </p>
                            </div>

                            <!-- Fecha y hora de captura en el sistema -->
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                                <span class="text-[11px] font-bold text-slate-500 block uppercase tracking-wider">
                                    Fecha y hora de captura en sistema
                                </span>
                                <span class="text-base font-bold text-slate-900 block font-mono">
                                    {{ $recepcion->fecha_captura_fecha_texto }}
                                </span>
                                <span class="text-xs text-slate-600 block">
                                    Hora de registro: <strong class="font-mono text-slate-800">{{ $recepcion->hora_captura_texto }} hrs</strong>
                                </span>
                                <p class="text-[10px] text-slate-400 pt-1">
                                    Registro computado en zona horaria America/Merida.
                                </p>
                            </div>
                        </div>

                        <!-- 2. ADMINISTRADOR RESPONSABLE Y ESTADO ACTUAL -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs pt-1">
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                                <span class="text-slate-400 font-bold block uppercase text-[10px]">Administrador responsable</span>
                                <span class="text-sm font-bold text-slate-900 block">{{ $recepcion->user->name ?? 'Personal de biblioteca' }}</span>
                                @if (! empty($recepcion->user->email))
                                    <span class="text-slate-500 text-[11px] block">{{ $recepcion->user->email }}</span>
                                @endif
                            </div>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                                <span class="text-slate-400 font-bold block uppercase text-[10px]">Estado actual del resguardo</span>
                                <div class="pt-0.5">
                                    @if ($recepcion->isAnualVencido())
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-900 border border-rose-300">
                                            Resguardo vencido
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-900 border border-emerald-300">
                                            Bajo resguardo institucional
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- 3. UBICACIÓN INTERNA Y CONDICIÓN DEL OBJETO -->
                        <div class="space-y-4 pt-1 text-xs">
                            <!-- Ubicación interna de resguardo y acción Cambiar ubicación -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400 font-bold block uppercase text-[10px]">Ubicación interna de resguardo</span>
                                    @if ($reporte->situacion_resguardo === 'En biblioteca')
                                        <button type="button"
                                                @click="editUbicacionOpen = !editUbicacionOpen"
                                                class="inline-flex items-center gap-1 text-[11px] font-bold text-[#691C32] hover:text-[#520B1D] transition-colors focus:outline-none">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            <span x-text="editUbicacionOpen ? 'Cancelar cambio' : 'Cambiar ubicación'">Cambiar ubicación</span>
                                        </button>
                                    @endif
                                </div>

                                <div class="p-3.5 rounded-xl bg-amber-50/50 border border-amber-200 text-slate-800 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-[#691C32] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                        <span class="text-sm font-bold text-slate-900">{{ $recepcion->ubicacion_resguardo }}</span>
                                    </div>

                                    @if ($recepcion->fecha_cambio_ubicacion)
                                        <div class="text-[11px] text-slate-500 pt-1 border-t border-amber-200/60 flex items-center gap-1.5">
                                            <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>
                                                Actualizada el {{ $recepcion->fecha_cambio_ubicacion_texto }}
                                                @if ($recepcion->actualizador)
                                                    por <strong class="text-slate-700">{{ $recepcion->actualizador->name }}</strong>
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Formulario interactivo para cambiar ubicación -->
                                @if ($reporte->situacion_resguardo === 'En biblioteca')
                                    <div x-show="editUbicacionOpen"
                                         x-cloak
                                         x-transition
                                         class="p-4 rounded-xl bg-white border-2 border-[#691C32]/30 shadow-xs space-y-3 mt-2">
                                        <div class="border-b border-slate-100 pb-2">
                                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5 text-[#691C32]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                <span>Actualizar ubicación interna</span>
                                            </h3>
                                            <p class="text-[11px] text-slate-500 mt-0.5">
                                                Ingresa el nuevo estante, gaveta o módulo dentro de biblioteca. Se guardará registro administrativo del cambio.
                                            </p>
                                        </div>

                                        <form method="POST" action="{{ route('admin.inventario.update-ubicacion', $recepcion->id) }}" class="space-y-3">
                                            @csrf
                                            @method('PUT')

                                            <div>
                                                <label for="ubicacion_resguardo" class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                                                    Nueva ubicación <span class="text-rose-600">*</span>
                                                </label>
                                                <input type="text"
                                                       name="ubicacion_resguardo"
                                                       id="ubicacion_resguardo"
                                                       value="{{ old('ubicacion_resguardo', $recepcion->ubicacion_resguardo) }}"
                                                       required
                                                       maxlength="255"
                                                       placeholder="Ej: Estante B-4, Gaveta 2, Caja 10"
                                                       class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-[#691C32] focus:border-[#691C32] transition-colors" />
                                                @error('ubicacion_resguardo')
                                                    <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="flex items-center justify-end gap-2 pt-1">
                                                <button type="button"
                                                        @click="editUbicacionOpen = false"
                                                        class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-colors">
                                                    Cancelar
                                                </button>
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-[#691C32] hover:bg-[#520B1D] text-white text-xs font-bold transition-all shadow-2xs">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span>Guardar nueva ubicación</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            </div>

                            <div>
                                <span class="text-slate-400 font-bold block uppercase text-[10px] mb-1">Condición del objeto al momento de entrega</span>
                                <p class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 leading-relaxed whitespace-pre-line text-xs sm:text-sm">
                                    {{ $recepcion->condicion_objeto }}
                                </p>
                            </div>

                            <!-- 4. FECHA LÍMITE ANUAL -->
                            <div>
                                <span class="text-slate-400 font-bold block uppercase text-[10px] mb-1">Fecha límite de resguardo anual (1 año natural)</span>
                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                                    <div>
                                        <span class="text-sm font-bold {{ $recepcion->isAnualVencido() ? 'text-rose-700' : 'text-slate-900' }}">
                                            {{ $recepcion->fecha_limite_anual ? $recepcion->fecha_limite_anual->format('d/m/Y') : '1 año' }}
                                        </span>
                                        <span class="text-[11px] text-slate-500 block">
                                            Calculado a partir de la fecha física de entrega ({{ $recepcion->fecha_recepcion ? $recepcion->fecha_recepcion->format('d/m/Y') : '' }})
                                        </span>
                                    </div>
                                    @if ($recepcion->isAnualVencido())
                                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                            Plazo cumplido
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                            Vigente en resguardo
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- 5. OBSERVACIONES ADMINISTRATIVAS -->
                            <div>
                                <span class="text-slate-400 font-bold block uppercase text-[10px] mb-1">Observaciones administrativas</span>
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 leading-relaxed text-xs sm:text-[13px]">
                                    @if (! empty($recepcion->observaciones))
                                        <p class="whitespace-pre-line">{{ $recepcion->observaciones }}</p>
                                    @else
                                        <span class="text-slate-400 italic">Sin observaciones administrativas adicionales registradas.</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción al pie de la tarjeta -->
                        <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-end gap-3">
                            <a href="{{ route('admin.moderacion.show', $reporte->id) }}"
                               class="w-full sm:w-auto px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold text-center transition-colors">
                                Ver en moderación
                            </a>
                        </div>
                    </div>

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
</x-app-layout>

