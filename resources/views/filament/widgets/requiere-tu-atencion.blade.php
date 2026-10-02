{{--
    Lo que requiere atención hoy.

    Una sola lista de cosas sobre las que se puede hacer algo, cada una con su
    acción al lado. El criterio de qué entra: si al leerlo la persona no puede
    hacer nada, no va en el escritorio.

    Cuando no hay nada, la tarjeta NO desaparece: se reduce a una barra de una
    línea. Un widget que se esconde deja a quien mira sin saber si está todo al
    día o si el escritorio se rompió.
--}}
<x-filament-widgets::widget>
    <x-filament::section
        :heading="'Requiere tu atención'"
        :description="$this->avisos->isNotEmpty() ? $this->avisos->count().' pendiente'.($this->avisos->count() === 1 ? '' : 's') : null"
        icon="heroicon-o-bell-alert"
    >
        @if ($this->huboError)
            <div class="pb-error">
                <p>
                    No se pudo leer lo pendiente. El resto del escritorio sigue funcionando.
                </p>
                <x-filament::button size="sm" color="danger" wire:click="reintentar" wire:loading.attr="disabled">
                    Reintentar
                </x-filament::button>
            </div>
        @elseif ($this->avisos->isEmpty())
            {{-- Barra de una línea: ocupa lo mínimo y sigue diciendo algo. --}}
            <div class="pb-vacio">
                <x-filament::icon icon="heroicon-m-check-circle" class="pb-punto pb-punto--bien" />
                <span>Todo al día · nada pendiente</span>
            </div>
        @else
            <ul class="pb-lista">
                @foreach ($this->visibles() as $aviso)
                    <li wire:key="aviso-{{ $aviso->id }}"
                        class="pb-fila">
                        <div class="pb-fila-texto">
                            <div class="pb-estado">
                                {{-- El color nunca es el único portador: junto al punto va la palabra. --}}
                                @if ($aviso->urgente)
                                    <span class="pb-punto pb-punto--urgente" aria-hidden="true"></span>
                                @endif
                                <span class="pb-titulo">
                                    {{ $aviso->titulo }}
                                </span>
                            </div>
                            <p class="pb-detalle">
                                {{ $aviso->seccion }} · {{ $aviso->detalle }}
                            </p>
                        </div>

                        @if ($aviso->url)
                            <x-filament::link :href="$aviso->url" size="sm" icon="heroicon-m-arrow-right">
                                {{ $aviso->accion }}
                            </x-filament::link>
                        @else
                            <x-filament::button
                                size="xs"
                                color="gray"
                                wire:click="resolver('{{ $aviso->id }}')"
                                wire:loading.attr="disabled"
                                wire:target="resolver('{{ $aviso->id }}')"
                            >
                                {{ $aviso->accion }}
                            </x-filament::button>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($this->omitidos() > 0)
                <p class="pb-pie">
                    y {{ $this->omitidos() }} más
                </p>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
