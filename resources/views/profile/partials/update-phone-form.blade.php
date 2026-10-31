@php
    $currentPhone = (string) ($user->phone ?? auth()->user()?->phone ?? '');
@endphp

<section
    x-data="{
        originalPhone: @js($currentPhone),
        phone: @js(old('phone', $currentPhone)),
        hasError: @js($errors->has('phone')),
        cancel() {
            this.phone = this.originalPhone;
            this.hasError = false;
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

    <!-- Ventana emergente SweetAlert2 institucional para notificar actualización exitosa o sin cambios -->
    @if (session('status'))
        <div class="sr-only" aria-live="polite">
            {{ session('status') === 'phone-updated' ? 'Teléfono de contacto actualizado correctamente' : session('status') }}
        </div>
        <script>
            (function() {
                function showPhoneNotification() {
                    var rawStatus = @js(session('status'));
                    var isNoChanges = rawStatus === 'No hay cambios en el teléfono de contacto';
                    var statusText = rawStatus === 'phone-updated' ? 'Teléfono de contacto actualizado correctamente' : rawStatus;
                    var subtitleText = isNoChanges
                        ? 'El número ingresado es idéntico al que ya tiene registrado tu cuenta.'
                        : 'Tu número de contacto ha sido actualizado con éxito en tu cuenta.';

                    var iconHtml = isNoChanges
                        ? `<!-- Icono circular con aro dorado y signo de información/atención -->
                           <div class="mx-auto w-16 h-16 rounded-full border-[2.5px] border-[#C59B3F] bg-gradient-to-b from-amber-50 to-orange-50/40 flex items-center justify-center mb-3.5 shadow-md ring-4 ring-amber-100/70">
                               <svg class="w-8 h-8 text-[#C59B3F]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                               </svg>
                           </div>`
                        : `<!-- Icono circular con aro dorado, brillo suave y palomita en rojo vino -->
                           <div class="mx-auto w-16 h-16 rounded-full border-[2.5px] border-[#C59B3F] bg-gradient-to-b from-amber-50 to-orange-50/40 flex items-center justify-center mb-3.5 shadow-md ring-4 ring-amber-100/70">
                               <svg class="w-8 h-8 text-[#691C32]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                               </svg>
                           </div>`;

                    if (typeof window.Swal !== 'undefined') {
                        window.Swal.fire({
                            html: `
                                <div class="relative pt-1 pb-1 px-1 text-center">
                                    <!-- Franja decorativa superior degradada vino a dorado -->
                                    <div class="absolute -top-6 -inset-x-6 h-1.5 bg-gradient-to-r from-[#691C32] via-[#941A3E] to-[#C59B3F]"></div>

                                    ${iconHtml}

                                    <!-- Título institucional -->
                                    <h3 class="text-xl font-black text-slate-800 tracking-tight">
                                        ${statusText}
                                    </h3>

                                    <!-- Mensaje informativo -->
                                    <p class="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed max-w-[270px] mx-auto">
                                        ${subtitleText}
                                    </p>
                                </div>
                            `,
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar',
                            buttonsStyling: false,
                            background: '#FCFBF9',
                            backdrop: 'rgba(26, 4, 10, 0.5)',
                            showCloseButton: false,
                            customClass: {
                                popup: 'rounded-3xl shadow-2xl border-2 border-amber-400/60 ring-1 ring-black/5 p-6 max-w-sm mx-auto overflow-hidden',
                                confirmButton: 'inline-flex items-center justify-center px-8 py-2.5 rounded-full font-bold text-xs tracking-wide text-white bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] active:bg-[#3B0715] border border-amber-300/40 shadow-md hover:shadow-lg transition-all duration-150 cursor-pointer mt-3.5 focus:outline-none focus:ring-2 focus:ring-[#691C32] focus:ring-offset-2'
                            }
                        });
                    } else {
                        setTimeout(showPhoneNotification, 50);
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', showPhoneNotification);
                } else {
                    showPhoneNotification();
                }
            })();
        </script>
    @endif

    <!-- Ventana emergente SweetAlert2 institucional para notificar errores en la actualización -->
    @if ($errors->has('phone'))
        <script>
            (function() {
                function showPhoneErrorNotification() {
                    var errorMessage = @js($errors->first('phone'));
                    if (typeof window.Swal !== 'undefined') {
                        window.Swal.fire({
                            html: `
                                <div class="relative pt-1 pb-1 px-1 text-center">
                                    <!-- Franja decorativa superior degradada vino a rojo -->
                                    <div class="absolute -top-6 -inset-x-6 h-1.5 bg-gradient-to-r from-[#691C32] via-red-600 to-[#691C32]"></div>

                                    <!-- Icono circular de advertencia/error -->
                                    <div class="mx-auto w-16 h-16 rounded-full border-[2.5px] border-red-300 bg-red-50 flex items-center justify-center mb-3.5 shadow-md ring-4 ring-red-100">
                                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                        </svg>
                                    </div>

                                    <!-- Título institucional -->
                                    <h3 class="text-xl font-black text-slate-800 tracking-tight">
                                        No se pudo actualizar el teléfono
                                    </h3>

                                    <!-- Mensaje informativo de error -->
                                    <p class="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed max-w-[280px] mx-auto">
                                        ${errorMessage}
                                    </p>

                                    <p class="text-[11px] text-slate-500 mt-2">
                                        El número anterior se conserva intacto en tu cuenta.
                                    </p>
                                </div>
                            `,
                            showConfirmButton: true,
                            confirmButtonText: 'Reintentar',
                            buttonsStyling: false,
                            background: '#FCFBF9',
                            backdrop: 'rgba(26, 4, 10, 0.5)',
                            showCloseButton: false,
                            customClass: {
                                popup: 'rounded-3xl shadow-2xl border-2 border-red-300/80 ring-1 ring-black/5 p-6 max-w-sm mx-auto overflow-hidden',
                                confirmButton: 'inline-flex items-center justify-center px-8 py-2.5 rounded-full font-bold text-xs tracking-wide text-white bg-gradient-to-r from-[#691C32] via-red-700 to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] active:bg-[#3B0715] border border-red-300 shadow-md hover:shadow-lg transition-all duration-150 cursor-pointer mt-3.5 focus:outline-none focus:ring-2 focus:ring-[#691C32] focus:ring-offset-2'
                            }
                        });
                    } else {
                        setTimeout(showPhoneErrorNotification, 50);
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', showPhoneErrorNotification);
                } else {
                    showPhoneErrorNotification();
                }
            })();
        </script>
    @endif

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

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
                :value="old('phone', $currentPhone)"
                x-model="phone"
                x-on:input="hasError = false; phone = $event.target.value = $event.target.value.replace(/[^0-9]/g, '').slice(0, 10)"
                required
                aria-required="true"
                aria-describedby="phone-instructions"
            />

            <div x-show="hasError" class="mt-1.5">
                <x-input-error :messages="$errors->get('phone')" />
            </div>

            <p id="phone-instructions" class="mt-2 text-sm text-gray-500">
                Ingresa 10 dígitos, sin espacios ni otros caracteres
            </p>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button id="btn-save-phone">
                {{ __('Guardar cambios') }}
            </x-primary-button>

            <x-secondary-button type="button" id="btn-cancel-phone" x-on:click="cancel()">
                {{ __('Cancelar') }}
            </x-secondary-button>

            @if (session('status'))
                @php
                    $isNoChanges = session('status') === 'No hay cambios en el teléfono de contacto';
                @endphp
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    class="text-sm {{ $isNoChanges ? 'text-amber-700' : 'text-emerald-600' }} font-semibold flex items-center gap-1.5"
                    id="phone-success-message"
                >
                    @if ($isNoChanges)
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    @else
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    @endif
                    <span>{{ session('status') === 'phone-updated' ? 'Teléfono de contacto actualizado correctamente' : session('status') }}</span>
                </p>
            @endif
        </div>
    </form>
</section>
