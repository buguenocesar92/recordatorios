<x-filament-panels::page>
    @php
        $datos = $this->indicadores();
        $serie = $this->serieDiaria();
        $maximo = max(1, ...array_column($serie, 'confirmados'));
    @endphp

    {{-- Solo agregados: ni un correo, ni una fila de la lista. --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Inscritos', $datos['inscritos'], 'Incluye los que aún no confirman (se borran a las 72 h).'],
            ['Confirmados', $datos['confirmados'], 'Doble opt-in completo.'],
            ['Bajas', $datos['bajas'], 'Acumuladas. La fila se borra; solo queda la cuenta.'],
            ['% con multa último año', $datos['porcentaje_multa'] === null ? '—' : $datos['porcentaje_multa'].' %', 'De '.$datos['respondieron'].' confirmados que respondieron.'],
        ] as [$nombre, $valor, $ayuda])
            <x-filament::section>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $nombre }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $valor }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $ayuda }}</p>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section heading="Confirmados por día (últimos 30 días)">
        {{-- Tabla y no solo gráfico: el número está escrito en cada fila, así el
             color nunca es el único portador del dato. --}}
        <table class="w-full text-sm tabular-nums">
            <caption class="sr-only">Confirmados por día</caption>
            <thead>
                <tr class="text-left text-gray-500 dark:text-gray-400">
                    <th scope="col" class="py-1 pr-4 font-medium">Día</th>
                    <th scope="col" class="py-1 pr-4 font-medium">Confirmados</th>
                    <th scope="col" class="w-1/2 py-1 font-medium"><span class="sr-only">Barra</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach (array_reverse($serie) as $punto)
                    <tr class="border-t border-gray-100 dark:border-white/5">
                        <td class="py-1 pr-4 text-gray-700 dark:text-gray-300">{{ \App\Filament\Pages\Metricas::formatearDia($punto['dia']) }}</td>
                        <td class="py-1 pr-4 font-medium text-gray-950 dark:text-white">{{ $punto['confirmados'] }}</td>
                        <td class="py-1" aria-hidden="true">
                            <div class="h-2 rounded bg-primary-600 dark:bg-primary-400" style="width: {{ round(100 * $punto['confirmados'] / $maximo) }}%"></div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
