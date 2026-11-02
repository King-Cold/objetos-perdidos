<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-3.5 sm:py-4 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        @if (session('status_login') && Auth::check())
            <script>
                (function() {
                    function showLoginSuccessAlert() {
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
                                            ¡Bienvenido(a)!
                                        </h3>

                                        <!-- Nombre del usuario -->
                                        <p class="text-sm font-bold text-slate-800 mt-1">
                                            {{ Auth::user()->name }}
                                        </p>

                                        <!-- Mensaje informativo -->
                                        <p class="text-xs text-slate-500 mt-2 mb-2 leading-relaxed max-w-[270px] mx-auto">
                                            Has iniciado sesión correctamente en el Sistema de Objetos Perdidos del Campus Poniente.
                                        </p>
                                    </div>
                                `,
                                showConfirmButton: true,
                                confirmButtonText: 'Continuar',
                                timer: 4000,
                                timerProgressBar: true,
                                buttonsStyling: false,
                                background: '#FCFBF9',
                                backdrop: 'rgba(26, 4, 10, 0.5)',
                                customClass: {
                                    popup: 'rounded-3xl shadow-2xl border-2 border-amber-400/60 ring-1 ring-black/5 p-6 max-w-sm mx-auto overflow-hidden',
                                    confirmButton: 'inline-flex items-center justify-center px-8 py-2.5 rounded-full font-bold text-xs tracking-wide text-white bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] active:bg-[#3B0715] border border-amber-300/40 shadow-md hover:shadow-lg transition-all duration-150 cursor-pointer mt-3 focus:outline-none focus:ring-2 focus:ring-[#691C32] focus:ring-offset-2'
                                }
                            });
                        } else {
                            setTimeout(showLoginSuccessAlert, 50);
                        }
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', showLoginSuccessAlert);
                    } else {
                        showLoginSuccessAlert();
                    }
                })();
            </script>
        @endif
    </body>
</html>
