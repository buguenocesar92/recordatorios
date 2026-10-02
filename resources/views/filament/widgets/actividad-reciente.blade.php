{{--
    Actividad de las últimas dos semanas.

    El gráfico va en SVG y no en un lienzo: así cada barra es un elemento con su
    propio texto, y un lector de pantalla puede recorrerlo. Debajo va la misma
    información como tabla, oculta a la vista pero no a quien no ve el gráfico —
    el color y la altura no pueden ser los únicos portadores del dato.

    La altura está reservada con «aspect-ratio» desde el primer render: sin eso,
    la tarjeta crece cuando llegan los datos y empuja el resto del escritorio.
--}}
<x-filament-widgets::widget>
    <x-filament::section
        :heading="'Actividad reciente'"
        :description="$this->huboError ? null : 'Últimos 14 días · '.number_format($this->total, 0, ',', '.').' registros'"
        icon="heroicon-o-chart-bar"
    >
        @if ($this->huboError)
            <div class="pb-error">
                <p>No se pudo leer la actividad. El resto del escritorio sigue funcionando.</p>
                <x-filament::button size="sm" color="danger" wire:click="reintentar" wire:loading.attr="disabled">
                    Reintentar
                </x-filament::button>
            </div>
        @elseif ($this->total === 0)
            {{-- Vacío no es lo mismo que roto, y hay que decir cuál de los dos es. --}}
            <div class="pb-vacio">
                <x-filament::icon icon="heroicon-m-information-circle" class="pb-punto" />
                <span>Sin actividad registrada en los últimos 14 días</span>
            </div>
        @else
            <div class="pb-grafico" data-grafico-actividad>
                <svg
                    class="pb-grafico-svg"
                    viewBox="0 0 {{ count($this->dias) * 10 }} 60"
                    preserveAspectRatio="none"
                    role="img"
                    aria-label="Actividad diaria de los últimos 14 días. El detalle está en la tabla siguiente."
                >
                    @foreach ($this->dias as $i => $dia)
                        @php
                            // Mínimo 1 de alto para que un día con cero se vea
                            // como una línea y no como un hueco: un hueco parece
                            // un fallo de dibujo, no un día sin movimiento.
                            $alto = $dia['total'] > 0
                                ? max(2, (int) round(($dia['total'] / $this->maximo) * 54))
                                : 1;
                        @endphp
                        <rect
                            class="pb-barra"
                            x="{{ $i * 10 + 2 }}"
                            y="{{ 58 - $alto }}"
                            width="6"
                            height="{{ $alto }}"
                           
                            data-barra
                            style="--i: {{ $i }}"
                        >
                            <title>{{ $dia['fecha']->translatedFormat('l j \d\e F') }}: {{ $dia['total'] }}</title>
                        </rect>
                    @endforeach
                </svg>

                <div class="pb-grafico-ejes" aria-hidden="true">
                    <span>{{ $this->dias->first()['etiqueta'] }}</span>
                    <span>{{ $this->dias->last()['etiqueta'] }}</span>
                </div>
            </div>

            {{-- El mismo dato en texto, para quien no ve el gráfico. --}}
            <table class="fi-sr-only">
                <caption>Actividad diaria de los últimos 14 días</caption>
                <thead>
                    <tr><th scope="col">Día</th><th scope="col">Registros</th></tr>
                </thead>
                <tbody>
                    @foreach ($this->dias as $dia)
                        <tr>
                            <th scope="row">{{ $dia['fecha']->translatedFormat('l j \d\e F') }}</th>
                            <td>{{ $dia['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
