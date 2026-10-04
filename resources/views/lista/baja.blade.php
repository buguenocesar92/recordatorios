<x-sitio.documento titulo="Darte de baja">
    <p>Al confirmar, borramos tu inscripción por completo de la base de datos. Los respaldos cifrados del servidor pueden conservarla hasta que expiran; no se usan para escribirte.</p>
    <form method="POST" action="{{ $accion }}">
        <button type="submit" class="inline-flex min-h-11 items-center rounded-md bg-acento px-5 font-medium text-sobre-acento hover:bg-acento-fuerte">Darme de baja</button>
    </form>
</x-sitio.documento>
