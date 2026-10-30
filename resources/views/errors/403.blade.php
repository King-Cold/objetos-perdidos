<x-guest-layout :raw="true">
    <div class="min-h-screen bg-slate-900 flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-3xl tecnm-card-shadow p-8 text-center relative overflow-hidden">
            <!-- Franja decorativa vino y dorada -->
            <div class="absolute top-0 inset-x-0 h-1.5 bg-gradient-to-r from-tecnm-wine via-amber-400 to-tecnm-wine"></div>

            <div class="w-16 h-16 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mx-auto mb-4 border border-red-100 shadow-xs">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <span class="inline-block px-3 py-1 rounded-full bg-red-100/70 text-red-800 text-[11px] font-bold uppercase tracking-wider mb-2">
                Acceso denegado (403)
            </span>

            <h1 class="text-xl font-black text-slate-800 tracking-tight mb-2">
                No tienes permiso para realizar esta acción
            </h1>

            <p class="text-xs text-slate-500 leading-relaxed mb-6">
                {{ $exception->getMessage() ?: 'No cuentas con los privilegios necesarios de tu rol para consultar o ejecutar esta operación.' }}
            </p>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-center gap-3">
                <a href="{{ route('muro') }}" class="w-full inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                    Volver al Muro Público
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>

