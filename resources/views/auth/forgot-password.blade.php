<x-guest-layout :raw="true">
    <!-- Contenedor principal de la tarjeta: Formulario y Panel Institucional -->
    <div class="w-full max-w-[1020px] rounded-3xl tecnm-card-shadow overflow-hidden flex flex-col lg:flex-row my-auto bg-white">

        <!-- COLUMNA 1 (IZQUIERDA): Formulario de Solicitud de Recuperación -->
        <div class="lg:w-[56%] bg-white p-6 sm:p-8 lg:p-9 flex flex-col justify-center order-1">

            <!-- Encabezado del Formulario -->
            <div class="mb-5 pb-3 border-b border-slate-100">
                <span class="text-[11px] font-bold uppercase tracking-wider text-tecnm-wine">
                    Recuperación de Contraseña
                </span>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight leading-tight mt-0.5">
                    Recuperar contraseña
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Ingresa tu correo electrónico institucional para solicitar el enlace de recuperación.
                </p>
            </div>

            <!-- Aviso de Estado / Éxito (Respuesta genérica anti-enumeración) -->
            @if (session('status'))
                <div class="mb-4 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-800 flex items-center gap-2.5" role="alert">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <!-- Aviso de Error Flash General -->
            @if (session('error'))
                <div class="mb-4 p-3.5 rounded-xl bg-red-50 border border-red-200 text-xs font-semibold text-red-800 flex items-center gap-2.5" role="alert">
                    <svg class="w-4 h-4 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- Formulario de Solicitud con validación cliente (Alpine.js) y servidor -->
            <form method="POST" action="{{ route('password.email') }}" class="space-y-4"
                  x-data="{
                      submitting: false,
                      email: '{{ old('email', '') }}',
                      emailError: '',
                      validateEmail() {
                          this.emailError = '';
                          const raw = this.email;
                          const trimmed = raw ? raw.trim() : '';

                          // 1. Campo vacío o solo espacios
                          if (trimmed === '') {
                              this.emailError = 'Ingresa tu correo electrónico institucional';
                              return false;
                          }

                          // 2. Normalización a minúsculas
                          const normalized = trimmed.toLowerCase();

                          // 3. Formato y dominio exacto @merida.tecnm.mx
                          const regex = /^[a-zA-Z0-9._%+-]+@merida\.tecnm\.mx$/i;
                          if (!regex.test(normalized)) {
                              this.emailError = 'Ingresa un correo institucional válido con dominio @merida.tecnm.mx';
                              return false;
                          }

                          return true;
                      },
                      handleSubmit(e) {
                          if (!this.validateEmail()) {
                              e.preventDefault();
                              return false;
                          }
                          this.submitting = true;
                          return true;
                      }
                  }"
                  @submit="handleSubmit($event)"
                  novalidate>
                @csrf

                <!-- Campo Único: Correo electrónico institucional -->
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
                            x-model="email"
                            @input="if (emailError) validateEmail()"
                            @blur="validateEmail()"
                            value="{{ old('email', '') }}"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="usuario@merida.tecnm.mx"
                            class="block w-full rounded-xl pl-10 pr-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition-all @error('email') border-red-500 bg-red-50/20 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 @else border-slate-200 bg-slate-50/60 hover:bg-white focus:bg-white focus:border-tecnm-wine focus:ring-4 focus:ring-tecnm-wine/10 @enderror"
                        />
                    </div>
                    <!-- Error desde el cliente (Alpine.js) -->
                    <p x-show="emailError" x-text="emailError" class="mt-1 text-xs text-red-600 font-medium" style="display: none;"></p>
                    <!-- Error desde el servidor (Laravel) -->
                    @error('email')
                        <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Botón Principal: Enviar enlace de recuperación -->
                <div class="pt-2">
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="w-full inline-flex justify-center items-center gap-2 px-5 py-3 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover active:bg-[#430918] disabled:opacity-60 disabled:cursor-not-allowed text-white font-bold text-sm tracking-wide shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tecnm-gold transition-all duration-150 cursor-pointer"
                    >
                        <svg x-show="submitting" x-cloak class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg x-show="!submitting" class="w-4 h-4 text-tecnm-gold-light" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span x-show="!submitting">Enviar enlace de recuperación</span>
                        <span x-show="submitting" x-cloak>Enviando enlace...</span>
                    </button>
                </div>

                <!-- Opción para regresar al inicio de sesión -->
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
                    Solicita el restablecimiento de tu contraseña institucional. Te enviaremos instrucciones detalladas a tu cuenta oficial para recuperar el acceso seguro a la plataforma.
                </p>

                <!-- Píldora de obligatoriedad -->
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#3D0A16]/90 border border-amber-400/40 text-xs text-amber-200 shadow-sm mb-4">
                    <svg class="w-4 h-4 text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd" />
                    </svg>
                    <span>Los campos marcados con <strong class="text-amber-300 font-bold">*</strong> son obligatorios</span>
                </div>

                <!-- Tarjetas informativas de dominio y seguridad institucional -->
                <div class="space-y-3">
                    <!-- Tarjeta 1: Dominio requerido -->
                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl bg-white/[0.06] border border-white/10 backdrop-blur-sm">
                        <div class="w-11 h-11 rounded-full bg-[#9E1B42] flex items-center justify-center text-white shrink-0 shadow-md">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-white tracking-wide">Dominio institucional requerido</h4>
                            <p class="text-[11px] text-rose-100/80 leading-normal mt-0.5">
                                Solo se admiten correos pertenecientes al dominio oficial:
                            </p>
                            <p class="text-xs font-bold text-amber-300 tracking-wide mt-0.5">
                                @merida.tecnm.mx
                            </p>
                        </div>
                    </div>

                    <!-- Tarjeta 2: Privacidad y anti-enumeración -->
                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl bg-white/[0.06] border border-white/10 backdrop-blur-sm">
                        <div class="w-11 h-11 rounded-full bg-[#9E1B42] flex items-center justify-center text-white shrink-0 shadow-md">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-white tracking-wide">Privacidad y seguridad</h4>
                            <p class="text-[11px] text-rose-100/80 leading-normal mt-0.5">
                                Si el correo se encuentra registrado en el sistema, recibirás un enlace de un solo uso con vigencia limitada.
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
