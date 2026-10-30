<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-black text-xl text-slate-800 leading-tight">
                Editar Reporte #{{ $publicacion->id }}
            </h2>
            <a href="{{ route('reportes.mis-reportes') }}" class="text-xs font-bold text-tecnm-wine hover:underline">
                &larr; Volver a Mis Reportes
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 sm:p-8 rounded-3xl tecnm-card-shadow border border-slate-100">
                <form method="POST" action="{{ route('reportes.update', $publicacion->id) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="titulo" class="block text-xs font-bold text-slate-700 mb-1">
                            Título del Objeto <span class="text-red-600 font-bold">*</span>
                        </label>
                        <input
                            id="titulo"
                            name="titulo"
                            type="text"
                            value="{{ old('titulo', $publicacion->titulo) }}"
                            required
                            class="block w-full rounded-xl border-slate-200 text-sm focus:border-tecnm-wine focus:ring-tecnm-wine"
                        />
                        @error('titulo')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="descripcion" class="block text-xs font-bold text-slate-700 mb-1">
                            Descripción <span class="text-red-600 font-bold">*</span>
                        </label>
                        <textarea
                            id="descripcion"
                            name="descripcion"
                            rows="4"
                            required
                            class="block w-full rounded-xl border-slate-200 text-sm focus:border-tecnm-wine focus:ring-tecnm-wine"
                        >{{ old('descripcion', $publicacion->descripcion) }}</textarea>
                        @error('descripcion')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <a href="{{ route('reportes.mis-reportes') }}" class="px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors">
                            Cancelar
                        </a>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-tecnm-wine hover:bg-tecnm-wine-hover text-white text-xs font-bold transition-colors shadow-xs">
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

