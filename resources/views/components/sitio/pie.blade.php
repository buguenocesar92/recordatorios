{{--
    Pie del sitio público: quién responde, qué no es, y los textos legales.
    Solo los datos de contacto configurados; un dato vacío no deja un rótulo
    huérfano.
--}}
@php
    $entidad = (array) config('sitio.entidad');
@endphp
<footer class="mt-16 border-t border-borde bg-superficie-2">
    <div class="mx-auto flex max-w-5xl flex-wrap justify-between gap-8 px-4 py-10 text-sm">
        <div>
            <p class="font-semibold">{{ $entidad['nombre'] ?? config('sitio.nombre') }}</p>
            <ul class="mt-2 space-y-1 text-atenuado">
                @if (! empty($entidad['direccion']))
                    <li>{{ $entidad['direccion'] }}</li>
                @endif
                @if (! empty($entidad['telefono']))
                    <li><a href="tel:{{ preg_replace('/\s+/', '', $entidad['telefono']) }}" class="inline-flex min-h-11 items-center underline-offset-2 hover:underline">{{ $entidad['telefono'] }}</a></li>
                @endif
                @if (! empty($entidad['correo']))
                    <li><a href="mailto:{{ $entidad['correo'] }}" class="inline-flex min-h-11 items-center underline-offset-2 hover:underline">{{ $entidad['correo'] }}</a></li>
                @endif
            </ul>
        </div>
        <div class="max-w-md text-atenuado">
            <p><strong class="text-tinta">No es un servicio del Estado ni de ningún municipio.</strong> El pago de cada obligación vale solo en su canal oficial.</p>
            <ul class="mt-2 flex flex-wrap gap-x-4">
                <li><a href="/privacidad" class="inline-flex min-h-11 items-center underline-offset-2 hover:underline">Privacidad</a></li>
                <li><a href="/terminos" class="inline-flex min-h-11 items-center underline-offset-2 hover:underline">Términos</a></li>
                <li><a href="/que-no-hacemos" class="inline-flex min-h-11 items-center underline-offset-2 hover:underline">Qué no hacemos</a></li>
            </ul>
        </div>
    </div>
</footer>
