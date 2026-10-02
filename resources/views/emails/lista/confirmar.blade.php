<x-mail::message>
# Confirma tu inscripción

Alguien (esperamos que tú) pidió sumarse a la lista de espera de **{{ config('sitio.nombre') }}**, un servicio de recordatorios de obligaciones de KraftDo SpA.

Para confirmar, abre este enlace y presiona «Confirmar»:

<x-mail::button :url="$confirmar">
Abrir la confirmación
</x-mail::button>

El enlace vence en {{ $horas }} horas y sirve una sola vez. Si no lo confirmas, borramos la inscripción.

**No es un servicio del Estado ni de ningún municipio.** Nunca te vamos a pedir ClaveÚnica, claves de bancos ni pagos.

Si no fuiste tú, ignora este correo o [bórrala ahora]({{ $baja }}).

{{ config('sitio.entidad.nombre') }}
</x-mail::message>
