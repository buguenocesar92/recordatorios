{{--
    Qué se tocó en el panel y quién.

    Responde «¿qué pasó desde ayer?». Solo muestra lo que esta persona
    administra: el historial guarda todo, y sin filtro una oficina vería lo de
    las demás.
--}}
<x-filament-widgets::widget>
    <x-filament::section heading="Movimiento reciente" icon="heroicon-o-clock">
        @if ($this->huboError)
            <div class="pb-error">
                <p>No se pudo leer la actividad.</p>
                <x-filament::button size="sm" color="danger" wire:click="reintentar">Reintentar</x-filament::button>
            </div>
        @elseif ($this->cambios->isEmpty())
            <p class="pb-vacio">
                Todavía no hay movimiento en lo que administras.
            </p>
        @else
            <ul class="pb-lista">
                @foreach ($this->cambios as $cambio)
                    <li class="pb-fila">
                        <div class="pb-fila-texto">
                            <p class="pb-titulo">
                                <span class="font-medium">{{ $cambio['quien'] }}</span>
                                {{ mb_strtolower($cambio['que']) }}
                                <span class="font-medium">{{ $cambio['sobre'] }}</span>
                            </p>
                        </div>
                        {{-- La hora exacta en el title: «hace 3 horas» sirve para
                             leer de un vistazo, pero al reconstruir un incidente
                             hace falta el minuto. --}}
                        <span class="pb-cifra"
                              title="{{ $cambio['fecha'] }}">
                            {{ $cambio['cuando'] }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
