{{--
    Pie del sitio público: quién responde por el sistema y cómo contactarlo.
    Solo lo configurado; un dato vacío no deja un rótulo huérfano.
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
            <p>Los datos personales que se entregan en este sistema se tratan según la Ley 21.719, solo para el trámite que los pide.</p>
            <p class="mt-2"><a href="{{ route(config('sitio.ingreso'), [], false) }}" class="inline-flex min-h-11 items-center underline-offset-2 hover:underline">Acceso para el personal</a></p>
        </div>
    </div>
</footer>
