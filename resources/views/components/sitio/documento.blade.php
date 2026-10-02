{{-- Marco de las páginas de texto (legales y de la lista de espera). --}}
@props(['titulo', 'borrador' => false])
<x-sitio.layout :titulo="$titulo">
    <article class="mx-auto max-w-3xl px-4 py-12">
        @if ($borrador)
            <p class="mb-6 rounded-md border-2 border-borde-2 bg-superficie p-4 text-sm font-medium">
                Borrador — pendiente de revisión de abogado. No es asesoría legal.
            </p>
        @endif
        <h1 class="text-3xl font-semibold tracking-tight">{{ $titulo }}</h1>
        <div class="mt-6 space-y-4 leading-relaxed [&_h2]:mt-8 [&_h2]:text-xl [&_h2]:font-semibold [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5 [&_a]:font-medium [&_a]:text-acento [&_a]:underline [&_a]:underline-offset-2">
            {{ $slot }}
        </div>
    </article>
</x-sitio.layout>
