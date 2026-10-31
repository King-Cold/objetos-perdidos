<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                        Nuevo reporte
                    </span>
                    <span class="text-xs text-slate-400">&bull;</span>
                    <span class="text-xs font-semibold text-slate-500">Campus Poniente</span>
                </div>
                <h2 class="font-black text-2xl text-slate-800 leading-tight">
                    Registrar Hallazgo
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Registra la pertenencia encontrada para que Biblioteca la valide y su dueño pueda recuperarla.
                </p>
            </div>
            <div>
                <a href="{{ route('reportes.mis-reportes') }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-xs">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Volver a Mis Reportes</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Ventana emergente SweetAlert2 institucional con bordes elegantes y detalles en rojo vino y dorado -->
            @if (session('status'))
                <script>
                    (function() {
                        function showValidationPopup() {
                            if (typeof window.Swal !== 'undefined') {
                                window.Swal.fire({
                                    html: `
                                        <div class="relative pt-1 pb-1 px-1 text-center">
                                            <!-- Franja decorativa superior degradada vino a dorado -->
                                            <div class="absolute -top-6 -inset-x-6 h-1.5 bg-gradient-to-r from-[#691C32] via-[#941A3E] to-[#C59B3F]"></div>

                                            <!-- Icono circular con aro dorado, brillo suave y palomita en rojo vino -->
                                            <div class="mx-auto w-16 h-16 rounded-full border-[2.5px] border-[#C59B3F] bg-gradient-to-b from-amber-50 to-orange-50/40 flex items-center justify-center mb-3.5 shadow-md ring-4 ring-amber-100/70">
                                                <svg class="w-8 h-8 text-[#691C32]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                </svg>
                                            </div>

                                            <!-- Título institucional -->
                                            <h3 class="text-xl font-black text-slate-800 tracking-tight">
                                                Reporte Registrado con Éxito
                                            </h3>

                                            @if (session('folio'))
                                                <div class="my-3 px-3 py-2 bg-amber-50/90 rounded-2xl border border-amber-200 shadow-2xs">
                                                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Folio único asignado</span>
                                                    <span class="text-base sm:text-lg font-black text-[#691C32] tracking-wider">{{ session('folio') }}</span>
                                                </div>
                                            @endif

                                            <!-- Mensaje informativo -->
                                            <p class="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed max-w-[320px] mx-auto">
                                                {{ session('status') }}
                                            </p>

                                            <!-- Insignia informativa sutil -->
                                            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-center">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 border border-amber-200/80 text-[11px] font-semibold text-amber-900 shadow-xs">
                                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                                    Estado: Pendiente de aprobación
                                                </span>
                                            </div>
                                        </div>
                                    `,
                                    showConfirmButton: true,
                                    confirmButtonText: 'Entendido',
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
                                setTimeout(showValidationPopup, 50);
                            }
                        }

                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', showValidationPopup);
                        } else {
                            showValidationPopup();
                        }
                    })();
                </script>
            @endif

            @if (session('error'))
                <script>
                    (function() {
                        function showErrorPopup() {
                            if (typeof window.Swal !== 'undefined') {
                                window.Swal.fire({
                                    icon: 'error',
                                    title: 'Atención',
                                    text: @js(session('error')),
                                    confirmButtonText: 'Entendido',
                                    buttonsStyling: false,
                                    customClass: {
                                        confirmButton: 'inline-flex items-center justify-center px-6 py-2.5 rounded-xl bg-[#691C32] text-white font-bold text-xs shadow'
                                    }
                                });
                            } else {
                                setTimeout(showErrorPopup, 50);
                            }
                        }
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', showErrorPopup);
                        } else {
                            showErrorPopup();
                        }
                    })();
                </script>
            @endif

            <script>
                function hallazgoForm() {
                    return {
                        images: [],
                        maxImages: 5,
                        submitting: false,
                        descripcion: @js(old('descripcion', '')),
                        fecha: @js(old('fecha_hallazgo', $maxDate)),
                        hora: @js(old('hora_hallazgo', '')),
                        minDate: @js($minDate),
                        maxDate: @js($maxDate),
                        currentTime: @js($currentTime),
                        clientErrors: {},
                        getCurrentTime() {
                            const d = new Date();
                            const hh = String(d.getHours()).padStart(2, '0');
                            const mm = String(d.getMinutes()).padStart(2, '0');
                            return `${hh}:${mm}`;
                        },
                        isDateToday() {
                            if (!this.fecha) return false;
                            const d = new Date();
                            const yyyy = d.getFullYear();
                            const mm = String(d.getMonth() + 1).padStart(2, '0');
                            const dd = String(d.getDate()).padStart(2, '0');
                            const localToday = `${yyyy}-${mm}-${dd}`;
                            return (this.fecha === this.maxDate || this.fecha === localToday);
                        },
                        validateHora() {
                            delete this.clientErrors.hora_hallazgo;
                            if (!this.hora) return;

                            if (this.isDateToday()) {
                                const nowTime = this.getCurrentTime();
                                const inputTime = this.hora.slice(0, 5);

                                if (inputTime > nowTime) {
                                    this.clientErrors.hora_hallazgo = 'La hora del hallazgo no puede ser posterior a la hora actual (' + nowTime + ').';
                                    if (typeof window.Swal !== 'undefined') {
                                        window.Swal.fire({
                                            icon: 'warning',
                                            title: 'Hora no permitida',
                                            text: `Para un objeto encontrado hoy, no puedes registrar una hora posterior a la hora actual de tu equipo (${nowTime}). Por favor selecciona una hora válida.`,
                                            confirmButtonText: 'Entendido',
                                            buttonsStyling: false,
                                            customClass: {
                                                confirmButton: 'inline-flex items-center justify-center px-6 py-2.5 rounded-xl bg-[#691C32] text-white font-bold text-xs shadow'
                                            }
                                        });
                                    }
                                    this.hora = '';
                                }
                            }
                        },
                        get charCount() {
                            return (this.descripcion || '').trim().length;
                        },
                        fileSelected(e) {
                            const files = Array.from(e.target.files || []);
                            if (!files.length) return;

                            const allowedExtensions = ['jpg', 'png'];
                            const maxSizeBytes = 2 * 1024 * 1024; // 2 MB

                            let invalidTypeFiles = [];
                            let oversizedFiles = [];
                            let validFiles = [];

                            files.forEach(file => {
                                const ext = (file.name.split('.').pop() || '').toLowerCase();
                                if (!allowedExtensions.includes(ext)) {
                                    invalidTypeFiles.push(file.name);
                                } else if (file.size > maxSizeBytes) {
                                    oversizedFiles.push(file.name);
                                } else {
                                    validFiles.push(file);
                                }
                            });

                            if (invalidTypeFiles.length > 0) {
                                if (typeof window.Swal !== 'undefined') {
                                    window.Swal.fire({
                                        icon: 'error',
                                        title: 'Formato no permitido',
                                        text: 'Solo se permiten fotografías en formato JPG o PNG. Archivo(s) omitido(s): ' + invalidTypeFiles.join(', '),
                                        confirmButtonText: 'Entendido',
                                        buttonsStyling: false,
                                        customClass: {
                                            confirmButton: 'inline-flex items-center justify-center px-6 py-2 rounded-xl bg-[#691C32] text-white font-bold text-xs shadow-sm'
                                        }
                                    });
                                }
                            }

                            if (oversizedFiles.length > 0) {
                                if (typeof window.Swal !== 'undefined') {
                                    window.Swal.fire({
                                        icon: 'error',
                                        title: 'Tamaño excedido',
                                        text: 'Cada fotografía no debe exceder los 2 MB. Archivo(s) omitido(s): ' + oversizedFiles.join(', '),
                                        confirmButtonText: 'Entendido',
                                        buttonsStyling: false,
                                        customClass: {
                                            confirmButton: 'inline-flex items-center justify-center px-6 py-2 rounded-xl bg-[#691C32] text-white font-bold text-xs shadow-sm'
                                        }
                                    });
                                }
                            }

                            if (this.images.length + validFiles.length > this.maxImages) {
                                if (typeof window.Swal !== 'undefined') {
                                    window.Swal.fire({
                                        icon: 'warning',
                                        title: 'Límite de fotografías',
                                        text: 'Solo puedes adjuntar hasta 5 fotografías en total.',
                                        confirmButtonText: 'Entendido',
                                        buttonsStyling: false,
                                        customClass: {
                                            confirmButton: 'inline-flex items-center justify-center px-6 py-2 rounded-xl bg-[#691C32] text-white font-bold text-xs shadow-sm'
                                        }
                                    });
                                }
                            }

                            const availableSlots = this.maxImages - this.images.length;
                            const filesToAdd = validFiles.slice(0, Math.max(0, availableSlots));

                            filesToAdd.forEach(file => {
                                const previewUrl = window.URL && window.URL.createObjectURL ? window.URL.createObjectURL(file) : '';
                                this.images.push({
                                    file: file,
                                    url: previewUrl,
                                    name: file.name,
                                    size: (file.size / 1024).toFixed(0) + ' KB'
                                });
                            });

                            this.syncFiles();

                            if (this.images.length >= 1 && this.clientErrors.fotografias) {
                                delete this.clientErrors.fotografias;
                            }

                            if (this.$refs.pickerInput) {
                                this.$refs.pickerInput.value = '';
                            }
                        },
                        removeImage(index) {
                            if (this.images[index] && this.images[index].url && this.images[index].url.startsWith('blob:')) {
                                try { window.URL.revokeObjectURL(this.images[index].url); } catch (err) {}
                            }
                            this.images.splice(index, 1);
                            this.syncFiles();
                            if (this.images.length === 0) {
                                this.clientErrors.fotografias = 'Debes adjuntar al menos una fotografía del objeto.';
                            }
                        },
                        syncFiles() {
                            try {
                                const dt = new DataTransfer();
                                this.images.forEach(item => {
                                    if (item.file) {
                                        dt.items.add(item.file);
                                    }
                                });
                                if (this.$refs.fileInput) {
                                    this.$refs.fileInput.files = dt.files;
                                }
                            } catch (e) {
                                console.error('DataTransfer error:', e);
                            }
                        },
                        validateClient(e) {
                            this.syncFiles();
                            this.clientErrors = {};
                            const trimmedDesc = (this.descripcion || '').trim();
                            if (trimmedDesc.length < 50) {
                                this.clientErrors.descripcion = 'La descripción debe contener al menos 50 caracteres.';
                            } else if (trimmedDesc.length > 200) {
                                this.clientErrors.descripcion = 'La descripción no debe superar los 200 caracteres.';
                            }

                            if (this.fecha > this.maxDate) {
                                this.clientErrors.fecha_hallazgo = 'La fecha del hallazgo no puede ser una fecha futura.';
                            } else if (this.fecha < this.minDate) {
                                this.clientErrors.fecha_hallazgo = 'Solo se pueden registrar objetos encontrados durante los últimos 7 días.';
                            }

                            if (this.hora && this.isDateToday()) {
                                const nowTime = this.getCurrentTime();
                                if (this.hora.slice(0, 5) > nowTime) {
                                    this.clientErrors.hora_hallazgo = 'La hora del hallazgo no puede ser posterior a la hora actual (' + nowTime + ').';
                                    if (typeof window.Swal !== 'undefined') {
                                        window.Swal.fire({
                                            icon: 'warning',
                                            title: 'Hora no permitida',
                                            text: `Para un objeto encontrado hoy, no puedes registrar una hora posterior a la hora actual de tu equipo (${nowTime}). Por favor selecciona una hora válida.`,
                                            confirmButtonText: 'Entendido',
                                            buttonsStyling: false,
                                            customClass: {
                                                confirmButton: 'inline-flex items-center justify-center px-6 py-2.5 rounded-xl bg-[#691C32] text-white font-bold text-xs shadow'
                                            }
                                        });
                                    }
                                    this.hora = '';
                                }
                            }

                            if (this.images.length < 1) {
                                this.clientErrors.fotografias = 'Debes adjuntar al menos una fotografía del objeto.';
                            } else if (this.images.length > 5) {
                                this.clientErrors.fotografias = 'No puedes subir más de 5 fotografías.';
                            }

                            if (Object.keys(this.clientErrors).length > 0) {
                                if (e && e.preventDefault) e.preventDefault();
                                return false;
                            }
                            return true;
                        },
                        submitForm(e) {
                            this.syncFiles();
                            if (this.submitting) {
                                if (e && e.preventDefault) e.preventDefault();
                                return false;
                            }

                            if (!this.validateClient(e)) {
                                return false;
                            }

                            if (e && e.preventDefault) e.preventDefault();
                            this.submitting = true;

                            const form = document.getElementById('form-hallazgo');
                            const formData = new FormData(form);

                            fetch(form.action, {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                },
                                body: formData
                            })
                            .then(async (response) => {
                                const data = await response.json().catch(() => ({}));
                                if (!response.ok) {
                                    const errMsg = data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : 'Ocurrió un error al procesar el reporte.');
                                    throw new Error(errMsg);
                                }
                                return data;
                            })
                            .then((data) => {
                                if (typeof window.Swal !== 'undefined') {
                                    window.Swal.fire({
                                        icon: 'success',
                                        title: 'Reporte Enviado Correctamente',
                                        html: `
                                            <div class="text-center space-y-3">
                                                <p class="text-sm text-slate-600">
                                                    Tu reporte de hallazgo ha sido registrado con éxito.
                                                </p>
                                                <div class="p-3 bg-amber-50 rounded-2xl border border-amber-200">
                                                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Folio único asignado</span>
                                                    <span class="text-lg font-black text-[#691C32] tracking-wider">${data.folio}</span>
                                                </div>
                                                <p class="text-xs font-semibold text-amber-800">
                                                    Estado: <span class="underline">Pendiente de aprobación</span>
                                                </p>
                                                <p class="text-xs text-slate-500 leading-relaxed">
                                                    Personal de Biblioteca verificará los datos y el resguardo físico antes de su publicación en el muro institucional.
                                                </p>
                                            </div>
                                        `,
                                        confirmButtonText: 'Entendido',
                                        buttonsStyling: false,
                                        allowOutsideClick: false,
                                        customClass: {
                                            confirmButton: 'inline-flex items-center justify-center px-8 py-2.5 rounded-full font-bold text-xs tracking-wide text-white bg-gradient-to-r from-[#691C32] via-[#7D1636] to-[#691C32] hover:from-[#520B1D] hover:to-[#520B1D] shadow-md transition-all cursor-pointer'
                                        }
                                    }).then(() => {
                                        window.location.href = data.redirect_url || "{{ route('reportes.mis-reportes') }}";
                                    });
                                } else {
                                    window.location.href = data.redirect_url || "{{ route('reportes.mis-reportes') }}";
                                }
                            })
                            .catch((error) => {
                                this.submitting = false;
                                if (typeof window.Swal !== 'undefined') {
                                    window.Swal.fire({
                                        icon: 'error',
                                        title: 'Atención',
                                        text: error.message || 'Ocurrió un error al intentar registrar el reporte. Por favor, intenta nuevamente.',
                                        confirmButtonText: 'Entendido',
                                        buttonsStyling: false,
                                        customClass: {
                                            confirmButton: 'inline-flex items-center justify-center px-6 py-2.5 rounded-xl bg-[#691C32] text-white font-bold text-xs shadow'
                                        }
                                    });
                                } else {
                                    alert(error.message || 'Ocurrió un error al intentar registrar el reporte.');
                                }
                            });
                        }
                    };
                }
            </script>

            <form
                id="form-hallazgo"
                method="POST"
                action="{{ route('reportes.store') }}"
                enctype="multipart/form-data"
                x-data="hallazgoForm()"
                @submit="submitForm($event)"
                class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden"
            >
                @csrf
                <input type="hidden" name="form_token" value="{{ $formToken ?? '' }}" />

                <!-- Aviso Institucional Integrado en la Cabecera del Formulario -->
                <div class="bg-gradient-to-r from-amber-50 via-orange-50/50 to-amber-50/80 border-b border-amber-200/80 px-6 sm:px-8 py-4 sm:py-5 flex items-start gap-3.5">
                    <div class="p-2 rounded-xl bg-amber-100 text-[#691C32] shrink-0 mt-0.5 shadow-xs">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                        <h3 class="font-bold text-slate-900 mb-0.5">
                            Aviso importante sobre la publicación de hallazgos
                        </h3>
                        <p class="text-xs text-slate-600">
                            Al enviar este formulario, tu reporte será canalizado al personal encargado de biblioteca para su <strong>revisión y validación institucional</strong>. El objeto no será publicado de inmediato en el muro general hasta que se verifiquen los datos y se confirme el resguardo físico.
                        </p>
                    </div>
                </div>

                <!-- Cuadrícula Principal de 2 Columnas Unificadas -->
                <div class="grid grid-cols-1 lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-slate-100 items-stretch">

                    <!-- ========================================== -->
                    <!-- COLUMNA IZQUIERDA (7/12): Datos del Objeto -->
                    <!-- ========================================== -->
                    <div class="lg:col-span-7 p-6 sm:p-8 space-y-5">

                        <!-- Encabezado de sección con indicador -->
                        <div class="border-b border-slate-100 pb-3.5 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-tecnm-wine/10 text-tecnm-wine flex items-center justify-center font-black text-xs">
                                    1
                                </div>
                                <div>
                                    <h3 class="text-base font-extrabold text-slate-900 leading-tight">
                                        Datos del Objeto Encontrado
                                    </h3>
                                    <p class="text-[11px] text-slate-500">
                                        Información descriptiva y ubicación física
                                    </p>
                                </div>
                            </div>
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-50 border border-slate-200 text-[11px] font-semibold text-slate-600">
                                <span class="text-red-600 font-bold text-sm">*</span>
                                <span>Campos obligatorios</span>
                            </div>
                        </div>

                        <!-- 1. Nombre del objeto encontrado -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label for="nombre" class="block text-xs font-bold text-slate-800">
                                    Nombre del objeto encontrado <span class="text-red-600 font-bold" aria-hidden="true">*</span>
                                </label>
                                <span class="text-[11px] text-slate-400">Máx. 150 caracteres</span>
                            </div>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                required
                                maxlength="150"
                                value="{{ old('nombre') }}"
                                placeholder="Ej. Mochila escolar negra, Credencial de estudiante, Termo de acero azul..."
                                class="block w-full rounded-xl border text-sm placeholder:text-slate-400 transition-colors {{ $errors->has('nombre') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-slate-200 focus:border-tecnm-wine focus:ring-tecnm-wine' }}"
                            />
                            @error('nombre')
                                <p class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    <span>{{ $message }}</span>
                                </p>
                            @else
                                <p class="mt-1 text-[11px] text-slate-500">
                                    Indica de forma breve y clara el tipo o nombre común de la pertenencia hallada.
                                </p>
                            @enderror
                        </div>

                        <!-- 2. Descripción del objeto -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label for="descripcion" class="block text-xs font-bold text-slate-800">
                                    Descripción del objeto <span class="text-red-600 font-bold" aria-hidden="true">*</span>
                                </label>
                                <span class="text-[11px] text-slate-400">Entre 50 y 200 caracteres</span>
                            </div>
                            <textarea
                                id="descripcion"
                                name="descripcion"
                                rows="3"
                                required
                                x-model="descripcion"
                                placeholder="Describe características clave: marca, modelo, color predominante, estado físico, calcomanías, contenido visible o cualquier seña distintiva que ayude a acreditar la propiedad..."
                                class="block w-full rounded-xl border text-sm placeholder:text-slate-400 transition-colors {{ $errors->has('descripcion') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-slate-200 focus:border-tecnm-wine focus:ring-tecnm-wine' }}"
                            >{{ old('descripcion') }}</textarea>

                            <!-- Contador visual de caracteres -->
                            <div class="flex items-center justify-between mt-1 text-[11px]">
                                <span class="text-slate-500">
                                    Mínimo 50 y máximo 200 caracteres.
                                </span>
                                <span :class="charCount < 50 || charCount > 200 ? 'text-amber-800 font-bold' : 'text-emerald-700 font-bold'">
                                    <span x-text="charCount"></span> / 200 caracteres
                                </span>
                            </div>

                            <template x-if="clientErrors.descripcion">
                                <p class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    <span x-text="clientErrors.descripcion"></span>
                                </p>
                            </template>

                            @error('descripcion')
                                <p class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        <!-- Grid 2 cols: Fecha y Hora -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                            <!-- 3. Fecha en que fue encontrado -->
                            <div>
                                <label for="fecha_hallazgo" class="block text-xs font-bold text-slate-800 mb-1">
                                    Fecha en que fue encontrado <span class="text-red-600 font-bold" aria-hidden="true">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="fecha_hallazgo"
                                    name="fecha_hallazgo"
                                    required
                                    min="{{ $minDate }}"
                                    max="{{ $maxDate }}"
                                    value="{{ old('fecha_hallazgo', $maxDate) }}"
                                    x-model="fecha"
                                    @change="validateHora()"
                                    class="block w-full rounded-xl border text-sm transition-colors {{ $errors->has('fecha_hallazgo') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-slate-200 focus:border-tecnm-wine focus:ring-tecnm-wine' }}"
                                />

                                <template x-if="clientErrors.fecha_hallazgo">
                                    <p class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        <span x-text="clientErrors.fecha_hallazgo"></span>
                                    </p>
                                </template>

                                @error('fecha_hallazgo')
                                    <p class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        <span>{{ $message }}</span>
                                    </p>
                                @else
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        Objetos de los últimos 7 días (del {{ \Carbon\Carbon::parse($minDate)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($maxDate)->format('d/m/Y') }}).
                                    </p>
                                @enderror
                            </div>

                            <!-- 6. Hora en que fue encontrado (opcional) -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label for="hora_hallazgo" class="block text-xs font-bold text-slate-800">
                                        Hora en que fue encontrado
                                    </label>
                                    <span class="text-[10px] text-slate-500 font-bold px-1.5 py-0.5 rounded bg-slate-100">
                                        Opcional
                                    </span>
                                </div>
                                <input
                                    type="time"
                                    id="hora_hallazgo"
                                    name="hora_hallazgo"
                                    value="{{ old('hora_hallazgo') }}"
                                    x-model="hora"
                                    :max="isDateToday() ? getCurrentTime() : null"
                                    @change="validateHora()"
                                    @blur="validateHora()"
                                    :class="clientErrors.hora_hallazgo ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '{{ $errors->has('hora_hallazgo') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-slate-200 focus:border-tecnm-wine focus:ring-tecnm-wine' }}'"
                                    class="block w-full rounded-xl border text-sm text-slate-700 transition-colors"
                                />

                                <template x-if="clientErrors.hora_hallazgo">
                                    <p class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        <span x-text="clientErrors.hora_hallazgo"></span>
                                    </p>
                                </template>

                                @error('hora_hallazgo')
                                    <p class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        <span>{{ $message }}</span>
                                    </p>
                                @else
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        Hora aproximada (no posterior a la hora actual para hoy).
                                    </p>
                                @enderror
                            </div>
                        </div>

                        <!-- Grid 2 cols: Área y Ubicación específica -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                            <!-- 4. Área o zona donde se encontró -->
                            <div>
                                <label for="area_zona" class="block text-xs font-bold text-slate-800 mb-1">
                                    Área o zona donde se encontró <span class="text-red-600 font-bold" aria-hidden="true">*</span>
                                </label>
                                @php
                                    $selectedArea = old('area_zona');
                                @endphp
                                <select
                                    id="area_zona"
                                    name="area_zona"
                                    required
                                    class="block w-full rounded-xl border text-sm bg-white transition-colors {{ $errors->has('area_zona') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-slate-200 focus:border-tecnm-wine focus:ring-tecnm-wine' }}"
                                >
                                    <option value="" disabled {{ empty($selectedArea) ? 'selected' : '' }}>Selecciona un área o edificio...</option>
                                    <optgroup label="Biblioteca y Servicios">
                                        <option value="Biblioteca Campus Poniente" {{ $selectedArea == 'Biblioteca Campus Poniente' ? 'selected' : '' }}>Biblioteca Campus Poniente</option>
                                        <option value="Cafetería" {{ $selectedArea == 'Cafetería' ? 'selected' : '' }}>Cafetería y Comedores</option>
                                        <option value="Centro de Cómputo" {{ $selectedArea == 'Centro de Cómputo' ? 'selected' : '' }}>Centro de Cómputo</option>
                                        <option value="Auditorio / Audiovisual" {{ $selectedArea == 'Auditorio / Audiovisual' ? 'selected' : '' }}>Auditorio / Salas Audiovisuales</option>
                                    </optgroup>
                                    <optgroup label="Edificios de Sistemas e Informática">
                                        <option value="Edificio G - Sistemas" {{ $selectedArea == 'Edificio G - Sistemas' ? 'selected' : '' }}>Edificio G (Sistemas y Computación)</option>
                                        <option value="Edificio H - Sistemas" {{ $selectedArea == 'Edificio H - Sistemas' ? 'selected' : '' }}>Edificio H (Sistemas y Computación)</option>
                                        <option value="Edificio B - Laboratorios" {{ $selectedArea == 'Edificio B - Laboratorios' ? 'selected' : '' }}>Edificio B (Laboratorios de Cómputo)</option>
                                    </optgroup>
                                    <optgroup label="Otros Edificios y Aulas">
                                        <option value="Edificio A - Dirección y Aulas" {{ $selectedArea == 'Edificio A - Dirección y Aulas' ? 'selected' : '' }}>Edificio A (Dirección y Aulas)</option>
                                        <option value="Edificio C - Talleres" {{ $selectedArea == 'Edificio C - Talleres' ? 'selected' : '' }}>Edificio C (Talleres y Aulas)</option>
                                        <option value="Edificio D - Aulas" {{ $selectedArea == 'Edificio D - Aulas' ? 'selected' : '' }}>Edificio D (Aulas)</option>
                                        <option value="Edificio E - Aulas" {{ $selectedArea == 'Edificio E - Aulas' ? 'selected' : '' }}>Edificio E (Aulas)</option>
                                        <option value="Edificio F - Aulas" {{ $selectedArea == 'Edificio F - Aulas' ? 'selected' : '' }}>Edificio F (Aulas)</option>
                                    </optgroup>
                                    <optgroup label="Espacios Exteriores y Generales">
                                        <option value="Canchas y Explanada" {{ $selectedArea == 'Canchas y Explanada' ? 'selected' : '' }}>Canchas Deportivas / Explanada</option>
                                        <option value="Estacionamiento General" {{ $selectedArea == 'Estacionamiento General' ? 'selected' : '' }}>Estacionamiento General</option>
                                        <option value="Áreas Verdes y Pasillos" {{ $selectedArea == 'Áreas Verdes y Pasillos' ? 'selected' : '' }}>Áreas Verdes y Pasillos Centrales</option>
                                        <option value="Otra zona del campus" {{ $selectedArea == 'Otra zona del campus' ? 'selected' : '' }}>Otra zona del campus</option>
                                    </optgroup>
                                </select>
                                @error('area_zona')
                                    <p class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        <span>{{ $message }}</span>
                                    </p>
                                @else
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        Edificio o instalación general del campus.
                                    </p>
                                @enderror
                            </div>

                            <!-- 5. Ubicación específica -->
                            <div>
                                <label for="ubicacion_especifica" class="block text-xs font-bold text-slate-800 mb-1">
                                    Ubicación específica <span class="text-red-600 font-bold" aria-hidden="true">*</span>
                                </label>
                                <textarea
                                    id="ubicacion_especifica"
                                    name="ubicacion_especifica"
                                    rows="2"
                                    required
                                    placeholder="Ej. Fila 3 del aula G-102, mesa 4 de lectura en biblioteca..."
                                    class="block w-full rounded-xl border text-sm placeholder:text-slate-400 transition-colors {{ $errors->has('ubicacion_especifica') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-slate-200 focus:border-tecnm-wine focus:ring-tecnm-wine' }}"
                                >{{ old('ubicacion_especifica') }}</textarea>
                                @error('ubicacion_especifica')
                                    <p class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        <span>{{ $message }}</span>
                                    </p>
                                @else
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        Punto exacto dentro del área o edificio.
                                    </p>
                                @enderror
                            </div>
                        </div>

                    </div>

                    <!-- ======================================================= -->
                    <!-- COLUMNA DERECHA (5/12): Fotos y Contacto                -->
                    <!-- ======================================================= -->
                    <div class="lg:col-span-5 p-6 sm:p-8 space-y-5 bg-slate-50/50 flex flex-col justify-between">

                        <div class="space-y-5">
                            <!-- Sección: Fotografías del objeto -->
                            <div>
                                <div class="flex items-center justify-between pb-2.5 border-b border-slate-200/70 mb-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-amber-50 border border-amber-200 text-tecnm-wine flex items-center justify-center font-black text-xs shadow-xs">
                                            2
                                        </div>
                                        <label for="fotografia" class="block text-xs font-extrabold text-slate-900">
                                            Fotografías del objeto <span class="text-red-600 font-bold" aria-hidden="true">*</span>
                                        </label>
                                    </div>
                                    <span class="text-[10px] text-amber-900 font-bold px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200">
                                        <span x-text="images.length"></span> de 5 (mín. 1, máx. 5)
                                    </span>
                                </div>

                                <div class="space-y-3">
                                    <!-- Input real del formulario que viaja al servidor -->
                                    <input
                                        x-ref="fileInput"
                                        id="fotografia"
                                        name="fotografias[]"
                                        type="file"
                                        multiple
                                        accept=".jpg,.png"
                                        class="sr-only"
                                    />

                                    <!-- Input auxiliar selector de archivos -->
                                    <input
                                        x-ref="pickerInput"
                                        type="file"
                                        multiple
                                        accept=".jpg,.png"
                                        class="sr-only"
                                        @change="fileSelected($event)"
                                    />

                                    <!-- Estado cuando no hay fotos seleccionadas -->
                                    <template x-if="images.length === 0">
                                        <div
                                            @click="$refs.pickerInput.click()"
                                            class="flex flex-col items-center justify-center p-6 border-2 border-dashed rounded-2xl transition-colors bg-white cursor-pointer group {{ $errors->has('fotografias') || $errors->has('fotografias.*') ? 'border-red-400 bg-red-50/20' : 'border-slate-300 hover:border-tecnm-wine/60' }}"
                                            :class="clientErrors.fotografias ? 'border-red-400 bg-red-50/20' : ''"
                                        >
                                            <div class="mx-auto w-11 h-11 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center text-tecnm-wine mb-2 shadow-xs group-hover:scale-105 transition-transform">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                                                </svg>
                                            </div>
                                            <div class="text-xs text-slate-700 text-center font-medium">
                                                <span class="font-bold text-tecnm-wine group-hover:underline">Selecciona de 1 a 5 fotos</span>
                                                <span class="text-slate-400"> o arrástralas</span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 mt-1">
                                                JPG o PNG (máximo 2 MB por archivo)
                                            </p>
                                        </div>
                                    </template>

                                    <!-- Galería cuando hay al menos 1 fotografía -->
                                    <template x-if="images.length > 0">
                                        <div class="space-y-2.5">
                                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                                <template x-for="(img, idx) in images" :key="idx">
                                                    <div class="relative group rounded-xl overflow-hidden border border-slate-200 bg-white shadow-2xs">
                                                        <img :src="img.url" :alt="img.name" class="w-full h-20 object-cover" />
                                                        <div class="p-1 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-[9px]">
                                                            <span class="truncate max-w-[65px] text-slate-600 font-medium" x-text="img.name"></span>
                                                            <button
                                                                type="button"
                                                                @click="removeImage(idx)"
                                                                class="text-red-600 hover:text-red-800 font-bold ml-1"
                                                                title="Quitar foto"
                                                            >
                                                                ✕
                                                            </button>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- Botón para agregar más fotos hasta el límite de 5 -->
                                                <template x-if="images.length < maxImages">
                                                    <button
                                                        type="button"
                                                        @click="$refs.pickerInput.click()"
                                                        class="h-20 border-2 border-dashed border-slate-300 hover:border-tecnm-wine rounded-xl flex flex-col items-center justify-center p-1 text-slate-500 hover:text-tecnm-wine bg-white hover:bg-amber-50/20 transition-colors"
                                                    >
                                                        <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                        </svg>
                                                        <span class="text-[10px] font-bold">Añadir más</span>
                                                        <span class="text-[8px] text-slate-400" x-text="(maxImages - images.length) + ' disponibles'"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Mensajes de validación de fotografías -->
                                    <template x-if="clientErrors.fotografias">
                                        <p class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                            <span x-text="clientErrors.fotografias"></span>
                                        </p>
                                    </template>

                                    @error('fotografias')
                                        <p class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                            <span>{{ $message }}</span>
                                        </p>
                                    @enderror

                                    @error('fotografias.*')
                                        <p class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                            <span>{{ $message }}</span>
                                        </p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Sección: Datos del Usuario que Registra el Reporte (Solo lectura) -->
                            <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-2xs space-y-3">
                                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center font-black text-[11px]">
                                            3
                                        </div>
                                        <h3 class="text-xs font-extrabold text-slate-900">
                                            Datos del Usuario que Registra el Reporte
                                        </h3>
                                    </div>
                                    <span class="text-[9px] uppercase font-extrabold text-slate-500 bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded">
                                        Solo lectura
                                    </span>
                                </div>

                                <div>
                                    <label for="user_name" class="block text-[11px] font-bold text-slate-700 mb-1">
                                        Nombre del usuario
                                    </label>
                                    <div class="relative">
                                        <input
                                            type="text"
                                            id="user_name"
                                            name="user_name"
                                            value="{{ Auth::user()->name }}"
                                            readonly
                                            aria-readonly="true"
                                            class="block w-full rounded-xl border-slate-200 bg-slate-100 text-slate-600 text-xs font-semibold cursor-not-allowed select-none focus:ring-0 focus:border-slate-200 py-2"
                                        />
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="user_phone" class="block text-[11px] font-bold text-slate-700 mb-1">
                                        Teléfono de contacto
                                    </label>
                                    <div class="relative">
                                        <input
                                            type="text"
                                            id="user_phone"
                                            name="user_phone"
                                            value="{{ Auth::user()->phone ?? 'Sin teléfono registrado' }}"
                                            readonly
                                            aria-readonly="true"
                                            class="block w-full rounded-xl border-slate-200 bg-slate-100 text-slate-600 text-xs font-semibold cursor-not-allowed select-none focus:ring-0 focus:border-slate-200 py-2"
                                        />
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                                    <span class="text-[11px] text-slate-400">¿Cambió tu teléfono?</span>
                                    <a
                                        href="{{ route('profile.edit') }}"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-tecnm-wine hover:underline"
                                    >
                                        <span>Actualizar datos en mi perfil</span>
                                        <span aria-hidden="true">&rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Barra Inferior de Acciones Unificada (Footer del Formulario) -->
                <div class="bg-slate-50 border-t border-slate-200/90 px-6 sm:px-8 py-4 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Verifica que los datos del hallazgo y la ubicación sean lo más claros posible.</span>
                    </div>

                    <div class="flex items-center justify-end gap-3 w-full sm:w-auto">
                        <a
                            href="{{ route('reportes.mis-reportes') }}"
                            class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors shadow-xs"
                        >
                            Cancelar
                        </a>

                        <button
                            type="submit"
                            id="btn-enviar-revision"
                            :disabled="submitting"
                            :class="submitting ? 'opacity-70 cursor-not-allowed' : ''"
                            class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-tecnm-wine focus:ring-offset-2"
                        >
                            <span x-show="!submitting" class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Encontré un objeto</span>
                            </span>
                            <span x-show="submitting" class="inline-flex items-center gap-2" style="display: none;">
                                <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Enviando reporte...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
