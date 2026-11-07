<nav x-data="{ open: false }" class="bg-white border-b border-slate-200 shadow-xs sticky top-0 z-40">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo Oficial TecNM -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ Auth::user()->isAdmin() ? route('admin.dashboard') : route('muro') }}" class="flex items-center gap-2.5">
                        <div class="p-1.5 rounded-xl bg-white shadow-xs ring-1 ring-amber-400">
                            <img src="{{ asset('images/logo-tecnm.png') }}" alt="TecNM Mérida" class="h-8 w-auto" />
                        </div>
                        <div class="hidden md:block">
                            <span class="block text-[10px] font-extrabold uppercase tracking-widest text-tecnm-wine">
                                Campus Poniente
                            </span>
                            <span class="text-xs font-black text-slate-800 tracking-tight">
                                Objetos Perdidos
                            </span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links segun Rol (CA-16) -->
                <div class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex">
                    @if (Auth::user()->isAdmin())
                        <!-- Opciones Exclusivas de Administrador / Bibliotecario (CA-16) -->
                        <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                            {{ __('Panel Administrativo') }}
                        </x-nav-link>

                        <x-nav-link :href="route('admin.moderacion.index')" :active="request()->routeIs('admin.moderacion.*') || request()->routeIs('admin.publicaciones')">
                            {{ __('Gestión de Objetos') }}
                        </x-nav-link>

                        <x-nav-link :href="route('admin.devoluciones.index')" :active="request()->routeIs('admin.devoluciones.*')">
                            {{ __('Entregas y Devoluciones') }}
                        </x-nav-link>

                        <x-nav-link :href="route('avisos.index')" :active="request()->routeIs('avisos.*')">
                            {{ __('Avisos') }}
                            @if (Auth::user()->avisos_no_leidos_count > 0)
                                <span class="ms-1.5 px-1.5 py-0.5 text-[10px] font-black rounded-full bg-rose-600 text-white">
                                    {{ Auth::user()->avisos_no_leidos_count }}
                                </span>
                            @endif
                        </x-nav-link>

                        <x-nav-link :href="route('muro')" :active="request()->routeIs('muro')">
                            {{ __('Muro Público') }}
                        </x-nav-link>
                    @else
                        <!-- Opciones Exclusivas de Usuario General (CA-16) -->
                        <x-nav-link :href="route('muro')" :active="request()->routeIs('muro')">
                            {{ __('Muro de Objetos') }}
                        </x-nav-link>

                        <x-nav-link :href="route('reportes.mis-reportes')" :active="request()->routeIs('reportes.mis-reportes')">
                            {{ __('Mis Reportes') }}
                        </x-nav-link>

                        <x-nav-link :href="route('avisos.index')" :active="request()->routeIs('avisos.*')">
                            {{ __('Avisos') }}
                            @if (Auth::user()->avisos_no_leidos_count > 0)
                                <span class="ms-1.5 px-1.5 py-0.5 text-[10px] font-black rounded-full bg-rose-600 text-white">
                                    {{ Auth::user()->avisos_no_leidos_count }}
                                </span>
                            @endif
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-3">
                @if (! Auth::user()->isAdmin())
                    <!-- Insignia del Rol para Usuario General -->
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                        {{ Auth::user()->role }}
                    </span>
                @endif

                <x-dropdown align="right" width="w-56" contentClasses="p-1.5 bg-white rounded-2xl shadow-xl border border-slate-100">
                    <x-slot name="trigger">
                        <button type="button" class="group inline-flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-white hover:bg-slate-50/90 border border-slate-300 hover:border-tecnm-wine/40 text-xs font-bold text-slate-800 hover:text-slate-900 shadow-2xs hover:shadow-xs transition-all duration-150 cursor-pointer focus:outline-none focus:ring-2 focus:ring-tecnm-wine/20 active:scale-[0.98]">
                            <!-- Icono de avatar / usuario -->
                            <span class="w-6 h-6 rounded-lg bg-tecnm-wine/10 text-tecnm-wine group-hover:bg-tecnm-wine group-hover:text-white flex items-center justify-center shrink-0 transition-all duration-150 shadow-2xs">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </span>

                            <span class="truncate max-w-[220px]">{{ Auth::user()->name }}</span>

                            <!-- Flecha indicadora de dropdown -->
                            <span class="w-4 h-4 flex items-center justify-center text-slate-400 group-hover:text-tecnm-wine transition-colors duration-150 shrink-0">
                                <svg class="w-3.5 h-3.5 transition-transform duration-200 group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="space-y-0.5">
                            <x-dropdown-link :href="route('profile.edit')" class="group hover:text-tecnm-wine">
                                <svg class="w-4 h-4 text-slate-400 group-hover:text-tecnm-wine transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>{{ __('Perfil') }}</span>
                            </x-dropdown-link>

                            <!-- Cerrar sesión visible y funcional (CA-18) -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                        class="group hover:text-red-600 hover:bg-red-50/70"
                                        onclick="event.preventDefault(); confirmarCierreSesion(this.closest('form'));">
                                    <svg class="w-4 h-4 text-slate-400 group-hover:text-red-500 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span>{{ __('Cerrar sesión') }}</span>
                                </x-dropdown-link>
                            </form>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger Responsive -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 focus:outline-none focus:bg-slate-100 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu (CA-16, CA-18) -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-slate-200 bg-slate-50/70">
        <div class="pt-2 pb-3 space-y-1 px-3">
            @if (Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    {{ __('Panel Administrativo') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.moderacion.index')" :active="request()->routeIs('admin.moderacion.*') || request()->routeIs('admin.publicaciones')">
                    {{ __('Gestión de Objetos') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.devoluciones.index')" :active="request()->routeIs('admin.devoluciones.*')">
                    {{ __('Entregas y Devoluciones') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('avisos.index')" :active="request()->routeIs('avisos.*')">
                    {{ __('Avisos') }}
                    @if (Auth::user()->avisos_no_leidos_count > 0)
                        <span class="ms-2 px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-rose-600 text-white">
                            {{ Auth::user()->avisos_no_leidos_count }}
                        </span>
                    @endif
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('muro')" :active="request()->routeIs('muro')">
                    {{ __('Muro Público') }}
                </x-responsive-nav-link>
            @else
                <x-responsive-nav-link :href="route('muro')" :active="request()->routeIs('muro')">
                    {{ __('Muro de Objetos') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('reportes.mis-reportes')" :active="request()->routeIs('reportes.mis-reportes')">
                    {{ __('Mis Reportes') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('avisos.index')" :active="request()->routeIs('avisos.*')">
                    {{ __('Avisos') }}
                    @if (Auth::user()->avisos_no_leidos_count > 0)
                        <span class="ms-2 px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-rose-600 text-white">
                            {{ Auth::user()->avisos_no_leidos_count }}
                        </span>
                    @endif
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options (CA-18) -->
        <div class="pt-4 pb-3 border-t border-slate-200 px-4">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <div class="font-bold text-sm text-slate-800">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-xs text-slate-500">{{ Auth::user()->email }}</div>
                </div>
                @if (! Auth::user()->isAdmin())
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-700">
                        {{ Auth::user()->role }}
                    </span>
                @endif
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')" class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>{{ __('Perfil') }}</span>
                </x-responsive-nav-link>

                <!-- Cerrar sesión visible en móvil (CA-18) -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            class="flex items-center gap-2.5 text-red-600 hover:text-red-700"
                            onclick="event.preventDefault(); confirmarCierreSesion(this.closest('form'));">
                        <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>{{ __('Cerrar sesión') }}</span>
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>

<!-- Diálogo de Confirmación SweetAlert2 para Cierre de Sesión -->
<script>
    function confirmarCierreSesion(form) {
        if (!form) return;
        if (typeof window.Swal !== 'undefined') {
            window.Swal.fire({
                html: `
                    <div class="relative pt-1 pb-1 px-1 text-center">
                        <!-- Franja decorativa superior degradada vino a dorado -->
                        <div class="absolute -top-6 -inset-x-6 h-1.5 bg-gradient-to-r from-[#691C32] via-[#941A3E] to-[#C59B3F]"></div>

                        <!-- Icono circular con aro dorado y símbolo de salida en vino -->
                        <div class="mx-auto w-16 h-16 rounded-full border-[2.5px] border-[#C59B3F] bg-gradient-to-b from-amber-50 to-orange-50/40 flex items-center justify-center mb-3.5 shadow-md ring-4 ring-amber-100/70">
                            <svg class="w-8 h-8 text-[#691C32]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                            </svg>
                        </div>

                        <!-- Título institucional -->
                        <h3 class="text-xl font-black text-slate-800 tracking-tight">
                            ¿Cerrar sesión?
                        </h3>

                        <!-- Mensaje informativo -->
                        <p class="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed max-w-[270px] mx-auto">
                            ¿Estás seguro de que deseas salir del sistema? Podrás volver a ingresar en cualquier momento con tus credenciales.
                        </p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Sí, cerrar sesión',
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                background: '#FCFBF9',
                backdrop: 'rgba(26, 4, 10, 0.5)',
                customClass: {
                    popup: 'rounded-3xl shadow-2xl border-2 border-amber-400/60 ring-1 ring-black/5 p-6 max-w-sm mx-auto overflow-hidden',
                    confirmButton: 'inline-flex items-center justify-center px-6 py-2.5 rounded-full font-bold text-xs tracking-wide text-white bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] active:bg-[#3B0715] border border-amber-300/40 shadow-md hover:shadow-lg transition-all duration-150 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#691C32] focus:ring-offset-2',
                    cancelButton: 'inline-flex items-center justify-center px-6 py-2.5 rounded-full font-bold text-xs tracking-wide text-slate-700 bg-white hover:bg-slate-100 active:bg-slate-200 border border-slate-300 shadow-xs hover:shadow-sm transition-all duration-150 cursor-pointer ml-3 focus:outline-none focus:ring-2 focus:ring-slate-300'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        } else {
            form.submit();
        }
    }
</script>
