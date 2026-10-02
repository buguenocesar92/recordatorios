<x-filament-panels::page>
    {{-- En palabras y no en números sueltos: quien mira esta pantalla no sabe
         qué número es normal, pero sí entiende «no responde». --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ($this->revisiones() as $revision)
            <x-filament::section>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $revision['nombre'] }}
                        </h3>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $revision['detalle'] }}
                        </p>
                    </div>

                    <x-filament::badge
                        :color="match ($revision['estado']) {
                            'bien' => 'success',
                            'atención' => 'warning',
                            default => 'danger',
                        }"
                        :icon="match ($revision['estado']) {
                            'bien' => 'heroicon-m-check-circle',
                            'atención' => 'heroicon-m-exclamation-triangle',
                            default => 'heroicon-m-x-circle',
                        }"
                    >
                        {{ ucfirst($revision['estado']) }}
                    </x-filament::badge>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section icon="heroicon-o-information-circle" heading="Si algo está en rojo">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Anota qué dice el recuadro rojo y pásalo tal cual a quien mantiene el
            servidor. Con eso puede saber dónde mirar sin tener que reproducir el
            problema.
        </p>
    </x-filament::section>
</x-filament-panels::page>
