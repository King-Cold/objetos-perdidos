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
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-3">
                <!-- Insignia del Rol Autenticado (CA-09) -->
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold {{ Auth::user()->isAdmin() ? 'bg-tecnm-wine text-white' : 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                    {{ Auth::user()->role }}
                </span>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-slate-200 text-xs leading-4 font-bold rounded-xl text-slate-700 bg-slate-50/80 hover:bg-slate-100 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1.5 text-slate-400">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Perfil') }}
                        </x-dropdown-link>

                        <!-- Cerrar sesión visible y funcional (CA-18) -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Cerrar sesión') }}
                            </x-dropdown-link>
                        </form>
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
            @endif
        </div>

        <!-- Responsive Settings Options (CA-18) -->
        <div class="pt-4 pb-3 border-t border-slate-200 px-4">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <div class="font-bold text-sm text-slate-800">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-xs text-slate-500">{{ Auth::user()->email }}</div>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ Auth::user()->isAdmin() ? 'bg-tecnm-wine text-white' : 'bg-slate-200 text-slate-700' }}">
                    {{ Auth::user()->role }}
                </span>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Perfil') }}
                </x-responsive-nav-link>

                <!-- Cerrar sesión visible en móvil (CA-18) -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Cerrar sesión') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
