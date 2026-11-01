<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-100 text-[#691C32] flex items-center justify-center shrink-0 shadow-2xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="text-xs font-black tracking-wider text-slate-800 uppercase">{{ $publicacion->folio }}</span>
                        <span class="text-slate-300">&bull;</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $publicacion->estado === 'Aprobada' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : ($publicacion->estado === 'Requiere corrección' ? 'bg-rose-50 text-rose-800 border border-rose-200' : 'bg-amber-50 text-amber-900 border border-amber-200') }}">
                            {{ $publicacion->estado }}
                        </span>
                    </div>
                    <h2 class="font-black text-xl text-slate-900 leading-tight">
                        Editar Reporte de Hallazgo
                    </h2>
                    <p class="text-xs text-slate-500 font-medium">
                        Actualiza la información del objeto o atiende las observaciones de Biblioteca
                    </p>
                </div>
            </div>
            <div>
                <a href="{{ route('reportes.mis-reportes') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-colors shadow-2xs">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Volver a Mis Reportes</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-50/60 min-h-[calc(100vh-140px)]">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Observaciones Administrativas de Biblioteca si el estado es 'Requiere corrección' o 'Corrección solicitada' (CA-07) -->
            @if ($publicacion->isEnCorreccion() && ($publicacion->motivo_correccion || $publicacion->observaciones_admin || $publicacion->observaciones))
                <div class="p-5 rounded-3xl bg-amber-50 border-2 border-amber-300 text-amber-950 shadow-xs">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-2xl bg-amber-200/80 text-amber-900 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-amber-900" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-black text-amber-900 uppercase tracking-wide">
                                Observaciones de Biblioteca para Corrección
                            </h4>
                            <p class="text-xs sm:text-sm text-amber-900 mt-1 leading-relaxed font-medium">
                                {{ $publicacion->motivo_correccion ?? $publicacion->observaciones_admin ?? $publicacion->observaciones }}
                            </p>
                            <p class="text-[11px] text-amber-700 mt-2 font-medium">
                                Por favor atiende estas indicaciones antes de enviar nuevamente a revisión.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tarjeta de Metadatos Protegidos del Reporte (CA-13: Folio, autor, vigencia e historial inmutables) -->
            <div class="bg-white rounded-3xl border border-slate-200/90 p-5 shadow-xs">
                <div class="flex items-center gap-2 mb-3 pb-3 border-b border-slate-100">
                    <svg class="w-4 h-4 text-[#691C32]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <span class="text-xs font-black text-slate-800 uppercase tracking-wider">
                        Datos Institucionales Protegidos
                    </span>
                    <span class="text-[10px] text-slate-400 font-medium ml-auto">Inmutables por seguridad</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 text-xs">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Folio</span>
                        <span class="font-black text-slate-900">{{ $publicacion->folio }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Autor</span>
                        <span class="font-bold text-slate-800">{{ $publicacion->user->name ?? Auth::user()->name }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Teléfono de Contacto</span>
                        <span class="font-bold text-slate-800 block">
                            {{ $publicacion->user->phone ?? Auth::user()->phone ?? 'Sin registrar' }}
                        </span>
                        <a href="{{ route('profile.edit') }}" class="text-[10px] text-[#691C32] hover:underline font-semibold" title="Modificar teléfono en tu perfil">
                            Editar en perfil &rsaquo;
                        </a>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Fecha de Captura</span>
                        <span class="font-bold text-slate-800">
                            {{ $publicacion->fecha_registro_texto }}
                        </span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Vigencia</span>
                        <span class="font-bold text-slate-800">{{ $publicacion->vigencia_texto }}</span>
                    </div>
                </div>
            </div>

            <!-- Formulario de Edición (CA-11 a CA-17, CA-22) -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8">
                <form
                    method="POST"
                    action="{{ route('reportes.update', $publicacion->id) }}"
                    enctype="multipart/form-data"
                    x-data="{
                        descripcion: '{{ addslashes(old('descripcion', $publicacion->descripcion)) }}',
                        fecha: '{{ old('fecha_hallazgo', $publicacion->fecha_hallazgo ? $publicacion->fecha_hallazgo->format('Y-m-d') : '') }}',
                        hora: '{{ old('hora_hallazgo', $publicacion->hora_hallazgo ? (is_string($publicacion->hora_hallazgo) ? substr($publicacion->hora_hallazgo, 0, 5) : $publicacion->hora_hallazgo->format('H:i')) : '') }}',
                        minDate: '{{ $minDate }}',
                        maxDate: '{{ $maxDate }}',
                        maxTime: '{{ $maxTime }}',
                        descCount() {
                            return (this.descripcion || '').trim().length;
                        }
                    }"
                    class="space-y-6"
                >
                    @csrf
                    @method('PUT')

                    <!-- Nombre del Objeto (CA-14) -->
                    <div>
                        <label for="nombre" class="block text-xs font-bold text-slate-800 mb-1.5">
                            Nombre del Objeto Encontrado <span class="text-rose-600 font-bold">*</span>
                        </label>
                        <input
                            id="nombre"
                            name="nombre"
                            type="text"
                            value="{{ old('nombre', $publicacion->nombre ?? $publicacion->titulo) }}"
                            maxlength="150"
                            required
                            placeholder="Ej. Mouse inalámbrico color blanco"
                            class="block w-full rounded-2xl border-slate-200 text-sm focus:border-[#691C32] focus:ring-[#691C32] @error('nombre') border-rose-300 ring-1 ring-rose-300 @enderror"
                        />
                        @error('nombre')
                            <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Descripción (CA-12: 50 a 200 caracteres) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="descripcion" class="block text-xs font-bold text-slate-800">
                                Descripción Detallada <span class="text-rose-600 font-bold">*</span>
                            </label>
                            <span class="text-[11px] font-bold" :class="descCount() < 50 || descCount() > 200 ? 'text-amber-600' : 'text-emerald-700'">
                                <span x-text="descCount()"></span> / 200 caracteres (mínimo 50)
                            </span>
                        </div>
                        <textarea
                            id="descripcion"
                            name="descripcion"
                            rows="4"
                            x-model="descripcion"
                            required
                            placeholder="Describe características particulares, marcas, color, detalles visibles (sin revelar datos que solo el dueño deba conocer)..."
                            class="block w-full rounded-2xl border-slate-200 text-sm focus:border-[#691C32] focus:ring-[#691C32] @error('descripcion') border-rose-300 ring-1 ring-rose-300 @enderror"
                        >{{ old('descripcion', $publicacion->descripcion) }}</textarea>
                        @error('descripcion')
                            <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-[11px] text-slate-500">
                            La descripción debe tener entre 50 y 200 caracteres para facilitar su identificación institucional.
                        </p>
                    </div>

                    <!-- Fecha y Hora del Hallazgo (CA-15, CA-16, CA-17) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <!-- Fecha de hallazgo referenciada a la fecha de captura original (CA-15) -->
                        <div>
                            <label for="fecha_hallazgo" class="block text-xs font-bold text-slate-800 mb-1.5">
                                Fecha del Hallazgo <span class="text-rose-600 font-bold">*</span>
                            </label>
                            <input
                                id="fecha_hallazgo"
                                name="fecha_hallazgo"
                                type="date"
                                x-model="fecha"
                                min="{{ $minDate }}"
                                max="{{ $maxDate }}"
                                required
                                class="block w-full rounded-2xl border-slate-200 text-sm focus:border-[#691C32] focus:ring-[#691C32] @error('fecha_hallazgo') border-rose-300 ring-1 ring-rose-300 @enderror"
                            />
                            @error('fecha_hallazgo')
                                <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-[11px] text-slate-500">
                                Debe estar dentro de los 7 días previos a la captura original (entre {{ Carbon\Carbon::parse($minDate)->format('d/m/Y') }} y {{ Carbon\Carbon::parse($maxDate)->format('d/m/Y') }}).
                            </p>
                        </div>

                        <!-- Hora de hallazgo opcional con validación cronológica (CA-16, CA-17) -->
                        <div>
                            <label for="hora_hallazgo" class="block text-xs font-bold text-slate-800 mb-1.5">
                                Hora Aproximada del Hallazgo <span class="text-slate-400 font-normal">(Opcional)</span>
                            </label>
                            <input
                                id="hora_hallazgo"
                                name="hora_hallazgo"
                                type="time"
                                x-model="hora"
                                class="block w-full rounded-2xl border-slate-200 text-sm focus:border-[#691C32] focus:ring-[#691C32] @error('hora_hallazgo') border-rose-300 ring-1 ring-rose-300 @enderror"
                            />
                            @error('hora_hallazgo')
                                <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-[11px] text-slate-500">
                                Si fue encontrado el día de la captura original, no puede exceder las {{ $maxTime }} hrs.
                            </p>
                        </div>
                    </div>

                    <!-- Ubicación: Área/Zona y Ubicación Específica (CA-14) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="area_zona" class="block text-xs font-bold text-slate-800 mb-1.5">
                                Área o Zona del Campus <span class="text-rose-600 font-bold">*</span>
                            </label>
                            <input
                                id="area_zona"
                                name="area_zona"
                                type="text"
                                value="{{ old('area_zona', $publicacion->area_zona) }}"
                                maxlength="100"
                                required
                                placeholder="Ej. Edificio G - Sistemas"
                                class="block w-full rounded-2xl border-slate-200 text-sm focus:border-[#691C32] focus:ring-[#691C32] @error('area_zona') border-rose-300 ring-1 ring-rose-300 @enderror"
                            />
                            @error('area_zona')
                                <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="ubicacion_especifica" class="block text-xs font-bold text-slate-800 mb-1.5">
                                Ubicación Específica <span class="text-rose-600 font-bold">*</span>
                            </label>
                            <input
                                id="ubicacion_especifica"
                                name="ubicacion_especifica"
                                type="text"
                                value="{{ old('ubicacion_especifica', $publicacion->ubicacion_especifica) }}"
                                maxlength="255"
                                required
                                placeholder="Ej. Laboratorio L4, mesa 3"
                                class="block w-full rounded-2xl border-slate-200 text-sm focus:border-[#691C32] focus:ring-[#691C32] @error('ubicacion_especifica') border-rose-300 ring-1 ring-rose-300 @enderror"
                            />
                            @error('ubicacion_especifica')
                                <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Gestión de Fotografías del Reporte (CA-18, CA-19, CA-20, CA-21) -->
                    <div class="pt-6 border-t border-slate-100 space-y-4" x-data="{
                        eliminadas: @js(array_map('intval', old('eliminar_fotos', []))),
                        principalId: @js(old('foto_principal_id', $publicacion->foto_principal?->id ?? ($publicacion->fotografias->first()?->id ?? null))),
                        nuevasCount: 0,
                        toggleEliminar(id) {
                            const index = this.eliminadas.indexOf(id);
                            if (index > -1) {
                                this.eliminadas.splice(index, 1);
                            } else {
                                this.eliminadas.push(id);
                                if (this.principalId === id) {
                                    this.principalId = null;
                                }
                            }
                        },
                        isEliminada(id) {
                            return this.eliminadas.includes(id);
                        },
                        setPrincipal(id) {
                            if (!this.isEliminada(id)) {
                                this.principalId = id;
                            }
                        }
                    }">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                            <div>
                                <label class="block text-xs font-bold text-slate-800">
                                    Fotografías del Reporte <span class="text-rose-600 font-bold">*</span>
                                </label>
                                <p class="text-[11px] text-slate-500">
                                    Conserva, retira o agrega imágenes. El reporte debe mantener entre 1 y 5 fotografías en total.
                                </p>
                            </div>
                            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 uppercase tracking-wide self-start sm:self-auto">
                                Máximo 2 MB por archivo (JPG o PNG)
                            </span>
                        </div>

                        <!-- Mensajes de Error de Validación (CA-21, CA-30) -->
                        @error('fotografias')
                            <div class="p-3 rounded-2xl bg-rose-50 border border-rose-200 text-xs font-semibold text-rose-700 flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                <span>{{ $message }}</span>
                            </div>
                        @enderror

                        @error('foto_principal_id')
                            <div class="p-3 rounded-2xl bg-amber-50 border border-amber-300 text-xs font-semibold text-amber-800 flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                <span>{{ $message }}</span>
                            </div>
                        @enderror

                        <!-- Fotografías actualmente guardadas (CA-18, CA-20) -->
                        <div>
                            <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider block mb-2">
                                Fotografías actuales en el reporte ({{ $publicacion->fotografias->count() }})
                            </span>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5">
                                @foreach ($publicacion->fotografias as $foto)
                                    <div
                                        class="relative rounded-2xl border p-2.5 transition-all bg-white flex flex-col justify-between"
                                        :class="isEliminada({{ $foto->id }})
                                            ? 'border-rose-200 bg-rose-50/40 opacity-60'
                                            : (principalId === {{ $foto->id }}
                                                ? 'border-[#691C32] ring-2 ring-[#691C32]/30 shadow-xs'
                                                : 'border-slate-200 hover:border-slate-300')"
                                    >
                                        <!-- Imagen con botón para abrirla -->
                                        <div class="w-full h-32 rounded-xl overflow-hidden bg-slate-50 border border-slate-100 flex items-center justify-center relative">
                                            <img
                                                src="{{ $foto->url }}"
                                                alt="{{ $foto->nombre_original ?? 'Fotografía' }}"
                                                class="w-full h-full object-contain"
                                                loading="lazy"
                                            />
                                            
                                            <!-- Indicador de fotografía principal -->
                                            <template x-if="principalId === {{ $foto->id }} && !isEliminada({{ $foto->id }})">
                                                <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full bg-[#691C32] text-white text-[10px] font-bold shadow-xs inline-flex items-center gap-1">
                                                    <svg class="w-2.5 h-2.5 text-amber-300 fill-amber-300" viewBox="0 0 20 20">
                                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                    </svg>
                                                    <span>Principal</span>
                                                </span>
                                            </template>

                                            <template x-if="isEliminada({{ $foto->id }})">
                                                <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-rose-600 text-white text-[10px] font-bold shadow-xs">
                                                    Marcada para retirar
                                                </span>
                                            </template>
                                        </div>

                                        <!-- Acciones para la foto: Retirar / Conservar y Seleccionar Principal -->
                                        <div class="mt-3 space-y-2 text-xs">
                                            <!-- Checkbox de eliminar -->
                                            <input
                                                type="checkbox"
                                                name="eliminar_fotos[]"
                                                value="{{ $foto->id }}"
                                                id="eliminar_foto_{{ $foto->id }}"
                                                class="hidden"
                                                :checked="isEliminada({{ $foto->id }})"
                                            />

                                            <button
                                                type="button"
                                                @click="toggleEliminar({{ $foto->id }})"
                                                class="w-full py-1.5 px-3 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                                                :class="isEliminada({{ $foto->id }})
                                                    ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100'
                                                    : 'bg-rose-50 text-rose-800 border border-rose-200 hover:bg-rose-100'"
                                            >
                                                <!-- Icono SVG de papelera para retirar -->
                                                <span x-show="!isEliminada({{ $foto->id }})" class="inline-flex items-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5 text-rose-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    <span>Retirar imagen</span>
                                                </span>

                                                <!-- Icono SVG para deshacer / conservar -->
                                                <span x-show="isEliminada({{ $foto->id }})" class="inline-flex items-center gap-1.5" style="display: none;">
                                                    <svg class="w-3.5 h-3.5 text-emerald-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2M3 10l6-6m-6 6l6 6" />
                                                    </svg>
                                                    <span>Deshacer (Conservar)</span>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Cargar nuevas fotografías (CA-18, CA-19) -->
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/90 space-y-3">
                            <label for="nuevas_fotografias" class="block text-xs font-bold text-slate-800">
                                Agregar Nuevas Fotografías (Opcional)
                            </label>
                            <input
                                type="file"
                                id="nuevas_fotografias"
                                name="nuevas_fotografias[]"
                                multiple
                                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                @change="nuevasCount = $event.target.files.length"
                                class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-[#691C32] file:text-white hover:file:bg-[#520B1D] file:cursor-pointer cursor-pointer border border-slate-200 rounded-xl bg-white p-1"
                            />
                            <p class="text-[11px] text-slate-500">
                                Puedes seleccionar hasta el número de fotos que complemente el total permitido (1 a 5). Formatos admitidos: JPG o PNG de hasta 2 MB por archivo.
                            </p>

                            @if ($errors->has('nuevas_fotografias.*'))
                                <div class="mt-2 space-y-1">
                                    @foreach ($errors->get('nuevas_fotografias.*') as $messages)
                                        @foreach ($messages as $msg)
                                            <p class="text-xs text-rose-600 font-semibold flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5 shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                                <span>{{ $msg }}</span>
                                            </p>
                                        @endforeach
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Botones de Acción (CA-22, CA-23) -->
                    <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-end gap-3">
                        <!-- Botón Cancelar edición (CA-23: descarta cambios y regresa a Mis Reportes) -->
                        <a
                            href="{{ route('reportes.mis-reportes') }}"
                            class="w-full sm:w-auto text-center px-6 py-2.5 rounded-full border border-slate-300 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-2xs"
                        >
                            Cancelar edición
                        </a>

                        <!-- Botón Guardar y enviar a revisión (CA-22) -->
                        <button
                            type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-2.5 rounded-full bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] text-white text-xs font-bold shadow-md hover:shadow-lg transition-all cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Guardar y enviar a revisión</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
