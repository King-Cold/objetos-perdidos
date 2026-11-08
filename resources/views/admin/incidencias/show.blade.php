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
                        @if ($incidencia->responsable_canalizacion)
                            <div class="pt-2 border-t border-slate-200/80 text-xs text-slate-800 space-y-1">
                                <p><strong>Destino / Responsable de canalización institucional:</strong> {{ $incidencia->responsable_canalizacion }}</p>
                                @if ($incidencia->observaciones_seguimiento)
                                    <p><strong>Observaciones de seguimiento:</strong> {{ $incidencia->observaciones_seguimiento }}</p>
                                @endif
                            </div>
                        @endif
                        @if ($incidencia->fecha_cierre_texto)
                            <div class="pt-2 border-t border-slate-200/80 text-xs text-slate-600">
                                <strong>Fecha de resolución / cierre:</strong> {{ $incidencia->fecha_cierre_texto }}
                                • <strong>Responsable:</strong> {{ $incidencia->cerradaPor?->name ?? $incidencia->responsableRevision?->name ?? 'Administrador' }}
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Resumen de recepción física en biblioteca cuando la recuperación ya fue confirmada --}}
                @php
                    $recepcionVinculada = $incidencia->recepcion ?? $pub?->recepcion;
                @endphp
                @if ($recepcionVinculada)
                    <div class="rounded-2xl bg-emerald-50/70 border border-emerald-200 p-5 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-emerald-900">
                                Recuperación física confirmada mediante recepción en biblioteca (#{{ $recepcionVinculada->id }})
                            </span>
                            <span class="text-xs text-emerald-800 font-semibold">
                                Recibido por: {{ $recepcionVinculada->user?->name ?? 'Personal de biblioteca' }}
                                • {{ $incidencia->fecha_recuperacion_texto ?? $recepcionVinculada->fecha_captura_texto }}
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-slate-800 pt-1">
                            <div>
                                <span class="text-slate-500 block">Ubicación en biblioteca:</span>
                                <strong>{{ $recepcionVinculada->ubicacion_resguardo }}</strong>
                            </div>
                            <div>
                                <span class="text-slate-500 block">Condición registrada:</span>
                                <strong>{{ $recepcionVinculada->condicion_objeto }}</strong>
                            </div>
                            <div>
                                <span class="text-slate-500 block">Antecedente de entrega anterior:</span>
                                <strong>Conservado con su evidencia privada (no marcada como correcta)</strong>
                            </div>
                        </div>
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
                            <dd class="font-semibold {{ $pub && $pub->isDisponible() ? 'text-emerald-700' : 'text-rose-700' }} mt-0.5">{{ $pub ? $pub->disponibilidad_texto : 'Fuera del muro' }}</dd>
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

                {{-- SECCIÓN CA-07, CA-08 y CA-13: VINCULAR RECUPERACIÓN FÍSICA EN BIBLIOTECA Y CIERRE TRAS RECUPERACIÓN --}}
                @if ($incidencia->estado === \App\Models\Incidencia::ESTADO_ESPERA_RECUPERACION)
                    <div class="bg-white rounded-3xl border border-orange-200 shadow-sm p-6 sm:p-8 space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <span class="inline-block px-2.5 py-0.5 rounded-full bg-orange-100 text-orange-900 text-[11px] font-extrabold mb-1">
                                    Seguimiento de entrega incorrecta confirmada
                                </span>
                                <h2 class="text-lg font-extrabold text-slate-900">
                                    Recuperación física del objeto en biblioteca y cierre del caso
                                </h2>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    Una promesa de devolver el objeto no se considera recuperación. La recuperación se completa únicamente mediante la recepción física en biblioteca, conservando los antecedentes y evidencias de la entrega incorrecta.
                                </p>
                            </div>
                            @if ($pub && !$incidencia->hasRecuperacionFisicaConfirmada())
                                <a href="{{ route('admin.recepciones.create', ['id' => $pub->id, 'from_incidencia' => 1]) }}"
                                   class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-[#4E0B1B] hover:bg-[#3A0814] text-white text-xs font-bold shadow-sm transition">
                                    Ir al formulario de recepción en biblioteca
                                </a>
                            @endif
                        </div>

                        @if (!$incidencia->hasRecuperacionFisicaConfirmada() && !$recepcionVinculada)
                            <form method="POST"
                                  action="{{ route('admin.incidencias.recuperacion-fisica', $incidencia->id) }}"
                                  class="rounded-2xl bg-orange-50/50 border border-orange-200 p-5 space-y-4">
                                @csrf
                                <h3 class="text-sm font-extrabold text-slate-900">
                                    Confirmar recuperación mediante recepción física en biblioteca
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                            Fecha real de recepción <span class="text-rose-600">*</span>
                                        </label>
                                        <input type="date"
                                               name="fecha_recepcion"
                                               value="{{ old('fecha_recepcion', $fechaRecepcionDefault ?? now()->format('Y-m-d')) }}"
                                               required
                                               class="w-full rounded-xl border-slate-300 text-xs focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                            Hora real de recepción <span class="text-rose-600">*</span>
                                        </label>
                                        <input type="time"
                                               name="hora_recepcion"
                                               value="{{ old('hora_recepcion', $horaRecepcionDefault ?? now()->format('H:i')) }}"
                                               required
                                               class="w-full rounded-xl border-slate-300 text-xs focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                            Ubicación asignada en biblioteca <span class="text-rose-600">*</span>
                                        </label>
                                        <input type="text"
                                               name="ubicacion_resguardo"
                                               value="{{ old('ubicacion_resguardo') }}"
                                               required
                                               placeholder="Ej. Estante A-2, Gaveta de resguardo"
                                               class="w-full rounded-xl border-slate-300 text-xs focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                            Condición física del objeto al recuperarlo <span class="text-rose-600">*</span>
                                        </label>
                                        <textarea name="condicion_objeto"
                                                  rows="2"
                                                  required
                                                  placeholder="Describe las condiciones físicas en que se recibe el objeto recuperado..."
                                                  class="w-full rounded-xl border-slate-300 text-xs focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">{{ old('condicion_objeto') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                            Observaciones de recepción (opcional)
                                        </label>
                                        <textarea name="observaciones"
                                                  rows="2"
                                                  placeholder="Notas adicionales sobre la entrega física en biblioteca..."
                                                  class="w-full rounded-xl border-slate-300 text-xs focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">{{ old('observaciones') }}</textarea>
                                    </div>
                                </div>
                                <div class="p-3.5 rounded-xl bg-white border border-orange-300">
                                    <label class="flex items-start gap-2.5 cursor-pointer">
                                        <input type="checkbox"
                                               name="confirmacion_cotejo"
                                               value="1"
                                               required
                                               class="mt-0.5 rounded border-orange-400 text-[#4E0B1B] focus:ring-[#4E0B1B]">
                                        <span class="text-xs font-bold text-slate-800">
                                            Confirmo que he recibido físicamente el objeto en biblioteca y que corresponde al reporte original. Esta recepción conserva el antecedente de la entrega anterior sin marcarla como correcta.
                                        </span>
                                    </label>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit"
                                            class="px-5 py-2.5 rounded-xl bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold shadow-sm transition">
                                        Registrar recepción física y confirmar recuperación
                                    </button>
                                </div>
                            </form>
                        @else
                            <form method="POST"
                                  action="{{ route('admin.incidencias.cerrar', $incidencia->id) }}"
                                  class="rounded-2xl bg-emerald-50/60 border border-emerald-200 p-5 space-y-4">
                                @csrf
                                <div class="space-y-1">
                                    <span class="inline-block px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-900 text-[11px] font-extrabold">
                                        Recuperación física en biblioteca confirmada
                                    </span>
                                    <h3 class="text-sm font-extrabold text-slate-900">
                                        Cerrar incidencia registrando resultado, motivo, fecha y responsable
                                    </h3>
                                    <p class="text-xs text-slate-600">
                                        Al haberse confirmado la recepción física del objeto en biblioteca, puedes cerrar la incidencia conservando todos los antecedentes y evidencias privadas del caso.
                                    </p>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                            Resultado de la resolución <span class="text-rose-600">*</span>
                                        </label>
                                        <input type="text"
                                               name="resultado"
                                               required
                                               value="Objeto recuperado físicamente en biblioteca — Incidencia cerrada"
                                               class="w-full rounded-xl border-emerald-300 text-xs focus:border-emerald-700 focus:ring-emerald-700">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                            Motivo de cierre <span class="text-rose-600">*</span>
                                        </label>
                                        <input type="text"
                                               name="motivo"
                                               required
                                               placeholder="Indica el motivo de cierre tras confirmar la recepción física en biblioteca..."
                                               class="w-full rounded-xl border-emerald-300 text-xs focus:border-emerald-700 focus:ring-emerald-700">
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit"
                                            class="px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-sm transition">
                                        Cerrar incidencia con recuperación confirmada
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endif

                {{-- SECCIÓN CA-09: CASO SIN SOLUCIÓN («Sin resolver y canalizada») --}}
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-5">
                    <div class="border-b border-slate-100 pb-4">
                        <span class="inline-block px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-800 text-[11px] font-extrabold mb-1">
                            Caso sin solución
                        </span>
                        <h2 class="text-lg font-extrabold text-slate-900">
                            Marcar incidencia como «Sin resolver y canalizada»
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Si no es posible aclarar el caso ni recuperar físicamente el objeto, registra la canalización institucional con motivo, destino y observaciones de seguimiento. El objeto no se marcará como «Devuelto» ni como «Disponible», y el sistema no impondrá sanciones automáticas.
                        </p>
                    </div>

                    <form method="POST"
                          action="{{ route('admin.incidencias.canalizar', $incidencia->id) }}"
                          class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                    Destino o instancia institucional de canalización <span class="text-rose-600">*</span>
                                </label>
                                <input type="text"
                                       name="responsable_canalizacion"
                                       value="{{ old('responsable_canalizacion') }}"
                                       required
                                       placeholder="Ej. Subdirección de Servicios Administrativos / Jefatura de Centro de Información"
                                       class="w-full rounded-xl border-slate-300 text-xs sm:text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                    Motivo por el que no fue posible resolver ni recuperar <span class="text-rose-600">*</span>
                                </label>
                                <input type="text"
                                       name="motivo"
                                       value="{{ old('motivo') }}"
                                       required
                                       placeholder="Describe el motivo por el cual el caso se canaliza sin resolver..."
                                       class="w-full rounded-xl border-slate-300 text-xs sm:text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Observaciones de seguimiento institucional <span class="text-rose-600">*</span>
                            </label>
                            <textarea name="observaciones_seguimiento"
                                      rows="2"
                                      required
                                      placeholder="Registra las gestiones realizadas, antecedentes que se turnan y observaciones de seguimiento..."
                                      class="w-full rounded-2xl border-slate-300 text-xs sm:text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">{{ old('observaciones_seguimiento') }}</textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold shadow-sm transition">
                                Marcar como «Sin resolver y canalizada»
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- PUBLICACIÓN EXCEPCIONAL ÚNICA DE 14 DÍAS TRAS RECUPERACIÓN EN BIBLIOTECA --}}
            @if ($pub && ($incidencia->hasRecuperacionFisicaConfirmada() || $recepcionVinculada))
                <div class="bg-white rounded-3xl border border-emerald-200 shadow-sm p-6 sm:p-8 space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                        <div>
                            <span class="inline-block px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-900 text-[11px] font-extrabold mb-1">
                                Difusión excepcional tras recuperación en biblioteca
                            </span>
                            <h2 class="text-lg font-extrabold text-slate-900">
                                Publicación excepcional única de 14 días como «Disponible en biblioteca»
                            </h2>
                            <p class="text-xs text-slate-600 mt-0.5">
                                Después de confirmar la recepción física y revisar el contenido, el administrador puede autorizar una sola publicación excepcional de 14 días manteniendo el mismo folio, una fecha especial independiente de la primera aprobación y el año de resguardo calculado desde la recepción.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            @if ($recepcionVinculada)
                                <a href="{{ route('admin.inventario.show', $recepcionVinculada->id) }}"
                                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition">
                                    Ver en inventario de biblioteca
                                </a>
                            @endif
                            @if ($pub->puedeRegistrarDevolucionDesdeBiblioteca())
                                <a href="{{ route('admin.devoluciones-biblioteca.create', $pub->id) }}"
                                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#4E0B1B] hover:bg-[#3A0814] text-white text-xs font-bold shadow-sm transition">
                                    Registrar devolución desde biblioteca
                                </a>
                            @endif
                        </div>
                    </div>

                    @if ((bool) $pub->publicacion_excepcional_autorizada)
                        <div class="rounded-2xl bg-emerald-50/70 border border-emerald-200 p-5 space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    @if ($pub->isPublicacionExcepcionalVigente())
                                        <span class="px-3 py-1 rounded-full bg-emerald-600 text-white text-xs font-extrabold">
                                            Publicación excepcional vigente: «Disponible en biblioteca»
                                        </span>
                                    @elseif ($pub->estado === \App\Models\Publicacion::ESTADO_DEVUELTO)
                                        <span class="px-3 py-1 rounded-full bg-slate-800 text-white text-xs font-extrabold">
                                            Objeto devuelto desde biblioteca (nuevo evento de devolución)
                                        </span>
                                    @else
                                        <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-900 border border-amber-300 text-xs font-extrabold">
                                            Publicación excepcional vencida — Retirada del muro (permanece en inventario de biblioteca)
                                        </span>
                                    @endif
                                </div>
                                <span class="text-xs font-semibold text-emerald-900">
                                    Única publicación excepcional utilizada (1 de 1)
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                                <div class="p-3 rounded-xl bg-white border border-emerald-200/80">
                                    <span class="text-slate-500 block">Folio original conservado</span>
                                    <strong class="font-mono text-slate-900 text-sm">{{ $pub->folio }}</strong>
                                </div>
                                <div class="p-3 rounded-xl bg-white border border-emerald-200/80">
                                    <span class="text-slate-500 block">Fecha original de 1.ª aprobación</span>
                                    <strong class="text-slate-900">{{ $pub->fecha_primera_aprobacion ? $pub->fecha_primera_aprobacion->format('d/m/Y • h:i a') : 'Conservada sin cambios' }}</strong>
                                </div>
                                <div class="p-3 rounded-xl bg-white border border-emerald-200/80">
                                    <span class="text-slate-500 block">Fecha especial y vencimiento (14 días)</span>
                                    <strong class="text-emerald-900 block">Inicio: {{ $pub->fecha_publicacion_excepcional ? $pub->fecha_publicacion_excepcional->format('d/m/Y • h:i a') : '-' }}</strong>
                                    <span class="text-slate-700 font-semibold">Vence: {{ $pub->fecha_vencimiento_excepcional ? $pub->fecha_vencimiento_excepcional->format('d/m/Y • h:i a') : '-' }}</span>
                                </div>
                                <div class="p-3 rounded-xl bg-white border border-emerald-200/80">
                                    <span class="text-slate-500 block">Año de resguardo desde recepción</span>
                                    <strong class="text-slate-900">{{ $recepcionVinculada ? $recepcionVinculada->fecha_limite_anual_texto : '-' }}</strong>
                                </div>
                            </div>

                            @if ($pub->motivo_publicacion_excepcional)
                                <div class="text-xs text-slate-800 pt-1">
                                    <strong>Dictamen de revisión de contenido:</strong> {{ $pub->motivo_publicacion_excepcional }}
                                </div>
                            @endif
                        </div>
                    @elseif ($pub->puedeAutorizarPublicacionExcepcional())
                        <form method="POST"
                              action="{{ route('admin.incidencias.publicacion-excepcional', $incidencia->id) }}"
                              class="rounded-2xl bg-slate-50 border border-slate-200 p-5 space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs bg-white p-3.5 rounded-xl border border-slate-200">
                                <div>
                                    <span class="text-slate-500 block">Folio institucional que se conserva:</span>
                                    <strong class="font-mono text-slate-900">{{ $pub->folio }}</strong>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">1.ª aprobación original (no se altera):</span>
                                    <strong class="text-slate-900">{{ $pub->fecha_primera_aprobacion ? $pub->fecha_primera_aprobacion->format('d/m/Y • h:i a') : 'Registrada' }}</strong>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Año de resguardo desde recepción:</span>
                                    <strong class="text-slate-900">{{ $recepcionVinculada ? $recepcionVinculada->fecha_limite_anual_texto : '-' }}</strong>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                        Nombre del objeto revisado <span class="text-rose-600">*</span>
                                    </label>
                                    <input type="text"
                                           name="nombre"
                                           value="{{ old('nombre', $pub->nombre ?? $pub->titulo) }}"
                                           required
                                           class="w-full rounded-xl border-slate-300 text-xs sm:text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                        Motivo / Dictamen de autorización excepcional <span class="text-rose-600">*</span>
                                    </label>
                                    <input type="text"
                                           name="motivo"
                                           value="{{ old('motivo') }}"
                                           required
                                           placeholder="Indica el dictamen tras revisar el contenido y confirmar la recepción en biblioteca..."
                                           class="w-full rounded-xl border-slate-300 text-xs sm:text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                    Descripción del objeto revisada para el muro <span class="text-rose-600">*</span>
                                </label>
                                <textarea name="descripcion"
                                          rows="2"
                                          required
                                          class="w-full rounded-xl border-slate-300 text-xs sm:text-sm focus:border-[#4E0B1B] focus:ring-[#4E0B1B]">{{ old('descripcion', $pub->descripcion) }}</textarea>
                            </div>

                            <div class="p-3.5 rounded-xl bg-white border border-emerald-300">
                                <label class="flex items-start gap-2.5 cursor-pointer">
                                    <input type="checkbox"
                                           name="confirmacion_revision_contenido"
                                           value="1"
                                           required
                                           class="mt-0.5 rounded border-emerald-400 text-[#4E0B1B] focus:ring-[#4E0B1B]">
                                    <span class="text-xs font-bold text-slate-800">
                                        Confirmo que el objeto fue recibido físicamente en biblioteca y que he revisado el contenido de la publicación para autorizar su única difusión excepcional de 14 días como «Disponible en biblioteca».
                                    </span>
                                </label>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                        class="px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-sm transition">
                                    Autorizar única publicación excepcional de 14 días («Disponible en biblioteca»)
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4 text-xs text-slate-600">
                            No es posible autorizar una publicación excepcional en este momento (verifica que el año de resguardo desde la recepción siga vigente y que el objeto no haya sido devuelto ya desde biblioteca).
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
