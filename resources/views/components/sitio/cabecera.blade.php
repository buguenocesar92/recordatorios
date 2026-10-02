{{--
    Cabecera del sitio público. Sin menú desplegable: los enlaces son pocos y
    envuelven en pantallas angostas. Un menú «hamburguesa» exige JavaScript, y
    la landing no lo tiene.

    La barra es el acento profundo en los dos modos, igual que la del panel y
    la de la consola: es la firma visual del sistema. Texto #eaf5f3 sobre el
    acento profundo mide ≥ 11:1 en las tres paletas; el anillo de foco DENTRO
    de la barra es el realce claro, porque el acento sobre el acento profundo
    desaparece.
--}}
<header class="bg-acento-profundo text-barra-texto">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-3">
        <a href="{{ route('sitio.portada', [], false) }}" class="inline-flex min-h-11 items-center text-lg font-semibold focus-visible:outline-(--marca-realce-claro)">
            {{ config('sitio.entidad.nombre') }}
        </a>
        <nav aria-label="Principal">
            <ul class="flex flex-wrap items-center gap-x-6">
                <li><a href="{{ route('sitio.portada', [], false) }}#servicios" class="inline-flex min-h-11 items-center text-barra-texto-suave hover:text-barra-texto focus-visible:outline-(--marca-realce-claro)">Servicios</a></li>
                <li><a href="{{ route(config('sitio.ingreso'), [], false) }}" class="inline-flex min-h-11 items-center rounded-md border border-current px-4 focus-visible:outline-(--marca-realce-claro)">Ingresar</a></li>
            </ul>
        </nav>
    </div>
</header>
