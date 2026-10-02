<x-sitio.documento titulo="Confirma tu inscripción">
    <p>Falta un paso: presiona el botón para confirmar que quieres estar en la lista de espera.</p>
    <form method="POST" action="/lista-de-espera/confirmar/{{ $token }}">
        <button type="submit" class="inline-flex min-h-11 items-center rounded-md bg-acento px-5 font-medium text-sobre-acento hover:bg-acento-fuerte">Confirmar inscripción</button>
    </form>
    <p class="text-sm text-atenuado">Si no fuiste tú, no hagas nada: la inscripción se borra sola en {{ config('waitlist.confirm_ttl_hours') }} horas.</p>
</x-sitio.documento>
