<x-app-layout>
    @php
        $pub = $incidencia->publicacion;
        $dev = $incidencia->devolucion;
    @endphp
    <div class="py-8 sm:py-10 bg-[#F8F9FA] min-h-screen">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Barra superior de navegación --}}
            <div class="flex flex-wrap items-center justify-between gap-4">
                <a href="{{ route('admin.incidencias.index') }}"
                   class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-[#4E0B1B] transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Volver al listado de incidencias
                </a>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($pub)
                        <a href="{{ route('admin.reportes.historial', $pub->id) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-sm transition">
                            Consultar historial del objeto
                        </a>
                    @endif
                    @if ($dev)
                        <a href="{{ route('admin.devoluciones.show', $dev->id) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-[#E9D8A6] bg-[#FDF8E7] hover:bg-[#F9EFC7] text-[#4E0B1B] text-xs font-bold shadow-sm transition">
                            Ver evento de devolución #{{ $dev->id }}
                        </a>
                    @endif
                </div>
            </div>

            {{-- Mensajes de confirmación y error --}}
            @if (session('status'))
                <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-900 font-medium flex items-center gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-900 font-medium flex items-center gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"></path>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 text-xs text-rose-900 space-y-1 shadow-sm">
                    <p class="font-bold">Revisa los siguientes campos:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Encabezado principal del detalle de la incidencia --}}
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 border-b border-slate-100 pb-6">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-3 py-1 rounded-lg bg-slate-900 text-white text-xs font-extrabold">
                                Incidencia #{{ $incidencia->id }}
                            </span>
                            <span class="px-3 py-1 rounded-lg bg-[#FDF8E7] border border-[#E9D8A6] text-xs font-extrabold text-[#4E0B1B]">
                                {{ $pub?->folio ?? 'SIN-FOLIO' }}
                            </span>
                            <span class="px-3 py-1 rounded-full bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold">
                                Estado: {{ $incidencia->estado }}
                            </span>
                            <span class="px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-700 text-xs font-bold">
                                Disponibilidad: {{ $pub && $pub->isDisponible() ? 'Disponible en el muro' : 'Fuera del muro' }}
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                            {{ $pub?->nombre ?? $pub?->titulo ?? 'Objeto reportado' }}
                        </h1>
                        <p class="text-xs text-slate-500">
                            Identificador de la incidencia: <strong>#{{ $incidencia->id }}</strong> • Seguimiento autorizado para el autor: <strong>{{ $incidencia->seguimiento_autorizado }}</strong>
                        </p>
                    </div>
                </div>

                {{-- Datos clave de la incidencia --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="rounded-2xl bg-slate-50 border border-slate-200/80 p-4">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Fecha y hora de apertura</span>
                        <span class="text-sm font-extrabold text-slate-900 mt-1 block">{{ $incidencia->fecha_apertura_texto }}</span>
                    </div>
                    <div class="rounded-2xl bg-slate-50 border border-slate-200/80 p-4">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Administrador/bibliotecario responsable</span>
                        <span class="text-sm font-extrabold text-slate-900 mt-1 block">{{ $incidencia->user?->name ?? 'Administrador / Bibliotecario' }}</span>
                        <span class="text-xs text-slate-500">{{ $incidencia->user?->email }}</span>
                    </div>
                    <div class="rounded-2xl bg-slate-50 border border-slate-200/80 p-4">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Condición en el muro público</span>
                        <span class="text-sm font-extrabold text-rose-700 mt-1 block">
                            {{ $pub && $pub->isDisponible() ? 'Disponible en el muro' : 'Fuera del muro institucional' }}
                        </span>
                        <span class="text-xs text-slate-500">Permanece fuera del muro mientras exista duda de entrega</span>
                    </div>
                </div>

                {{-- Motivo obligatorio registrado --}}
                <div class="rounded-2xl bg-rose-50/50 border border-rose-200 p-5 space-y-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-rose-900 block">
                        Motivo registrado de apertura
                    </span>
                    <p class="text-sm text-slate-900 font-medium whitespace-pre-line leading-relaxed">{{ $incidencia->motivo }}</p>
                </div>

                {{-- Decisión / Resultado de revisión vigente si ya se registró una decisión --}}
                @if ($incidencia->resultado || $incidencia->motivo_resolucion)
                    <div class="rounded-2xl bg-slate-50 border border-slate-200 p-5 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-slate-700">
                                Decisión de revisión registrada: {{ $incidencia->resultado }}
                            </span>
                            <span class="text-xs text-slate-500 font-semibold">
                                Responsable: {{ $incidencia->responsableRevision?->name ?? $incidencia->cerradaPor?->name ?? 'Administrador' }}
                                @if ($incidencia->fecha_revision_texto)
                                    • {{ $incidencia->fecha_revision_texto }}
                                @endif
                            </span>
                        </div>
                        @if ($incidencia->motivo_resolucion)
                            <p class="text-sm text-slate-800 font-medium">{{ $incidencia->motivo_resolucion }}</p>
                        @endif
                        @if ($incidencia->solicitud_recuperacion)
                            <div class="pt-2 border-t border-slate-200/80 text-xs text-orange-900 font-semibold">
                                Solicitud de recuperación en biblioteca: {{ $incidencia->solicitud_recuperacion }}
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Garantía de neutralidad sobre el receptor --}}
                <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900 leading-relaxed">
                    <p class="font-bold text-amber-950 mb-1">Garantía institucional de no presunción de culpabilidad</p>
                    <p>
                        Esta incidencia mantiene el objeto fuera del muro público y del detalle general mientras se aclara la duda de entrega. <strong>Su apertura no señala automáticamente al receptor como responsable de una entrega indebida ni confirma una irregularidad.</strong>
                    </p>
                </div>
            </div>

            {{-- Información del reporte original y referencia al evento de devolución --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <h2 class="text-base font-extrabold text-slate-900 border-b border-slate-100 pb-3">
                        Datos del reporte original
                    </h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <dt class="text-slate-500">Folio del reporte original</dt>
                            <dd class="font-bold text-slate-900 mt-0.5">{{ $pub?->folio ?? 'SIN-FOLIO' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Nombre del objeto</dt>
                            <dd class="font-bold text-slate-900 mt-0.5">{{ $pub?->nombre ?? $pub?->titulo }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Autor del reporte</dt>
                            <dd class="font-semibold text-slate-800 mt-0.5">{{ $pub?->user?->name ?? 'Autor' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Estado del objeto</dt>
                            <dd class="font-semibold text-slate-800 mt-0.5">{{ $pub?->estado }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Situación de resguardo</dt>
                            <dd class="font-semibold text-slate-800 mt-0.5">{{ $pub?->situacion_resguardo ?? 'En posesión del autor' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Disponibilidad general</dt>
                            <dd class="font-semibold text-rose-700 mt-0.5">{{ $pub && $pub->isDisponible() ? 'Disponible' : 'Fuera del muro' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <h2 class="text-base font-extrabold text-slate-900 border-b border-slate-100 pb-3">
                        Referencia al evento de devolución relacionado
                    </h2>
                    @if ($dev)
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <dt class="text-slate-500">Evento de devolución</dt>
                                <dd class="font-bold text-slate-900 mt-0.5">Devolución #{{ $dev->id }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Receptor registrado</dt>
                                <dd class="font-bold text-slate-900 mt-0.5">{{ $dev->receptor_nombre }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Identificador / Contacto</dt>
                                <dd class="font-semibold text-slate-800 mt-0.5">{{ $dev->receptor_identificador }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Momento físico de entrega</dt>
                                <dd class="font-semibold text-slate-800 mt-0.5">{{ $dev->momento_entrega_texto }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Estado de la devolución</dt>
                                <dd class="font-semibold text-slate-800 mt-0.5">{{ $dev->estado }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-slate-500">Explicación de comprobación de propiedad</dt>
                                <dd class="font-medium text-slate-800 mt-0.5">{{ $dev->explicacion_propiedad }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="text-xs text-slate-500">
                            No se especificó un evento de devolución individual.
                        </p>
                    @endif
                </div>
            </div>

            {{-- SECCIÓN CA-03: OBSERVACIONES DEL CASO (Aclaraciones, contactos externos y resultados) --}}
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">
                            Observaciones del caso, aclaraciones y contactos externos
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Bitácora administrativa con fecha y responsable de cada aclaración, contacto por medios externos y resultado obtenido.
                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-bold">
                        Total: {{ $incidencia->observaciones->count() }}
                    </span>
                </div>

                @if ($incidencia->observaciones->isEmpty())
                    <div class="rounded-2xl bg-slate-50 border border-slate-200/70 p-6 text-center text-xs text-slate-500">
                        Aún no se han registrado observaciones, aclaraciones ni contactos externos en esta incidencia.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($incidencia->observaciones as $obs)
                            <div class="rounded-2xl bg-slate-50 border border-slate-200/80 p-4 space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="px-2.5 py-0.5 rounded-full bg-[#FDF8E7] border border-[#E9D8A6] text-[11px] font-extrabold text-[#4E0B1B]">
                                            {{ $obs->tipo }}
                                        </span>
                                        @if ($obs->medio_contacto)
                                            <span class="px-2.5 py-0.5 rounded-full bg-white border border-slate-200 text-[11px] font-semibold text-slate-700">
                                                Medio: {{ $obs->medio_contacto }}
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-xs font-semibold text-slate-500">
                                        {{ $obs->fecha_registro_texto }} • Responsable: <strong class="text-slate-800">{{ $obs->user?->name ?? 'Administrador' }}</strong>
                                    </span>
                                </div>
                                <p class="text-xs sm:text-sm text-slate-800 font-medium whitespace-pre-line">{{ $obs->descripcion }}</p>
                                @if ($obs->resultado_obtenido)
                                    <div class="pt-2 border-t border-slate-200/70 text-xs text-slate-700">
                                        <strong>Resultado obtenido:</strong> {{ $obs->resultado_obtenido }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($incidencia->isActiva())
                    <form method="POST"
                          action="{{ route('admin.incidencias.observaciones.store', $incidencia->id) }}"
                          class="pt-4 border-t border-slate-100 space-y-4">
                        @csrf
                        <h3 class="text-sm font-extrabold text-slate-900">
                            Registrar nueva observación, aclaración o contacto externo
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="tipo" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                    Tipo de registro
                                </label>
                                <select id="tipo"
                                        name="tipo"
                                        class="w-full rounded-xl border-slate-300 text-xs sm:text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                                    <option value="Aclaración">Aclaración del caso</option>
                                    <option value="Contacto por medio externo">Contacto realizado por medio externo</option>
                                    <option value="Resultado obtenido">Resultado obtenido</option>
                                </select>
                            </div>
                            <div>
                                <label for="medio_contacto" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                    Medio de contacto externo (opcional)
                                </label>
                                <input type="text"
                                       id="medio_contacto"
                                       name="medio_contacto"
                                       value="{{ old('medio_contacto') }}"
                                       placeholder="Ej. Correo institucional, llamada telefónica, atención presencial..."
                                       class="w-full rounded-xl border-slate-300 text-xs sm:text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                            </div>
                        </div>

                        <div>
                            <label for="descripcion" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Descripción de la aclaración o contacto <span class="text-rose-600">*</span>
                            </label>
                            <textarea id="descripcion"
                                      name="descripcion"
                                      rows="3"
                                      required
                                      placeholder="Registra la aclaración, el contacto externo realizado o el seguimiento obtenido..."
                                      class="w-full rounded-2xl border-slate-300 text-xs sm:text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">{{ old('descripcion') }}</textarea>
                        </div>

                        <div>
                            <label for="resultado_obtenido" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Resultado obtenido (opcional)
                            </label>
                            <input type="text"
                                   id="resultado_obtenido"
                                   name="resultado_obtenido"
                                   value="{{ old('resultado_obtenido') }}"
                                   placeholder="Ej. Se acordó presentar evidencia complementaria / Prometió acudir a biblioteca..."
                                   class="w-full rounded-xl border-slate-300 text-xs sm:text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shadow-sm transition">
                                Guardar observación
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- SECCIÓN CA-05, CA-06, CA-07: GESTIÓN DE DECISIONES DE REVISIÓN --}}
            @if ($incidencia->isActiva())
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-extrabold text-slate-900">
                            Gestionar decisiones de revisión de la incidencia
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Selecciona el dictamen correspondiente según el resultado de la revisión. El objeto no se reactivará en el muro público mientras exista duda.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {{-- Opción 1: CA-05 Entrega aclarada como correcta --}}
                        <form method="POST"
                              action="{{ route('admin.incidencias.confirmar-entrega-correcta', $incidencia->id) }}"
                              class="rounded-2xl bg-emerald-50/50 border border-emerald-200 p-5 flex flex-col justify-between space-y-4">
                            @csrf
                            <div class="space-y-2">
                                <span class="inline-block px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-900 text-[11px] font-extrabold">
                                    1. Entrega aclarada como correcta
                                </span>
                                <h3 class="text-sm font-extrabold text-slate-900">
                                    Confirmar entrega correcta y aprobar devolución
                                </h3>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Si el objeto fue entregado a la persona correcta y la evidencia está completa, aprueba la devolución y cierra la incidencia sin exigir recuperar el objeto ni recibirlo en biblioteca.
                                </p>
                                <div class="pt-2">
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                                        Motivo de confirmación y cierre <span class="text-rose-600">*</span>
                                    </label>
                                    <textarea name="motivo"
                                              rows="3"
                                              required
                                              placeholder="Explica por qué se confirma que la entrega fue correcta..."
                                              class="w-full rounded-xl border-emerald-300 text-xs focus:border-emerald-700 focus:ring-emerald-700"></textarea>
                                </div>
                            </div>
                            <button type="submit"
                                    class="w-full py-2.5 px-4 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-sm transition">
                                Aprobar devolución y cerrar incidencia
                            </button>
                        </form>

                        {{-- Opción 2: CA-06 Prueba incompleta / Evidencia insuficiente sin sospecha --}}
                        <form method="POST"
                              action="{{ route('admin.incidencias.solicitar-correccion', $incidencia->id) }}"
                              class="rounded-2xl bg-amber-50/60 border border-amber-200 p-5 flex flex-col justify-between space-y-4">
                            @csrf
                            <div class="space-y-2">
                                <span class="inline-block px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 text-[11px] font-extrabold">
                                    2. Prueba incompleta
                                </span>
                                <h3 class="text-sm font-extrabold text-slate-900">
                                    Solicitar corrección de devolución existente
                                </h3>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Si solo faltan datos o una fotografía clara sin sospecha de entrega incorrecta, solicita corregir la misma devolución («En espera de corrección»), sin crear otra entrega ni reactivar el objeto.
                                </p>
                                <div class="pt-2">
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                                        Motivo de corrección <span class="text-rose-600">*</span>
                                    </label>
                                    <textarea name="motivo"
                                              rows="3"
                                              required
                                              placeholder="Indica qué datos o fotografía debe corregir el autor..."
                                              class="w-full rounded-xl border-amber-300 text-xs focus:border-amber-700 focus:ring-amber-700"></textarea>
                                </div>
                            </div>
                            <button type="submit"
                                    class="w-full py-2.5 px-4 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-sm transition">
                                Solicitar corrección
                            </button>
                        </form>

                        {{-- Opción 3: CA-07 Entrega incorrecta confirmada --}}
                        <form method="POST"
                              action="{{ route('admin.incidencias.confirmar-entrega-incorrecta', $incidencia->id) }}"
                              class="rounded-2xl bg-rose-50/60 border border-rose-200 p-5 flex flex-col justify-between space-y-4">
                            @csrf
                            <div class="space-y-2">
                                <span class="inline-block px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-900 text-[11px] font-extrabold">
                                    3. Entrega incorrecta confirmada
                                </span>
                                <h3 class="text-sm font-extrabold text-slate-900">
                                    Pasar a «En espera de recuperación»
                                </h3>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Si se confirma que el receptor no era el dueño, registra esa conclusión y la solicitud de llevar el objeto a biblioteca. La promesa de devolverlo no se considera recuperación física.
                                </p>
                                <div class="pt-2 space-y-2">
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                                            Conclusión de entrega incorrecta <span class="text-rose-600">*</span>
                                        </label>
                                        <textarea name="motivo"
                                                  rows="2"
                                                  required
                                                  placeholder="Registra la conclusión que confirma que el receptor no era el dueño..."
                                                  class="w-full rounded-xl border-rose-300 text-xs focus:border-rose-700 focus:ring-rose-700"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                                            Solicitud de entrega en biblioteca <span class="text-rose-600">*</span>
                                        </label>
                                        <input type="text"
                                               name="solicitud_recuperacion"
                                               required
                                               value="Se solicita llevar y entregar físicamente el objeto en biblioteca institucional para confirmar su recuperación."
                                               class="w-full rounded-xl border-rose-300 text-xs focus:border-rose-700 focus:ring-rose-700">
                                    </div>
                                </div>
                            </div>
                            <button type="submit"
                                    class="w-full py-2.5 px-4 rounded-xl bg-[#4E0B1B] hover:bg-[#3A0814] text-white text-xs font-bold shadow-sm transition">
                                Confirmar y pasar a espera de recuperación
                            </button>
                        </form>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
