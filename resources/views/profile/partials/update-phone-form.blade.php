@php
    $currentPhone = (string) ($user->phone ?? auth()->user()?->phone ?? '');
@endphp

<section
    x-data="{
        originalPhone: @js($currentPhone),
        phone: @js($currentPhone),
        cancel() {
            this.phone = this.originalPhone;
            if (this.$refs.phoneInput) {
                this.$refs.phoneInput.value = this.originalPhone;
            }
        }
    }"
>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Teléfono de contacto') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Mantén actualizado tu número de teléfono para estar localizable en tus reportes y publicaciones.') }}
        </p>
    </header>

    <form class="mt-6 space-y-6" onsubmit="event.preventDefault(); return false;">
        <div>
            <x-input-label for="phone">
                {{ __('Teléfono de contacto') }} <span class="text-red-600 font-bold" aria-hidden="true">*</span> <span class="text-xs text-red-600 font-medium">({{ __('Obligatorio') }})</span>
            </x-input-label>

            <x-text-input
                x-ref="phoneInput"
                id="phone"
                name="phone"
                type="tel"
                inputmode="numeric"
                autocomplete="tel"
                maxlength="10"
                pattern="[0-9]{10}"
                class="mt-1 block w-full focus:border-tecnm-wine focus:ring-tecnm-wine"
                :value="$currentPhone"
                x-model="phone"
                x-on:input="phone = $event.target.value = $event.target.value.replace(/[^0-9]/g, '').slice(0, 10)"
                required
                aria-required="true"
                aria-describedby="phone-instructions"
            />

            <p id="phone-instructions" class="mt-2 text-sm text-gray-500">
                Ingresa 10 dígitos, sin espacios ni otros caracteres
            </p>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button type="button" id="btn-save-phone">
                {{ __('Guardar cambios') }}
            </x-primary-button>

            <x-secondary-button type="button" id="btn-cancel-phone" x-on:click="cancel()">
                {{ __('Cancelar') }}
            </x-secondary-button>
        </div>
    </form>
</section>
