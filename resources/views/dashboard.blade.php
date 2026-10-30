<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                </div>
            </div>
        </div>
    </div>

    <!-- Ventana emergente SweetAlert2 institucional con bordes elegantes y detalles en rojo vino y dorado -->
    @if (session('status'))
        <script>
            (function() {
                function showNotification() {
                    if (typeof window.Swal !== 'undefined') {
                        window.Swal.fire({
                            html: `
                                <div class="relative pt-1 pb-1 px-1 text-center">
                                    <!-- Franja decorativa superior degradada vino a dorado -->
                                    <div class="absolute -top-6 -inset-x-6 h-1.5 bg-gradient-to-r from-[#691C32] via-[#941A3E] to-[#C59B3F]"></div>

                                    <!-- Icono circular con aro dorado, brillo suave y palomita en rojo vino -->
                                    <div class="mx-auto w-16 h-16 rounded-full border-[2.5px] border-[#C59B3F] bg-gradient-to-b from-amber-50 to-orange-50/40 flex items-center justify-center mb-3.5 shadow-md ring-4 ring-amber-100/70">
                                        <svg class="w-8 h-8 text-[#691C32]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </div>

                                    <!-- Título institucional -->
                                    <h3 class="text-xl font-black text-slate-800 tracking-tight">
                                        {{ session('status') }}
                                    </h3>

                                    <!-- Mensaje informativo -->
                                    <p class="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed max-w-[270px] mx-auto">
                                        Tu cuenta institucional ha sido creada y configurada con éxito.
                                    </p>

                                    <!-- Insignia informativa sutil -->
                                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-center">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 border border-amber-200/80 text-[11px] font-semibold text-amber-900 shadow-xs">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Sesión activa &bull; Usuario general
                                        </span>
                                    </div>
                                </div>
                            `,
                            showConfirmButton: true,
                            confirmButtonText: 'Continuar al panel',
                            buttonsStyling: false,
                            background: '#FCFBF9',
                            backdrop: 'rgba(26, 4, 10, 0.5)',
                            showCloseButton: false,
                            customClass: {
                                popup: 'rounded-3xl shadow-2xl border-2 border-amber-400/60 ring-1 ring-black/5 p-6 max-w-sm mx-auto overflow-hidden',
                                confirmButton: 'inline-flex items-center justify-center px-8 py-2.5 rounded-full font-bold text-xs tracking-wide text-white bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] active:bg-[#3B0715] border border-amber-300/40 shadow-md hover:shadow-lg transition-all duration-150 cursor-pointer mt-3.5 focus:outline-none focus:ring-2 focus:ring-[#691C32] focus:ring-offset-2'
                            }
                        });
                    } else {
                        setTimeout(showNotification, 50);
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', showNotification);
                } else {
                    showNotification();
                }
            })();
        </script>
    @endif
</x-app-layout>
