<x-filament-panels::page>
    @php
        $datos = $this->indicadores();
        $serie = $this->serieDiaria();
        $maximo = max(1, ...array_column($serie, 'confirmados'));
    @endphp

    {{-- Solo agregados: ni un correo, ni una fila de la lista. Clases propias
         (public/css/panel.css): el panel no compila utilidades de Tailwind. --}}
    <div class="rd-metricas-indicadores">
        @foreach ([
            ['Inscritos', $datos['inscritos'], 'Incluye los que aún no confirman (se borran a las 72 h).'],
            ['Confirmados', $datos['confirmados'], 'Doble opt-in completo.'],
            ['Bajas', $datos['bajas'], 'Acumuladas. La fila se borra; solo queda la cuenta.'],
            ['% con multa último año', $datos['porcentaje_multa'] === null ? '—' : $datos['porcentaje_multa'].' %', 'De '.$datos['respondieron'].' confirmados que respondieron.'],
        ] as [$nombre, $valor, $ayuda])
            <x-filament::section>
                <p class="rd-metricas-nombre">{{ $nombre }}</p>
                <p class="rd-metricas-valor">{{ $valor }}</p>
                <p class="rd-metricas-ayuda">{{ $ayuda }}</p>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section heading="Confirmados por día (últimos 30 días)">
        {{-- El número está escrito en cada fila: la barra es apoyo visual y el
             color nunca es el único portador del dato. --}}
        <table class="rd-metricas-tabla">
            <caption class="rd-solo-lector">Confirmados por día</caption>
            <thead>
                <tr>
                    <th scope="col">Día</th>
                    <th scope="col">Confirmados</th>
                    <th scope="col" style="width: 50%"><span class="rd-solo-lector">Proporción</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach (array_reverse($serie) as $punto)
                    <tr>
                        <td>{{ \App\Filament\Pages\Metricas::formatearDia($punto['dia']) }}</td>
                        <td>{{ $punto['confirmados'] }}</td>
                        <td aria-hidden="true">
                            @if ($punto['confirmados'] > 0)
                                <div class="rd-metricas-barra" style="width: {{ round(100 * $punto['confirmados'] / $maximo) }}%"></div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
