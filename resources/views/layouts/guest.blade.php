@props(['raw' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Sistema de Objetos Perdidos - TecNM Campus Poniente') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased min-h-screen relative overflow-x-hidden bg-slate-900">
        <!-- Fondo fotográfico del Campus Poniente: nítido, natural y atenuado con elegancia -->
        <div class="fixed inset-0 z-0 overflow-hidden pointer-events-none" aria-hidden="true">
            <img
                src="{{ asset('images/campus-poniente.jpg') }}"
                alt=""
                class="w-full h-full object-cover object-center filter brightness-[0.92] contrast-[1.02]"
            />
            <!-- Filtro suave de atenuación para que la imagen se aprecie sin saturar de rojo -->
            <div class="absolute inset-0 campus-overlay"></div>
            <!-- Viñeta suave para enfocar la vista en el formulario -->
            <div class="absolute inset-0 campus-vignette"></div>
        </div>

        <!-- Contenedor principal centrado -->
        <div class="relative z-10 min-h-screen w-full flex flex-col items-center justify-center p-3 sm:p-5 lg:p-6">
            @if ($raw)
                {{ $slot }}
            @else
                <div class="w-full sm:max-w-md my-auto">
                    <div class="flex flex-col items-center mb-4 text-center">
                        <a href="/" class="flex flex-col items-center group">
                            <div class="p-2.5 rounded-2xl bg-white shadow-xl ring-2 ring-tecnm-gold/80 mb-2 group-hover:scale-105 transition-transform duration-200">
                                <img src="{{ asset('images/logo-tecnm.png') }}" alt="TecNM Mérida" class="h-16 w-auto" />
                            </div>
                            <span class="text-base font-extrabold text-white drop-shadow">TecNM Campus Poniente</span>
                            <span class="text-xs font-semibold text-amber-200 uppercase tracking-widest mt-0.5">Sistema de Objetos Perdidos</span>
                        </a>
                    </div>
                    <div class="bg-white rounded-2xl tecnm-card-shadow overflow-hidden">
                        <div class="h-1.5 bg-gradient-to-r from-tecnm-wine via-tecnm-gold to-tecnm-wine"></div>
                        <div class="p-6 sm:p-7">
                            {{ $slot }}
                        </div>
                    </div>
                    <div class="mt-4 text-center text-xs text-white/80 drop-shadow-sm">
                        &copy; {{ date('Y') }} TecNM Campus Poniente &bull; Mérida, Yucatán
                    </div>
                </div>
            @endif
        </div>
    </body>
</html>
