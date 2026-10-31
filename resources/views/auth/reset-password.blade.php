<x-guest-layout :raw="true">
    <!-- Contenedor principal de la tarjeta: Formulario y Panel Institucional -->
    <div class="w-full max-w-[1020px] rounded-3xl tecnm-card-shadow overflow-hidden flex flex-col lg:flex-row my-auto bg-white">

        <!-- COLUMNA 1 (IZQUIERDA): Formulario o Aviso de Enlace Inválido -->
        <div class="lg:w-[56%] bg-white p-6 sm:p-8 lg:p-9 flex flex-col justify-center order-1">

            @php
                $hasError = !$isValid;
                $errorText = session('error') ?: ($errors->first('email') ?: ($errorMessage ?: 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo'));
            @endphp

            @if ($hasError)
                <!-- ESTADO: Enlace inválido, alterado, expirado o consumido (Requerimientos 1, 2, 10, 15) -->
                <div class="mb-5 pb-3 border-b border-slate-100">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-red-700">
                        Enlace no disponible
                    </span>
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight leading-tight mt-0.5">
                        Enlace inválido o vencido
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        El enlace de recuperación utilizado no puede procesarse.
                    </p>
                </div>

                <!-- Tarjeta de advertencia con el mensaje exacto requerido -->
                <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-300 text-amber-900 flex items-start gap-3.5 shadow-sm" role="alert">
                    <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div class="text-xs font-semibold leading-relaxed">
                        <span class="block text-slate-800 font-bold mb-1">Aviso de seguridad</span>
                        <p class="mt-1 text-xs text-amber-900 font-semibold">{{ $errorText }}</p>
                    </div>
                </div>

                <!-- Botón visible para solicitar una nueva recuperación (Requerimientos 11 y 16) -->
                <div class="space-y-3 pt-2">
                    <a
                        href="{{ route('password.request') }}"
                        class="w-full inline-flex justify-center items-center gap-2 px-5 py-3 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover active:bg-[#430918] text-white font-bold text-sm tracking-wide shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tecnm-gold transition-all duration-150 cursor-pointer"
                    >
                        <svg class="w-4 h-4 text-tecnm-gold-light" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Solicitar una nueva recuperación</span>
                    </a>

                    <div class="text-center pt-2">
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-tecnm-wine transition-colors focus:outline-none focus:ring-2 focus:ring-tecnm-gold rounded p-1"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            <span>Regresar al inicio de sesión</span>
                        </a>
                    </div>
                </div>
            @else
                <!-- ESTADO: Token válido - Formulario de Restablecimiento (Requerimientos 3 a 18) -->
                <div class="mb-5 pb-3 border-b border-slate-100">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-tecnm-wine">
                        Restablecimiento de Contraseña
                    </span>
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight leading-tight mt-0.5">
                        Nueva contraseña
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Crea una nueva contraseña segura para tu cuenta institucional.
                    </p>
                </div>

                <!-- Mensaje de error general si ocurre -->
                @if (session('error'))
                    <div class="mb-4 p-3.5 rounded-xl bg-red-50 border border-red-200 text-xs font-semibold text-red-800 flex items-center gap-2.5" role="alert">
                        <svg class="w-4 h-4 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
                    @csrf

                    <!-- Token de recuperación validado en servidor -->
                    <input type="hidden" name="token" value="{{ $token ?? $request->route('token') }}">

                    <!-- Correo Electrónico Institucional (oculto / readonly) -->
                    <input type="hidden" name="email" value="{{ $email ?? $request->email }}">

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 flex items-center gap-2">
                        <svg class="w-4 h-4 text-tecnm-wine shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
                        </svg>
                        <span>Restableciendo contraseña para: <strong class="text-slate-800">{{ $email ?? $request->email }}</strong></span>
                    </div>

                    <!-- Cuadro con las reglas de contraseña visibles ANTES del envío (Requerimiento 7) -->
                    <div class="p-2.5 sm:p-3 rounded-xl bg-amber-50/70 border border-amber-200 text-amber-950">
                        <h4 class="text-xs font-bold text-amber-900 mb-1 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Reglas obligatorias para la nueva contraseña:</span>
                        </h4>
                        <ul class="space-y-0.5 text-[11px] text-amber-900 leading-snug pl-0.5">
                            <li class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-600 shrink-0"></span>
                                <span>Debe contener entre <strong>8 y 12 caracteres</strong>.</span>
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-600 shrink-0"></span>
                                <span>Debe incluir al menos <strong>una letra mayúscula</strong>.</span>
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-600 shrink-0"></span>
                                <span>Debe incluir al menos <strong>un número</strong>.</span>
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-600 shrink-0"></span>
                                <span>Debe incluir al menos <strong>un carácter especial</strong> (un espacio no cuenta como carácter especial).</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Campo 1: Nueva contraseña (Requerimientos 3, 4, 5, 6) -->
                    <div x-data="{ showPassword: false }" class="group">
                        <label for="password" class="block text-xs font-bold text-slate-700 mb-1">
                            Nueva contraseña <span class="text-red-600 font-bold" aria-hidden="true">*</span>
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
                                autofocus
                                autocomplete="new-password"
                                placeholder="Introduce tu nueva contraseña"
                                class="block w-full rounded-xl pl-10 pr-11 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition-all @error('password') border-red-500 bg-red-50/20 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 @else border-slate-200 bg-slate-50/60 hover:bg-white focus:bg-white focus:border-tecnm-wine focus:ring-4 focus:ring-tecnm-wine/10 @enderror"
                            />
                            <!-- Botón accesible para alternar visibilidad (Requerimiento 6) -->
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

                    <!-- Campo 2: Confirmar nueva contraseña (Requerimientos 3, 4, 5, 6, 10, 11) -->
                    <div x-data="{ showConfirmPassword: false }" class="group">
                        <label for="password_confirmation" class="block text-xs font-bold text-slate-700 mb-1">
                            Confirmar nueva contraseña <span class="text-red-600 font-bold" aria-hidden="true">*</span>
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
                                placeholder="Repite tu nueva contraseña"
                                class="block w-full rounded-xl pl-10 pr-11 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition-all @error('password_confirmation') border-red-500 bg-red-50/20 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 @else border-slate-200 bg-slate-50/60 hover:bg-white focus:bg-white focus:border-tecnm-wine focus:ring-4 focus:ring-tecnm-wine/10 @enderror"
                            />
                            <!-- Botón accesible para alternar visibilidad de confirmación -->
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

                    <!-- Botón Principal: Guardar contraseña -->
                    <div class="pt-2">
                        <button
                            type="submit"
                            class="w-full inline-flex justify-center items-center gap-2 px-5 py-3 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover active:bg-[#430918] text-white font-bold text-sm tracking-wide shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tecnm-gold transition-all duration-150 cursor-pointer"
                        >
                            <svg class="w-4 h-4 text-tecnm-gold-light" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Guardar nueva contraseña</span>
                        </button>
                    </div>

                    <!-- Opción para regresar al login -->
                    <div class="text-center pt-3 border-t border-slate-100">
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-tecnm-wine transition-colors focus:outline-none focus:ring-2 focus:ring-tecnm-gold rounded p-1"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            <span>Regresar al inicio de sesión</span>
                        </a>
                    </div>
                </form>
            @endif
        </div>

        <!-- COLUMNA 2 (DERECHA): Panel Institucional -->
        <div class="lg:w-[44%] bg-gradient-to-b from-[#691C32] via-[#520B1D] to-[#2A040D] text-white p-6 sm:p-7 lg:p-8 flex flex-col justify-between relative overflow-hidden order-2">
            <!-- Silueta tenue del campus al fondo -->
            <div class="absolute inset-x-0 bottom-0 h-44 opacity-20 pointer-events-none overflow-hidden" aria-hidden="true">
                <img src="{{ asset('images/campus-poniente.jpg') }}" alt="" class="w-full h-full object-cover object-bottom filter contrast-125" />
                <div class="absolute inset-0 bg-gradient-to-t from-[#2A040D] via-[#2A040D]/80 to-transparent"></div>
            </div>

            <!-- Contenido Superior y Central -->
            <div class="relative z-10 flex flex-col justify-between flex-1 py-1">
                <div>
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

                    <p class="text-xs text-rose-100/90 leading-relaxed mb-4">
                        Actualiza tu contraseña de acceso para recuperar el control seguro de tu cuenta institucional en el sistema.
                    </p>

                    <!-- Píldora de obligatoriedad -->
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#3D0A16]/90 border border-amber-400/40 text-xs text-amber-200 shadow-sm mb-2">
                        <svg class="w-4 h-4 text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd" />
                        </svg>
                        <span>Los campos marcados con <strong class="text-amber-300 font-bold">*</strong> son obligatorios</span>
                    </div>
                </div>

                <!-- Tarjeta informativa de seguridad del token (Uso único y protegido) -->
                <div class="my-auto py-4">
                    <div class="flex items-start gap-3.5 p-4 rounded-2xl bg-white/[0.06] border border-white/10 backdrop-blur-sm">
                        <div class="w-11 h-11 rounded-full bg-[#9E1B42] flex items-center justify-center text-white shrink-0 shadow-md mt-0.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-white tracking-wide">Uso único y protegido</h4>
                            <p class="text-[11px] text-rose-100/80 leading-normal mt-1">
                                Cada token se consume de forma atómica y queda invalidado de inmediato tras su primer uso exitoso.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enlace alternativo inferior y lema -->
            <div class="relative z-10 pt-6 mt-6 border-t border-rose-300/20">
                <div class="flex items-center justify-between text-xs mb-3">
                    <span class="text-rose-100/80">¿Recordaste tu contraseña?</span>
                    <a
                        href="{{ route('login') }}"
                        class="font-bold text-white hover:text-amber-300 underline underline-offset-2 flex items-center gap-1 transition-colors"
                    >
                        <span>Iniciar sesión</span>
                        <span aria-hidden="true">&rsaquo;</span>
                    </a>
                </div>
                <div class="flex items-center justify-between pt-1">
                    <div class="w-16 h-3 border-b-2 border-r-2 border-amber-400/70 rounded-br-lg"></div>
                    <p class="text-xs text-rose-100/90 italic tracking-wide text-right font-serif">
                        Juntos construimos un mejor mañana
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
