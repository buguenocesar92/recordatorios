{{--
    Cabecera del sitio público. Sin menú desplegable: los enlaces son pocos y
    envuelven en pantallas angostas (un menú «hamburguesa» exige JavaScript).

    No enlaza al panel: el panel del dueño es solo por VPN y anunciarlo en la
    página pública solo le muestra la puerta a quien no debe tocarla.

    La barra es el acento profundo en los dos modos. El anillo de foco DENTRO
    de la barra es el realce claro, porque el acento sobre el acento profundo
    desaparece.
--}}
<header class="bg-acento-profundo text-barra-texto">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-3">
        <a href="/" class="inline-flex min-h-11 items-center text-lg font-semibold focus-visible:outline-(--marca-realce-claro)">
            {{ config('sitio.nombre') }}
        </a>
        <nav aria-label="Principal">
            <ul class="flex flex-wrap items-center gap-x-6">
                <li><a href="/#como-funciona" class="inline-flex min-h-11 items-center text-barra-texto-suave hover:text-barra-texto focus-visible:outline-(--marca-realce-claro)">Cómo funciona</a></li>
                <li><a href="/que-no-hacemos" class="inline-flex min-h-11 items-center text-barra-texto-suave hover:text-barra-texto focus-visible:outline-(--marca-realce-claro)">Qué no hacemos</a></li>
                <li><a href="/#lista" class="inline-flex min-h-11 items-center rounded-md border border-current px-4 focus-visible:outline-(--marca-realce-claro)">Lista de espera</a></li>
            </ul>
        </nav>
    </div>
</header>
