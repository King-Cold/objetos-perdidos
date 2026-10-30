<x-guest-layout :raw="true">
    <!-- Contenedor de la tarjeta invertida: Formulario a la izquierda y Panel Institucional a la derecha (CA-33, CT-03, CT-04) -->
    <div class="w-full max-w-[1020px] rounded-3xl tecnm-card-shadow overflow-hidden flex flex-col lg:flex-row my-auto bg-white">

        <!-- COLUMNA 1 (IZQUIERDA): Formulario de Inicio de Sesión -->
        <div class="lg:w-[56%] bg-white p-6 sm:p-8 lg:p-9 flex flex-col justify-center order-1">

            <!-- Encabezado del Formulario -->
            <div class="mb-5 pb-3 border-b border-slate-100">
                <span class="text-[11px] font-bold uppercase tracking-wider text-tecnm-wine">
                    Autenticación Institucional
                </span>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight leading-tight mt-0.5">
                    Iniciar sesión
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Ingresa tus credenciales oficiales para acceder al sistema.
                </p>
            </div>

            <!-- Avisos de Estado y Errores Flash (CA-26, CA-31) -->
            @if (session('status'))
                <div class="mb-4 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-800 flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 p-3.5 rounded-xl bg-red-50 border border-red-200 text-xs font-semibold text-red-800 flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if (session('warning'))
                <div class="mb-4 p-3.5 rounded-xl bg-amber-50 border border-amber-300 text-xs font-semibold text-amber-900 flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('warning') }}</span>
                </div>
            @endif

            <!-- Formulario de Acceso (CA-01, CA-02, CA-04, CA-06, CT-05) -->
            <form method="POST" action="{{ route('login') }}" class="space-y-4"
                  x-data="{
                      submitting: false,
                      emailError: '',
                      passwordError: '',
                      validateForm(e) {
                          this.emailError = '';
                          this.passwordError = '';
                          const emailEl = document.getElementById('email');
                          const passwordEl = document.getElementById('password');
                          const emailVal = emailEl ? emailEl.value.trim() : '';
                          const passwordVal = passwordEl ? passwordEl.value.trim() : '';
                          let valid = true;

                          if (emailVal === '') {
                              this.emailError = 'El correo electrónico es obligatorio.';
                              valid = false;
                          }
                          if (passwordVal === '') {
                              this.passwordError = 'La contraseña es obligatoria.';
                              valid = false;
                          }

                          if (!valid) {
                              e.preventDefault();
                              return false;
                          }
                          this.submitting = true;
                          return true;
                      }
                  }"
                  @submit="validateForm($event)"
                  novalidate>
                @csrf

                <!-- Campo 1: Correo electrónico (CA-01, CA-02, CA-03, CA-06) -->
                <div class="group">
                    <label for="email" class="block text-xs font-bold text-slate-700 mb-1">
                        Correo electrónico <span class="text-red-600 font-bold" aria-hidden="true">*</span>
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
                            value="{{ old('email', '') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="usuario@merida.tecnm.mx"
                            class="block w-full rounded-xl pl-10 pr-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition-all @error('email') border-red-500 bg-red-50/20 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 @else border-slate-200 bg-slate-50/60 hover:bg-white focus:bg-white focus:border-tecnm-wine focus:ring-4 focus:ring-tecnm-wine/10 @enderror"
                        />
                    </div>
                    <!-- Error desde el cliente (CA-02) -->
                    <p x-show="emailError" x-text="emailError" class="mt-1 text-xs text-red-600 font-medium" style="display: none;"></p>
                    <!-- Error desde el servidor (CA-02, CA-05) -->
                    @error('email')
                        <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Campo 2: Contraseña con toggle accesible (CA-01, CA-02, CA-03, CA-04, CA-06) -->
                <div x-data="{ showPassword: false }" class="group">
                    <div class="flex items-center justify-between mb-1">
                        <label for="password" class="block text-xs font-bold text-slate-700">
                            Contraseña <span class="text-red-600 font-bold" aria-hidden="true">*</span>
                        </label>
                        <!-- Enlace a Recuperar contraseña (CA-17) -->
                        @if (Route::has('password.request'))
                            <a
                                href="{{ route('password.request') }}"
                                class="text-xs font-medium text-tecnm-wine hover:text-tecnm-wine-hover hover:underline transition-colors focus:outline-none focus:ring-2 focus:ring-tecnm-gold rounded"
                            >
                                ¿Olvidaste tu contraseña?
                            </a>
                        @endif
                    </div>
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
                            autocomplete="current-password"
                            placeholder="Introduce tu contraseña"
                            class="block w-full rounded-xl pl-10 pr-11 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition-all @error('password') border-red-500 bg-red-50/20 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 @else border-slate-200 bg-slate-50/60 hover:bg-white focus:bg-white focus:border-tecnm-wine focus:ring-4 focus:ring-tecnm-wine/10 @enderror"
                        />
                        <!-- Botón para alternar visibilidad de contraseña (CA-04: no envía formulario, accesible con teclado) -->
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            @keydown.enter.prevent="showPassword = !showPassword"
                            @keydown.space.prevent="showPassword = !showPassword"
                            :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            :title="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            class="absolute inset-y-1 right-1 px-2.5 flex items-center justify-center rounded-lg text-slate-400 hover:text-tecnm-wine hover:bg-tecnm-wine/10 focus:outline-none focus:ring-2 focus:ring-tecnm-wine transition-colors"
                        >
                            <!-- Ícono de ojo tachado (ocultar) -->
                            <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                            </svg>
                            <!-- Ícono de ojo (mostrar) -->
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    <!-- Error desde el cliente (CA-02) -->
                    <p x-show="passwordError" x-text="passwordError" class="mt-1 text-xs text-red-600 font-medium" style="display: none;"></p>
                    <!-- Error desde el servidor (CA-02, CA-05) -->
                    @error('password')
                        <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Botón Principal: Iniciar sesión con protección de doble envío (CT-05, CT-14) -->
                <div class="pt-2">
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="w-full inline-flex justify-center items-center gap-2 px-5 py-3 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover active:bg-[#430918] disabled:opacity-60 disabled:cursor-not-allowed text-white font-bold text-sm tracking-wide shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tecnm-gold transition-all duration-150 cursor-pointer"
                    >
                        <!-- Indicador de carga cuando submitting es true -->
                        <svg x-show="submitting" x-cloak class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg x-show="!submitting" class="w-4 h-4 text-tecnm-gold-light" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        <span x-show="!submitting">Iniciar sesión</span>
                        <span x-show="submitting" x-cloak>Iniciando sesión...</span>
                    </button>
                </div>

                <!-- Enlace alternativo para pantallas pequeñas (CA-17, CA-33) -->
                <div class="text-center pt-3 border-t border-slate-100 lg:hidden">
                    <p class="text-xs text-slate-600">
                        ¿No tienes cuenta institucional?
                        <a href="{{ route('register') }}" class="font-bold text-tecnm-wine hover:underline ml-1">
                            Crear cuenta
                        </a>
                    </p>
                </div>
            </form>
        </div>

        <!-- COLUMNA 2 (DERECHA): Panel Institucional -->
        <div class="lg:w-[44%] bg-gradient-to-b from-[#691C32] via-[#520B1D] to-[#2A040D] text-white p-6 sm:p-7 lg:p-8 flex flex-col justify-between relative overflow-hidden order-2">
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
                    Inicia sesión con tu cuenta institucional para reportar objetos, reclamar pertenencias y acceder a las funciones autorizadas de tu rol.
                </p>

                <!-- Píldora de obligatoriedad (CA-01) -->
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#3D0A16]/90 border border-amber-400/40 text-xs text-amber-200 shadow-sm mb-4">
                    <svg class="w-4 h-4 text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd" />
                    </svg>
                    <span>Los campos marcados con <strong class="text-amber-300 font-bold">*</strong> son obligatorios</span>
                </div>

                <!-- Tarjeta informativa de seguridad institucional -->
                <div class="space-y-3">
                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl bg-white/[0.06] border border-white/10 backdrop-blur-sm">
                        <div class="w-11 h-11 rounded-full bg-[#9E1B42] flex items-center justify-center text-white shrink-0 shadow-md">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-white tracking-wide">Acceso seguro y controlado</h4>
                            <p class="text-[11px] text-rose-100/80 leading-normal mt-0.5">
                                La sesión vence tras 10 minutos de inactividad para resguardar tus datos y reportes.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enlace a registro y lema inferior (CA-17) -->
            <div class="relative z-10 pt-6 mt-6 border-t border-rose-300/20">
                <div class="flex items-center justify-between text-xs mb-3">
                    <span class="text-rose-100/80">¿Aún no tienes cuenta?</span>
                    <a
                        href="{{ route('register') }}"
                        class="font-bold text-white hover:text-amber-300 underline underline-offset-2 flex items-center gap-1 transition-colors"
                    >
                        <span>Crear cuenta</span>
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
