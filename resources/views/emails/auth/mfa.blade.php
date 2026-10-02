<x-mail::message>
# Código de verificación

@if($nombre)
Hola **{{ $nombre }}**,
@endif

Usa el siguiente código para completar tu inicio de sesión en {{ config('app.name') }}:

<x-mail::panel>
<p style="font-size:30px; font-weight:bold; letter-spacing:8px; text-align:center; margin:0; color:#1a5fa8;">{{ $codigo }}</p>
</x-mail::panel>

El código vence en **{{ $minutos }} minutos**. Por tu seguridad, no lo compartas con nadie.

**Nadie** de soporte va a pedirte este código por teléfono, correo ni mensaje. Pedir el código haciéndose pasar por soporte es la forma más común de saltarse este paso: si alguien te lo pide, no se lo des.

Si no intentaste iniciar sesión, ignora este correo y considera cambiar tu contraseña: alguien tiene tu contraseña.

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
