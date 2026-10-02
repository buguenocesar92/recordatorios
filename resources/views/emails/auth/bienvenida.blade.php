<x-mail::message>
# Bienvenido(a) a {{ config('app.name') }}

@if($nombre)
Hola **{{ $nombre }}**,
@endif

Se ha creado una cuenta para ti en la plataforma {{ config('app.name') }}.

<x-mail::panel>
**Usuario (correo):** {{ $correo }}
</x-mail::panel>

Para ingresar, usa el siguiente enlace y la contraseña que te entregó el
administrador:

<x-mail::button :url="$urlIngreso" color="primary">
Ingresar a la plataforma
</x-mail::button>

Por tu seguridad, te recomendamos cambiar tu contraseña la primera vez que ingreses.

Saludos,<br>
{{ config('app.name') }}
</x-mail::message>
