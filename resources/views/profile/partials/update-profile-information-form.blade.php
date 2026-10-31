<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Información institucional de la cuenta (solo lectura).') }}
        </p>
    </header>

    <div class="mt-6 space-y-6">
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input
                id="name"
                name="name"
                type="text"
                class="mt-1 block w-full bg-gray-100 text-gray-600 cursor-not-allowed border-gray-200"
                :value="$user->name"
                readonly
                disabled
            />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input
                id="email"
                name="email"
                type="email"
                class="mt-1 block w-full bg-gray-100 text-gray-600 cursor-not-allowed border-gray-200"
                :value="$user->email"
                readonly
                disabled
            />
            <p class="mt-1 text-xs text-slate-500">
                {{ __('El nombre y correo electrónico son gestionados institucionalmente y no pueden editarse desde el perfil.') }}
            </p>
        </div>
    </div>
</section>
