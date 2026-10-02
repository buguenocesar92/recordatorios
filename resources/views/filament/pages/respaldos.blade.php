<x-filament-panels::page>
    {{-- Estilos propios: el CSS compilado del panel no incluye utilidades Tailwind
         arbitrarias usadas solo en este blade (flex, justify-between…), así que el
         layout se define aquí y no depende de que Filament las haya recolectado. --}}
    {{-- El panel de Filament conmuta el modo oscuro con la clase «.dark» en
         <html>, no con data-theme: los tonos oscuros cuelgan de «.dark …». --}}
    <style>
        .respaldos-intro { font-size: .875rem; line-height: 1.5; color: #475569; }
        .dark .respaldos-intro { color: #cbd5e1; }

        .respaldos-lista { display: flex; flex-direction: column; }
        .respaldos-fila {
            display: flex; align-items: center; justify-content: space-between;
            gap: .75rem; flex-wrap: wrap; padding: .75rem 0;
            border-top: 1px solid rgba(0,0,0,.06);
        }
        .respaldos-fila:first-child { border-top: 0; }
        .dark .respaldos-fila { border-top-color: rgba(255,255,255,.08); }

        .respaldos-cuando { font-size: .875rem; font-weight: 600; color: #0f172a; margin: 0; }
        .respaldos-detalle { font-size: .75rem; color: #64748b; margin: .15rem 0 0; }
        .dark .respaldos-cuando { color: #f8fafc; }
        .dark .respaldos-detalle { color: #94a3b8; }
    </style>

    <x-filament::section icon="heroicon-o-information-circle" heading="Para qué sirve">
        <p class="respaldos-intro">
            Cada noche el sistema guarda solo una copia completa de la base de datos.
            Aquí puedes <strong>bajar la más reciente a tu computador</strong> sin pedirle
            a nadie que entre al servidor: útil antes de un cambio grande, para llevar una
            copia fuera del municipio, o si necesitas restaurar algo. Descargar no borra ni
            altera nada; solo te llevas una copia.
        </p>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-archive-box" heading="Copias disponibles">
        @php($respaldos = $this->respaldos())

        @if ($respaldos === [])
            {{-- Se dice qué pasa y qué hacer: «no hay nada» a secas deja a quien
                 mira sin saber si es normal o si algo falló. --}}
            <p class="respaldos-intro">
                Todavía no hay respaldos. Se hacen solos cada noche; si llevas más
                de un día sin ver ninguno, avisa a quien mantiene el servidor.
            </p>
        @else
            <div class="respaldos-lista">
                @foreach ($respaldos as $respaldo)
                    <div class="respaldos-fila">
                        <div>
                            <p class="respaldos-cuando">{{ $respaldo['cuando'] }}</p>
                            <p class="respaldos-detalle">{{ $respaldo['nombre'] }} — {{ $respaldo['peso'] }}</p>
                        </div>

                        <x-filament::button
                            wire:click="descargar('{{ $respaldo['nombre'] }}')"
                            icon="heroicon-m-arrow-down-tray"
                            color="gray"
                            size="sm"
                        >
                            Descargar
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::section icon="heroicon-o-shield-exclamation" heading="Antes de descargar uno">
        <p class="respaldos-intro">
            Un respaldo contiene la base de datos completa, con los datos
            personales de todas las personas registradas. Guárdalo donde
            corresponde y bórralo cuando ya no lo necesites.
        </p>
    </x-filament::section>
</x-filament-panels::page>
