<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('reportes.mis-reportes') }}" class="p-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 transition-colors" title="Volver a Mis Reportes">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <h2 class="font-extrabold text-lg sm:text-xl text-slate-900 leading-tight">
                        Cancelar reporte de hallazgo
                    </h2>
                    <p class="text-xs text-slate-500">
                        Confirmación de cancelación para el folio <span class="font-bold text-[#691C32]">{{ $publicacion->folio }}</span>
                    </p>
                </div>
            </div>
            <a
                href="{{ route('reportes.mis-reportes') }}"
                class="px-4 py-2 rounded-full border border-slate-300 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-2xs"
            >
                Volver
            </a>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-50/60 min-h-[calc(100vh-140px)]">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8">

                <!-- Encabezado de Advertencia (CA-33) -->
                <div class="flex items-start gap-4 p-4 rounded-2xl bg-amber-50/70 border border-amber-200/80 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-amber-900 mb-1">
                            ¿Estás seguro de cancelar este reporte?
                        </h3>
                        <p class="text-xs text-amber-800 leading-relaxed">
                            Al confirmar la cancelación, <strong>el reporte quedará cancelado y fuera del muro institucional</strong>. El registro se conservará de forma privada en tu historial sin posibilidad de edición ni reenvío posterior.
                        </p>
                    </div>
                </div>

                <!-- Resumen de datos del reporte (CA-35) -->
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 mb-6 space-y-2 text-xs">
                    <div class="flex justify-between items-center py-1 border-b border-slate-200/60">
                        <span class="text-slate-500 font-medium">Folio único:</span>
                        <span class="font-bold text-slate-800">{{ $publicacion->folio }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-slate-200/60">
                        <span class="text-slate-500 font-medium">Objeto reportado:</span>
                        <span class="font-bold text-slate-800">{{ $publicacion->nombre }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-slate-200/60">
                        <span class="text-slate-500 font-medium">Estado actual:</span>
                        <span class="font-bold text-amber-800">{{ $publicacion->estado }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1">
                        <span class="text-slate-500 font-medium">Fecha de registro:</span>
                        <span class="font-bold text-slate-800">{{ $publicacion->fecha_registro_texto }}</span>
                    </div>
                </div>

                <!-- Formulario de Cancelación (CA-32, CA-33) -->
                <form method="POST" action="{{ route('reportes.cancel', $publicacion->id) }}" class="space-y-6">
                    @csrf

                    <div>
                        <label for="motivo_cancelacion" class="block text-xs font-bold text-slate-800 mb-1.5">
                            Motivo de cancelación <span class="text-rose-600 font-bold">*</span>
                        </label>
                        <p class="text-[11px] text-slate-500 mb-2">
                            Indica brevemente la razón por la que cancelas el reporte (ej. "El objeto ya fue devuelto a su dueño", "Error en los datos del hallazgo", etc.).
                        </p>
                        <textarea
                            id="motivo_cancelacion"
                            name="motivo_cancelacion"
                            rows="3"
                            required
                            maxlength="500"
                            placeholder="Escribe el motivo de la cancelación..."
                            class="block w-full rounded-2xl border-slate-200 text-sm focus:border-[#691C32] focus:ring-[#691C32] @error('motivo_cancelacion') border-rose-300 ring-1 ring-rose-300 @enderror"
                        >{{ old('motivo_cancelacion') }}</textarea>

                        @error('motivo_cancelacion')
                            <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Botones de Acción: Confirmar cancelación y Volver (CA-33, CA-34) -->
                    <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-end gap-3">
                        <!-- Botón Volver (CA-34: descarta y regresa sin registrar cancelación) -->
                        <a
                            href="{{ route('reportes.mis-reportes') }}"
                            class="w-full sm:w-auto text-center px-6 py-2.5 rounded-full border border-slate-300 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-2xs"
                        >
                            Volver
                        </a>

                        <!-- Botón Confirmar cancelación (CA-33) -->
                        <button
                            type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-full bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold shadow-md hover:shadow-lg transition-all cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <span>Confirmar cancelación</span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>

