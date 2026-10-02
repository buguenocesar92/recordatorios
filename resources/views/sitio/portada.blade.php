{{--
    La portada. Tres bloques desde config('sitio.portada'): el claim con la
    llamada a la acción, los servicios y un cierre que repite la llamada. Sin
    JavaScript, sin consultas, sin fuentes web: el elemento LCP es el <h1> o la
    imagen precargada.
--}}
<x-sitio.layout titulo="Inicio" :descripcion="config('sitio.descripcion')" :imagen="$portada['imagen'] ?? null">
    <section class="mx-auto max-w-5xl px-4 pb-12 pt-16">
        <h1 class="max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl">{{ $portada['claim'] }}</h1>
        <p class="mt-4 max-w-2xl text-lg text-atenuado">{{ $portada['bajada'] }}</p>
        <a href="{{ $ctaDireccion }}" class="mt-8 inline-flex min-h-11 items-center rounded-md bg-acento px-5 font-medium text-sobre-acento hover:bg-acento-fuerte">
            {{ $ctaTexto }}
        </a>
        @if (! empty($portada['imagen']['src']))
            <x-imagen
                :src="$portada['imagen']['src']"
                :ancho="$portada['imagen']['ancho']"
                :alto="$portada['imagen']['alto']"
                :alt="$portada['imagen']['alt'] ?? ''"
                prioritaria
                class="mt-10 rounded-lg"
            />
        @endif
    </section>

    <section id="servicios" aria-labelledby="servicios-titulo" class="mx-auto max-w-5xl px-4 py-12">
        <h2 id="servicios-titulo" class="text-2xl font-semibold">Qué se puede hacer</h2>
        <ul class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($portada['servicios'] ?? [] as $servicio)
                <li class="rounded-lg border border-borde bg-superficie p-5">
                    <h3 class="text-lg font-medium">
                        @if (! empty($servicio['ruta']))
                            <a href="{{ route($servicio['ruta'], [], false) }}" class="inline-flex min-h-11 items-center text-acento underline-offset-2 hover:underline">{{ $servicio['titulo'] }}</a>
                        @else
                            {{ $servicio['titulo'] }}
                        @endif
                    </h3>
                    <p class="mt-2 text-atenuado">{{ $servicio['descripcion'] }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="mx-auto max-w-5xl px-4 py-12">
        <div class="rounded-lg bg-superficie-2 p-8 sm:flex sm:items-center sm:justify-between">
            <p class="text-xl font-medium">¿Listo para empezar?</p>
            <a href="{{ $ctaDireccion }}" class="mt-4 inline-flex min-h-11 items-center rounded-md bg-acento px-5 font-medium text-sobre-acento hover:bg-acento-fuerte sm:mt-0">
                {{ $ctaTexto }}
            </a>
        </div>
    </section>
</x-sitio.layout>
