@auth
    <x-app-layout>
        <x-slot name="header">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="font-black text-xl text-slate-800 leading-tight">
                        Muro de Objetos Perdidos y Encontrados
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Objetos resguardados en Biblioteca o reportados en el Campus Poniente
                    </p>
                </div>
                <div>
                    <a href="{{ route('reportes.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Encontré un objeto</span>
                    </a>
                </div>
            </div>
        </x-slot>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Mensajes Flash de Sesión (CA-26, CA-29, CA-31) -->
                @if (session('status'))
                    <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-sm font-semibold text-emerald-800 flex items-center gap-3 shadow-xs">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if (session('warning'))
                    <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-300 text-sm font-semibold text-amber-900 flex items-center justify-between shadow-xs">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ session('warning') }}</span>
                        </div>
                        <a href="{{ route('muro') }}" class="text-xs font-bold text-amber-900 underline hover:text-amber-950">
                            Volver al muro
                        </a>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-sm font-semibold text-red-800 flex items-center gap-3 shadow-xs">
                        <svg class="w-5 h-5 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                <!-- Listado de Publicaciones -->
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-black text-slate-900 tracking-tight">Publicaciones Recientes</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Catálogo de objetos perdidos y encontrados en resguardo.</p>
                    </div>
                </div>

                @if (!empty($errorConsulta))
                    <div class="bg-white rounded-3xl p-8 sm:p-10 border border-red-200 shadow-xs text-center max-w-lg mx-auto my-6" role="alert">
                        <div class="w-14 h-14 rounded-full bg-red-50 border border-red-100 flex items-center justify-center mx-auto mb-4 text-red-600">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <h4 class="text-base sm:text-lg font-black text-slate-900 mb-1.5">Error al cargar las publicaciones</h4>
                        <p class="text-xs sm:text-sm text-slate-600 mb-6 leading-relaxed">
                            {{ $mensajeError ?? 'Ocurrió un problema técnico o de conexión al consultar las publicaciones. Por favor, intenta de nuevo.' }}
                        </p>
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                            <a href="{{ request()->fullUrl() }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span>Reintentar</span>
                            </a>
                            <a href="{{ route('reportes.create') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Encontré un objeto</span>
                            </a>
                        </div>
                    </div>
                @elseif (isset($publicaciones) && $publicaciones->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($publicaciones as $pub)
                            <x-muro-tarjeta :publicacion="$pub" />
                        @endforeach
                    </div>

                    {{-- Controles condicionales Anterior y Siguiente --}}
                    <x-muro-paginacion :paginator="$publicaciones" />
                @else
                    <div class="bg-white rounded-3xl p-10 border border-slate-200 text-center max-w-lg mx-auto">
                        <div class="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                        </div>
                        <h4 class="text-base font-bold text-slate-800 mb-1">No hay objetos disponibles</h4>
                        <p class="text-xs text-slate-500 mb-6">Actualmente no existen objetos pendientes de entrega o reportados en el campus.</p>
                        <a href="{{ route('reportes.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Encontré un objeto</span>
                        </a>
                    </div>
                @endif
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

            <title>{{ config('app.name', 'Muro de Objetos Perdidos - TecNM Campus Poniente') }}</title>

            <link rel="preconnect" href="https://fonts.bunny.net">
            <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

            @vite(['resources/css/app.css', 'resources/js/app.js'])
        </head>
        <body class="font-sans antialiased bg-gray-100 min-h-screen text-slate-800 flex flex-col">
            <!-- Navegación Superior del Muro para Visitantes (CT-03) -->
            <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-1.5 rounded-xl bg-white shadow-xs ring-1 ring-amber-400 shrink-0">
                            <img src="{{ asset('images/logo-tecnm.png') }}" alt="TecNM" class="h-9 w-auto" />
                        </div>
                        <div>
                            <span class="block text-[10px] font-extrabold uppercase tracking-widest text-tecnm-wine">
                                Campus Poniente
                            </span>
                            <h1 class="text-base font-extrabold text-slate-900 leading-tight">
                                Muro de Objetos Perdidos y Encontrados
                            </h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <a href="{{ route('reportes.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-tecnm-wine hover:bg-tecnm-wine-hover text-white font-bold text-xs transition-colors shadow-xs">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Encontré un objeto</span>
                        </a>
                        <a href="{{ route('login') }}" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-colors">
                            Iniciar sesión
                        </a>
                        <a href="{{ route('register') }}" class="hidden sm:inline-block px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-colors">
                            Crear cuenta
                        </a>
                    </div>
                </div>
            </header>

            <!-- Contenido Principal -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
                <!-- Mensajes Flash de Sesión (CA-26, CA-29, CA-31) -->
                @if (session('status'))
                    <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-sm font-semibold text-emerald-800 flex items-center gap-3 shadow-xs">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if (session('warning'))
                    <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-300 text-sm font-semibold text-amber-900 flex items-center justify-between shadow-xs">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ session('warning') }}</span>
                        </div>
                        <a href="{{ route('muro') }}" class="text-xs font-bold text-amber-900 underline hover:text-amber-950">
                            Volver al muro
                        </a>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-sm font-semibold text-red-800 flex items-center gap-3 shadow-xs">
                        <svg class="w-5 h-5 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                <!-- Listado de Publicaciones -->
                <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-black text-slate-900 tracking-tight">Publicaciones Recientes</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Objetos resguardados en Biblioteca o reportados en el campus.</p>
                    </div>
                    <div>
                        <a href="{{ route('reportes.create') }}" class="hidden sm:inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Encontré un objeto</span>
                        </a>
                    </div>
                </div>

                @if (!empty($errorConsulta))
                    <div class="bg-white rounded-3xl p-8 sm:p-10 border border-red-200 shadow-xs text-center max-w-lg mx-auto my-6" role="alert">
                        <div class="w-14 h-14 rounded-full bg-red-50 border border-red-100 flex items-center justify-center mx-auto mb-4 text-red-600">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-black text-slate-900 mb-1.5">Error al cargar las publicaciones</h3>
                        <p class="text-xs sm:text-sm text-slate-600 mb-6 leading-relaxed">
                            {{ $mensajeError ?? 'Ocurrió un problema técnico o de conexión al consultar las publicaciones. Por favor, intenta de nuevo.' }}
                        </p>
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                            <a href="{{ request()->fullUrl() }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span>Reintentar</span>
                            </a>
                            <a href="{{ route('reportes.create') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Encontré un objeto</span>
                            </a>
                        </div>
                    </div>
                @elseif (isset($publicaciones) && $publicaciones->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($publicaciones as $pub)
                            <x-muro-tarjeta :publicacion="$pub" />
                        @endforeach
                    </div>

                    {{-- Controles condicionales Anterior y Siguiente --}}
                    <x-muro-paginacion :paginator="$publicaciones" />
                @else
                    <div class="bg-white rounded-3xl p-10 border border-slate-200 text-center max-w-lg mx-auto">
                        <div class="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mb-1">No hay objetos disponibles</h3>
                        <p class="text-xs text-slate-500 mb-6">Actualmente no existen objetos pendientes de entrega o reportados en el campus.</p>
                        <a href="{{ route('reportes.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Encontré un objeto</span>
                        </a>
                    </div>
                @endif
            </main>
        </body>
    </html>
@endauth
