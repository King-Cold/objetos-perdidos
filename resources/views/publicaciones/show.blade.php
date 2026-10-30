@auth
    <x-app-layout>
        <x-slot name="header">
            <div>
                <h2 class="font-black text-xl text-slate-800 leading-tight">
                    Detalle de Publicación
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Información y características del objeto en resguardo
                </p>
            </div>
        </x-slot>

        <div class="py-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs">
                    <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
                        <span class="px-3 py-1 rounded-full bg-amber-50 border border-amber-200 text-xs font-bold text-amber-900">
                            {{ $publicacion->categoria }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            {{ $publicacion->estado }}
                        </span>
                    </div>

                    <h1 class="text-2xl font-black text-slate-900 mb-2">
                        {{ $publicacion->titulo }}
                    </h1>

                    <p class="text-xs text-slate-500 mb-6">
                        Publicado el {{ $publicacion->created_at->format('d/m/Y H:i') }} &bull; Lugar de resguardo: <strong>{{ $publicacion->lugar_hallazgo ?? 'Biblioteca Campus Poniente' }}</strong>
                    </p>

                    <div class="prose prose-slate text-sm text-slate-700 leading-relaxed mb-8">
                        {{ $publicacion->descripcion }}
                    </div>

                    <div class="pt-6 border-t border-slate-100 flex items-center justify-between">
                        <a href="{{ route('muro') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                            Volver al Muro
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </x-app-layout>
@else
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="csrf-token" content="{{ csrf_token() }}">

            <title>{{ $publicacion->titulo }} - Detalle &bull; TecNM Campus Poniente</title>

            <link rel="preconnect" href="https://fonts.bunny.net">
            <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

            @vite(['resources/css/app.css', 'resources/js/app.js'])
        </head>
        <body class="font-sans antialiased bg-gray-100 min-h-screen text-slate-800 flex flex-col">
            <!-- Header para Invitados -->
            <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
                <div class="max-w-4xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="p-1.5 rounded-xl bg-white shadow-xs ring-1 ring-amber-400">
                            <img src="{{ asset('images/logo-tecnm.png') }}" alt="TecNM" class="h-7 w-auto" />
                        </div>
                        <span class="text-sm font-extrabold text-slate-900">
                            Detalle de Publicación
                        </span>
                    </div>
                    <a href="{{ route('muro') }}" class="px-3.5 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                        Volver al Muro
                    </a>
                </div>
            </header>

            <!-- Contenedor Principal -->
            <main class="max-w-4xl mx-auto px-4 sm:px-6 py-8 flex-1 w-full">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs">
                    <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
                        <span class="px-3 py-1 rounded-full bg-amber-50 border border-amber-200 text-xs font-bold text-amber-900">
                            {{ $publicacion->categoria }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            {{ $publicacion->estado }}
                        </span>
                    </div>

                    <h1 class="text-2xl font-black text-slate-900 mb-2">
                        {{ $publicacion->titulo }}
                    </h1>

                    <p class="text-xs text-slate-500 mb-6">
                        Publicado el {{ $publicacion->created_at->format('d/m/Y H:i') }} &bull; Lugar de resguardo: <strong>{{ $publicacion->lugar_hallazgo ?? 'Biblioteca Campus Poniente' }}</strong>
                    </p>

                    <div class="prose prose-slate text-sm text-slate-700 leading-relaxed mb-8">
                        {{ $publicacion->descripcion }}
                    </div>

                    <div class="pt-6 border-t border-slate-100 flex items-center justify-between">
                        <a href="{{ route('muro') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors">
                            Volver al Muro
                        </a>
                        <a href="{{ route('login') }}" class="px-5 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                            Iniciar sesión para reclamar objeto
                        </a>
                    </div>
                </div>
            </main>
        </body>
    </html>
@endauth
