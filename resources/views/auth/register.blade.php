<x-guest-layout :raw="true">
    <!-- Contenedor de la tarjeta con sombra profunda y suave, SIN bordes sólidos ni líneas superiores -->
    <div class="w-full max-w-[1060px] rounded-3xl tecnm-card-shadow overflow-hidden flex flex-col lg:flex-row my-auto bg-white">

        <!-- COLUMNA IZQUIERDA: Panel Institucional (Diseño Fiel a la Identidad Oficial) -->
        <div class="lg:w-[44%] bg-gradient-to-b from-[#691C32] via-[#520B1D] to-[#2A040D] text-white p-6 sm:p-7 lg:p-8 flex flex-col justify-between relative overflow-hidden">
            
            <!-- Silueta tenue del campus al fondo inferior del panel -->
            <div class="absolute inset-x-0 bottom-0 h-44 opacity-20 pointer-events-none overflow-hidden" aria-hidden="true">
                <img src="{{ asset('images/campus-poniente.jpg') }}" alt="" class="w-full h-full object-cover object-bottom filter contrast-125" />
                <div class="absolute inset-0 bg-gradient-to-t from-[#2A040D] via-[#2A040D]/80 to-transparent"></div>
            </div>

            <!-- Contenido Superior -->
            <div class="relative z-10">
                <!-- Escudo Oficial y Títulos -->
                <div class="flex items-center gap-3.5 mb-4">
                    <div class="p-2.5 rounded-2xl bg-white shadow-xl shadow-black/30 ring-2 ring-amber-400/80 shrink-0">
                        <img
                            src="{{ asset('images/logo-tecnm.png') }}"
                            alt="Escudo Instituto Tecnológico de Mérida"
                            class="h-14 lg:h-16 w-auto"
                        />
                    </div>
                    <div>
                        <span class="block text-[11px] font-extrabold uppercase tracking-[0.16em] text-amber-400">
                            TECNM &bull; MÉRIDA
                        </span>
                        <h1 class="text-2xl font-black leading-tight text-white tracking-tight mt-0.5">
                            Campus Poniente
                        </h1>
                        <p class="text-xs font-medium text-rose-100/90 mt-0.5">
                            Sistema Web de Objetos Perdidos
                        </p>
                    </div>
                </div>

                <!-- Párrafo introductorio -->
                <p class="text-xs text-rose-100/90 leading-relaxed mb-4">
                    Regístrate con tu correo institucional para reportar hallazgos y localizar pertenencias resguardadas en las instalaciones.
                </p>

                <!-- Píldora de obligatoriedad -->
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#3D0A16]/90 border border-amber-400/40 text-xs text-amber-200 shadow-sm mb-4">
                    <svg class="w-4 h-4 text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd" />
                    </svg>
                    <span>Los campos marcados con <strong class="text-amber-300 font-bold">*</strong> son obligatorios</span>
                </div>

                <!-- Bloques informativos (Correo y Contraseña) -->
                <div class="space-y-3">
                    <!-- Tarjeta 1: Correo institucional obligatorio -->
                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl bg-white/[0.06] border border-white/10 backdrop-blur-sm">
                        <div class="w-11 h-11 rounded-full bg-[#9E1B42] flex items-center justify-center text-white shrink-0 shadow-md">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-white tracking-wide">Correo institucional obligatorio</h4>
                            <p class="text-[11px] text-rose-100/80 leading-normal mt-0.5">
                                Debe utilizarse el dominio oficial:
                            </p>
                            <p class="text-xs font-bold text-white tracking-wide mt-0.5">
                                @merida.tecnm.mx
                            </p>
                        </div>
                    </div>

                    <!-- Tarjeta 2: Requisitos de la contraseña -->
                    <div class="flex items-start gap-3.5 p-3.5 rounded-2xl bg-white/[0.06] border border-white/10 backdrop-blur-sm">
                        <div class="w-11 h-11 rounded-full bg-[#9E1B42] flex items-center justify-center text-white shrink-0 shadow-md mt-0.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-white tracking-wide mb-1.5">Requisitos de la contraseña</h4>
                            <ul class="space-y-1 text-[11px] text-rose-100/90 leading-tight">
                                <li class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-white shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Debe contener entre <strong class="text-white font-bold">8 y 12 caracteres</strong>.</span>
                                </li>
                                <li class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-white shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Debe incluir al menos <strong class="text-white font-bold">una letra mayúscula</strong>.</span>
                                </li>
                                <li class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-white shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Debe incluir al menos <strong class="text-white font-bold">un número</strong>.</span>
                                </li>
                                <li class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-white shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Debe incluir al menos <strong class="text-white font-bold">un carácter especial</strong>.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pie del Panel Izquierdo: Acceso directo y lema -->
            <div class="relative z-10 mt-6 pt-3 border-t border-white/10">
                <div class="flex items-center justify-between text-xs text-rose-100/90 mb-3">
                    <div class="flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded-full border border-rose-200/70 flex items-center justify-center text-[10px] font-bold text-rose-100">?</span>
                        <span>¿Ya tienes cuenta institucional?</span>
                    </div>
                    <a
                        href="{{ route('login') }}"
                        class="font-bold text-white hover:text-amber-300 underline underline-offset-2 flex items-center gap-1 transition-colors"
                    >
                        <span>Inicia sesión aquí</span>
                        <span aria-hidden="true">&rsaquo;</span>
                    </a>
                </div>

                <!-- Línea decorativa y lema inferior -->
                <div class="flex items-center justify-between pt-1">
                    <div class="w-16 h-3 border-b-2 border-l-2 border-amber-400/70 rounded-bl-lg"></div>
                    <p class="text-xs text-rose-100/90 italic tracking-wide text-right font-serif">
                        Juntos construimos un mejor mañana
                    </p>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: Formulario Estilizado con Shadow, Limpio y Sin Línea Superior -->
        <div class="lg:w-[56%] bg-white p-6 sm:p-8 lg:p-9 flex flex-col justify-center">

            <!-- Encabezado del Formulario -->
            <div class="mb-4 pb-3 border-b border-slate-100">
                <span class="text-[11px] font-bold uppercase tracking-wider text-tecnm-wine">
                    Registro Institucional
                </span>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight leading-tight mt-0.5">
                    Crear cuenta institucional
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Ingresa tus datos personales y credenciales de acceso institucional.
                </p>
            </div>

            @if (session('status'))
                <div class="mb-3.5 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-800 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-3.5 p-3 rounded-xl bg-red-50 border border-red-200 text-xs font-semibold text-red-800 flex items-center gap-2">
                    <svg class="w-4 h-4 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- Formulario de Captura -->
            <form method="POST" action="{{ route('register') }}" class="space-y-3.5" novalidate>
                @csrf

                <!-- Campo 1: Nombre completo -->
                <div class="group">
                    <label for="name" class="block text-xs font-bold text-slate-700 mb-1">
                        Nombre completo <span class="text-red-600 font-bold" aria-hidden="true">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none @error('name') text-red-400 @else text-slate-400 group-focus-within:text-tecnm-wine @enderror transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </span>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            required
                            autofocus
                            autocomplete="name"
                            placeholder="Ej. Juan Carlos Pérez Gómez"
                            class="block w-full rounded-xl pl-10 pr-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition-all @error('name') border-red-500 bg-red-50/20 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 @else border-slate-200 bg-slate-50/60 hover:bg-white focus:bg-white focus:border-tecnm-wine focus:ring-4 focus:ring-tecnm-wine/10 @enderror"
                        />
                    </div>
                    @error('name')
                        <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Grid de 2 columnas: Correo y Teléfono -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <!-- Correo electrónico institucional -->
                    <div class="group">
                        <label for="email" class="block text-xs font-bold text-slate-700 mb-1">
                            Correo electrónico institucional <span class="text-red-600 font-bold" aria-hidden="true">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none @error('email') text-red-400 @else text-slate-400 group-focus-within:text-tecnm-wine @enderror transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
                                autocomplete="username"
                                placeholder="usuario@merida.tecnm.mx"
                                class="block w-full rounded-xl pl-10 pr-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition-all @error('email') border-red-500 bg-red-50/20 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 @else border-slate-200 bg-slate-50/60 hover:bg-white focus:bg-white focus:border-tecnm-wine focus:ring-4 focus:ring-tecnm-wine/10 @enderror"
                            />
                        </div>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Debe utilizar el dominio: <strong class="text-tecnm-wine font-semibold">@merida.tecnm.mx</strong>
                        </p>
                        @error('email')
                            <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Teléfono -->
                    <div class="group">
                        <label for="phone" class="block text-xs font-bold text-slate-700 mb-1">
                            Teléfono <span class="text-red-600 font-bold" aria-hidden="true">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none @error('phone') text-red-400 @else text-slate-400 group-focus-within:text-tecnm-wine @enderror transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </span>
                            <input
                                id="phone"
                                name="phone"
                                type="tel"
                                inputmode="numeric"
                                maxlength="10"
                                pattern="[0-9]{10}"
                                value="{{ old('phone') }}"
                                required
                                autocomplete="tel"
                                placeholder="Ej. 9991234567"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                                onkeypress="return event.charCode >= 48 && event.charCode <= 57"
                                class="block w-full rounded-xl pl-10 pr-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition-all @error('phone') border-red-500 bg-red-50/20 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 @else border-slate-200 bg-slate-50/60 hover:bg-white focus:bg-white focus:border-tecnm-wine focus:ring-4 focus:ring-tecnm-wine/10 @enderror"
                            />
                        </div>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Número a 10 dígitos para contacto.
                        </p>
                        @error('phone')
                            <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Grid de 2 columnas: Contraseña y Confirmación -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <!-- Contraseña -->
                    <div x-data="{ showPassword: false }" class="group">
                        <label for="password" class="block text-xs font-bold text-slate-700 mb-1">
                            Contraseña <span class="text-red-600 font-bold" aria-hidden="true">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none @error('password') text-red-400 @else text-slate-400 group-focus-within:text-tecnm-wine @enderror transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </span>
                            <input
                                id="password"
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                type="password"
                                required
                                autocomplete="new-password"
                                placeholder="Introduce tu contraseña"
                                class="block w-full rounded-xl pl-10 pr-11 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition-all @error('password') border-red-500 bg-red-50/20 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 @else border-slate-200 bg-slate-50/60 hover:bg-white focus:bg-white focus:border-tecnm-wine focus:ring-4 focus:ring-tecnm-wine/10 @enderror"
                            />
                            <!-- Botón independiente para alternar visibilidad -->
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                :title="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                class="absolute inset-y-1 right-1 px-2.5 flex items-center justify-center rounded-lg text-slate-400 hover:text-tecnm-wine hover:bg-tecnm-wine/10 focus:outline-none transition-colors"
                            >
                                <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                                <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        @if ($errors->has('password'))
                            <ul class="mt-1.5 space-y-1 text-xs text-red-600 font-medium">
                                @foreach ($errors->get('password') as $error)
                                    <li class="flex items-start gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-red-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                        <span>{{ $error }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <!-- Confirmación de contraseña -->
                    <div x-data="{ showConfirmPassword: false }" class="group">
                        <label for="password_confirmation" class="block text-xs font-bold text-slate-700 mb-1">
                            Confirmación de contraseña <span class="text-red-600 font-bold" aria-hidden="true">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none @error('password_confirmation') text-red-400 @else text-slate-400 group-focus-within:text-tecnm-wine @enderror transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </span>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                :type="showConfirmPassword ? 'text' : 'password'"
                                type="password"
                                required
                                autocomplete="new-password"
                                placeholder="Repite tu contraseña"
                                class="block w-full rounded-xl pl-10 pr-11 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition-all @error('password_confirmation') border-red-500 bg-red-50/20 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 @else border-slate-200 bg-slate-50/60 hover:bg-white focus:bg-white focus:border-tecnm-wine focus:ring-4 focus:ring-tecnm-wine/10 @enderror"
                            />
                            <!-- Botón independiente para alternar visibilidad -->
                            <button
                                type="button"
                                @click="showConfirmPassword = !showConfirmPassword"
                                :aria-label="showConfirmPassword ? 'Ocultar confirmación de contraseña' : 'Mostrar confirmación de contraseña'"
                                :title="showConfirmPassword ? 'Ocultar confirmación de contraseña' : 'Mostrar confirmación de contraseña'"
                                class="absolute inset-y-1 right-1 px-2.5 flex items-center justify-center rounded-lg text-slate-400 hover:text-tecnm-wine hover:bg-tecnm-wine/10 focus:outline-none transition-colors"
                            >
                                <svg x-show="showConfirmPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                                <svg x-show="!showConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        @error('password_confirmation')
                            <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Botón Principal: Crear cuenta -->
                <div class="pt-2">
                    <button
                        type="submit"
                        class="w-full inline-flex justify-center items-center gap-2 px-5 py-3 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover active:bg-[#430918] text-white font-bold text-sm tracking-wide shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tecnm-gold transition-all duration-150 cursor-pointer"
                    >
                        <svg class="w-4 h-4 text-tecnm-gold-light" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        <span>Crear cuenta</span>
                    </button>
                </div>

                <!-- Enlace alternativo para pantallas pequeñas -->
                <div class="text-center pt-2 border-t border-slate-100 lg:hidden">
                    <p class="text-xs text-slate-600">
                        ¿Ya tienes cuenta institucional?
                        <a href="{{ route('login') }}" class="font-bold text-tecnm-wine hover:underline ml-1">
                            Inicia sesión aquí
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
