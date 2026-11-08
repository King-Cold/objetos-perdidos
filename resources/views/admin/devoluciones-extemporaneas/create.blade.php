<x-app-layout>
    <div class="min-h-screen bg-[#F5F6F8] py-8 text-[#1F2937]">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

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

            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">
                    Identificación del reporte expirado
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <span class="text-slate-500 font-semibold block uppercase text-[10px]">Folio del reporte</span>
                        <strong class="font-mono text-sm text-[#691C32] mt-0.5 block">{{ $reporte->folio }}</strong>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <span class="text-slate-500 font-semibold block uppercase text-[10px]">Objeto</span>
                        <strong class="text-sm text-slate-900 mt-0.5 block">{{ $reporte->nombre ?? $reporte->titulo }}</strong>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <span class="text-slate-500 font-semibold block uppercase text-[10px]">Autor del reporte</span>
                        <strong class="text-sm text-slate-900 mt-0.5 block">{{ $reporte->user?->name ?? 'Autor' }}</strong>
                    </div>

                    <div class="p-3.5 rounded-xl bg-rose-50/70 border border-rose-200">
                        <span class="text-rose-800 font-semibold block uppercase text-[10px]">Vencimiento original</span>
                        <strong class="text-sm text-rose-950 mt-0.5 block">
                            {{ $reporte->fecha_vencimiento ? $reporte->fecha_vencimiento->format('d/m/Y • H:i') : 'Expirado' }}
                        </strong>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

