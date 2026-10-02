<x-mail::message>
# Restablece tu contraseña

@if($nombre)
Hola **{{ $nombre }}**,
@endif

Recibimos una solicitud para restablecer la contraseña de tu cuenta en
{{ config('app.name') }}. Usa el siguiente botón para crear una nueva:

<x-mail::button :url="$url" color="primary">
Restablecer contraseña
</x-mail::button>

Este enlace vence en **{{ $minutos }} minutos**.

Si no solicitaste el cambio, ignora este correo: tu contraseña no se modificará.

Saludos,<br>
{{ config('app.name') }}
</x-mail::message>
