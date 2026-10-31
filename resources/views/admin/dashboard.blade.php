<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-black text-xl text-slate-800 leading-tight">
                    Panel Administrativo &bull; Biblioteca TecNM
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Campus Poniente &bull; Control central de resguardo, aprobaciones y entregas
                </p>
            </div>
            <span class="px-3 py-1 rounded-full bg-tecnm-wine text-white text-xs font-bold shadow-xs">
                Administrador/bibliotecario
            </span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Tarjeta de Bienvenida y Métricas Rápidas -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-100 p-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800">
                            Bienvenido al Panel de Control, {{ Auth::user()->name }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Tu sesión cuenta con permisos administrativos completos para resguardar objetos, validar devoluciones y gestionar reportes.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Accesos a Módulos Administrativos -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <!-- Apartado: Moderación de reportes (HU-07 / Tarea 07.1) -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-tecnm-wine/40 transition-colors">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-tecnm-wine/10 text-tecnm-wine flex items-center justify-center mb-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900 mb-1">Moderación de reportes</h4>
                        <p class="text-xs text-slate-500 mb-4">Revisar y moderar los reportes de objetos pendientes antes de su publicación.</p>
                    </div>
                    <a href="{{ route('admin.moderacion.index') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                        Ir a Moderación
                    </a>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-tecnm-wine/10 text-tecnm-wine flex items-center justify-center mb-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900 mb-1">Muro Público</h4>
                        <p class="text-xs text-slate-500 mb-4">Consultar el muro de publicaciones activas visibles para la comunidad.</p>
                    </div>
                    <a href="{{ route('muro') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                        Ir al Muro
                    </a>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900 mb-1">Aprobación de Objetos</h4>
                        <p class="text-xs text-slate-500 mb-4">Revisar y autorizar objetos pendientes de resguardo físico.</p>
                    </div>
                    <span class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-amber-50 text-amber-800 text-xs font-semibold">
                        Módulo activo
                    </span>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900 mb-1">Entregas y Devoluciones</h4>
                        <p class="text-xs text-slate-500 mb-4">Confirmar actas y validar entrega al propietario legítimo.</p>
                    </div>
                    <span class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-emerald-50 text-emerald-800 text-xs font-semibold">
                        Módulo activo
                    </span>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

