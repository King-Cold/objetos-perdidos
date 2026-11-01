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
                        {{ $publicacion->nombre ?? $publicacion->titulo }}
                    </h1>

                    <p class="text-xs text-slate-500 mb-6">
                        Publicado el {{ $publicacion->created_at->format('d/m/Y H:i') }} &bull; Lugar de resguardo: <strong>{{ $publicacion->lugar_hallazgo ?? 'Biblioteca Campus Poniente' }}</strong>
                    </p>

                    <div class="prose prose-slate text-sm text-slate-700 leading-relaxed mb-8">
                        {{ $publicacion->descripcion }}
                    </div>

                    {{-- Fotografías de la publicación si existen --}}
                    @if ($publicacion->fotografias && $publicacion->fotografias->isNotEmpty())
                        <div class="mb-8">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
                                Fotografías del objeto
                            </h3>
                            <div class="flex items-center gap-3 overflow-x-auto py-2">
                                @foreach ($publicacion->fotografias as $foto)
                                    <div class="relative shrink-0">
                                        <img
                                            src="{{ $foto->url }}"
                                            alt="{{ $foto->nombre_original ?? 'Foto del objeto' }}"
                                            class="w-24 h-24 sm:w-32 sm:h-32 rounded-xl object-cover border border-slate-200 shadow-xs shrink-0"
                                        />
                                        @if ($loop->first)
                                            <span class="absolute bottom-1.5 left-1.5 px-2 py-0.5 rounded text-[10px] font-bold bg-[#722F37] text-white shadow-xs">
                                                Principal
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Sección de Resguardo en Biblioteca o Contacto Vigente del Autor (CA-16, CA-18, CA-20) --}}
                    @if ($publicacion->isBajoResguardoBiblioteca())
                        <!-- Resguardo confirmado en Biblioteca (CA-20) -->
                        <div class="mb-8 p-5 rounded-2xl bg-amber-50/80 border border-amber-200">
                            <div class="flex items-start gap-3.5">
                                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <div class="space-y-1.5 text-xs text-amber-900 leading-relaxed">
                                    <h3 class="text-sm font-extrabold text-amber-950">
                                        Objeto bajo resguardo confirmado de Biblioteca
                                    </h3>
                                    <p>
                                        <strong>Ubicación de resguardo:</strong> {{ $publicacion->lugar_hallazgo ?? 'Biblioteca Campus Poniente' }}
                                    </p>
                                    <p>
                                        <strong>Horario de atención:</strong> Lunes a Viernes de 7:00 a 19:00 hrs.
                                    </p>
                                    <p class="text-amber-800 mt-2">
                                        <strong>Instrucciones de recuperación:</strong> Preséntate en el mostrador principal de Biblioteca portando tu credencial institucional vigente y acredita la propiedad del objeto con características específicas ante el personal encargado.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @elseif ($publicacion->user?->phone)
                        <!-- Contacto vigente con el Autor del Reporte (CA-16) -->
                        <div class="mb-8 p-5 rounded-2xl bg-slate-50 border border-slate-200">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-tecnm-wine/10 text-tecnm-wine flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </div>
                                <div>
                                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Contacto directo con el autor</span>
                                    <span class="text-base font-extrabold text-slate-900" id="contacto-telefono-vigente">
                                        Teléfono: {{ $publicacion->user->phone }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

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
                        {{ $publicacion->nombre ?? $publicacion->titulo }}
                    </h1>

                    <p class="text-xs text-slate-500 mb-6">
                        Publicado el {{ $publicacion->created_at->format('d/m/Y H:i') }} &bull; Lugar de resguardo: <strong>{{ $publicacion->lugar_hallazgo ?? 'Biblioteca Campus Poniente' }}</strong>
                    </p>

                    <div class="prose prose-slate text-sm text-slate-700 leading-relaxed mb-8">
                        {{ $publicacion->descripcion }}
                    </div>

                    @if ($publicacion->isBajoResguardoBiblioteca())
                        <!-- Resguardo confirmado en Biblioteca para visitantes (CA-20) -->
                        <div class="mb-8 p-5 rounded-2xl bg-amber-50/80 border border-amber-200">
                            <div class="flex items-start gap-3.5">
                                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <div class="space-y-1.5 text-xs text-amber-900 leading-relaxed">
                                    <h3 class="text-sm font-extrabold text-amber-950">
                                        Objeto bajo resguardo confirmado de Biblioteca
                                    </h3>
                                    <p>
                                        <strong>Ubicación de resguardo:</strong> {{ $publicacion->lugar_hallazgo ?? 'Biblioteca Campus Poniente' }}
                                    </p>
                                    <p>
                                        <strong>Horario de atención:</strong> Lunes a Viernes de 7:00 a 19:00 hrs.
                                    </p>
                                    <p class="text-amber-800 mt-2">
                                        <strong>Instrucciones de recuperación:</strong> Preséntate en el mostrador principal de Biblioteca portando tu credencial institucional vigente y describe detalles específicos del objeto para acreditar tu legítima propiedad.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- Contacto protegido para visitantes sin sesión (CA-19) -->
                        <div class="mb-8 p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between text-xs text-slate-600">
                            <div class="flex items-center gap-2.5">
                                <svg class="w-5 h-5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <span>Información de contacto protegida para la comunidad del TecNM. Inicia sesión para contactar al autor.</span>
                            </div>
                        </div>
                    @endif

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
