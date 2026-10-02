{{--
    Si las piezas de las que depende el sistema responden.

    El estado no se distingue solo por color: junto al punto va la palabra. Y
    cada uno trae los milisegundos, porque un servicio que contesta en cuatro
    segundos está técnicamente arriba y en la práctica caído.
--}}
<x-filament-widgets::widget>
    <x-filament::section heading="Estado del sistema" icon="heroicon-o-signal">
        <div class="pb-estados">
            @foreach ($this->chequeos as $chequeo)
                <div class="pb-estado">
                    <span @class([
                        'pb-punto',
                        'pb-punto--bien' => $chequeo['estado'] === 'bien',
                        'pb-punto--lento' => $chequeo['estado'] === 'lento',
                        'pb-punto--caido' => $chequeo['estado'] === 'caído',
                    ]) aria-hidden="true"></span>

                    <span class="pb-titulo">{{ $chequeo['nombre'] }}</span>

                    <span @class([
                        'pb-palabra',
                        'pb-palabra--bien' => $chequeo['estado'] === 'bien',
                        'pb-palabra--lento' => $chequeo['estado'] === 'lento',
                        'pb-palabra--caido' => $chequeo['estado'] === 'caído',
                    ])>{{ $chequeo['estado'] }}</span>

                    {{-- Ancho fijo para las cifras: sin esto bailan de fila en
                         fila y cuesta compararlas de un vistazo. --}}
                    <span class="pb-cifra">
                        {{ $chequeo['ms'] }} ms
                    </span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
