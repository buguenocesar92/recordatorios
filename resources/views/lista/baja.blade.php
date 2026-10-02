<x-sitio.documento titulo="Darte de baja">
    <p>Al confirmar, borramos tu inscripción por completo. No guardamos tu correo.</p>
    <form method="POST" action="{{ $accion }}">
        <button type="submit" class="inline-flex min-h-11 items-center rounded-md bg-acento px-5 font-medium text-sobre-acento hover:bg-acento-fuerte">Darme de baja</button>
    </form>
</x-sitio.documento>
