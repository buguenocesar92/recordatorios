{{--
    La portada: qué es, qué NO es, y el formulario de la lista de espera.

    Sin Livewire ni Alpine. El único guion ejecutable es el widget de
    Cloudflare Turnstile (autorizado por host en PoliticaDeLaListaDeEspera).
    Sin él el formulario se ve y se puede llenar, pero el servidor no acepta
    la inscripción (Turnstile falla cerrado) y la página lo dice en texto.

    El mismo archivo dibuja la respuesta 422 con los errores: llega $errors
    (ViewErrorBag) y $anterior con lo que la persona había escrito.
--}}
@php
    $errores = isset($errors) ? $errors->getBag('default') : new \Illuminate\Support\MessageBag;
    $anterior = $anterior ?? [];
    $interesesAnteriores = (array) ($anterior['interests'] ?? []);
    $describe = fn (string $campo, string $ayuda = ''): string => trim($ayuda.' '.($errores->has($campo) ? 'error-'.$campo : ''));
@endphp
<x-sitio.layout titulo="Inicio" :descripcion="config('sitio.descripcion')" :imagen="$portada['imagen'] ?? null">
    <x-slot:cabeza>
        {{-- Sin integrity (SRI) a propósito: Cloudflare cambia api.js sin aviso y
             pide no fijarlo ni copiarlo; con un hash fijo el widget dejaría de
             cargar el día que lo actualicen. El riesgo se acota en la política:
             solo challenges.cloudflare.com puede entregar guiones. --}}
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    </x-slot:cabeza>

    <section aria-labelledby="titulo" class="mx-auto max-w-5xl px-4 pb-8 pt-12 sm:pt-16">
        <h1 id="titulo" class="max-w-3xl text-3xl font-semibold tracking-tight sm:text-5xl">{{ $portada['claim'] }}</h1>
        <p class="mt-4 max-w-2xl text-lg text-atenuado">{{ $portada['bajada'] }}</p>
        <a href="#lista" class="mt-8 inline-flex min-h-11 items-center rounded-md bg-acento px-5 font-medium text-sobre-acento hover:bg-acento-fuerte">
            Quiero que me avisen
        </a>
        @if (! empty($portada['imagen']['src']))
            <x-imagen
                :src="$portada['imagen']['src']"
                :ancho="$portada['imagen']['ancho']"
                :alto="$portada['imagen']['alto']"
                :alt="$portada['imagen']['alt'] ?? ''"
                prioritaria
                class="mt-10 rounded-lg"
            />
        @endif
    </section>

    <aside aria-labelledby="aviso-titulo" class="mx-auto max-w-5xl px-4">
        <div class="rounded-lg border border-borde-2 bg-superficie p-6">
            <h2 id="aviso-titulo" class="text-lg font-semibold">Antes que nada, lo que esto no es</h2>
            <ul class="mt-3 space-y-2 text-tinta">
                <li><strong>No es un servicio del Estado ni de ningún municipio.</strong> Es un producto privado de {{ config('sitio.entidad.nombre') }}.</li>
                <li><strong>No pedimos ClaveÚnica</strong>, Clave Tributaria, claves de bancos ni ninguna otra credencial.</li>
                <li><strong>No cobramos ni recibimos pagos.</strong> El pago vale solo en el sitio oficial de cada organismo.</li>
            </ul>
        </div>
    </aside>

    <section id="como-funciona" aria-labelledby="como-titulo" class="mx-auto max-w-5xl px-4 py-12">
        <h2 id="como-titulo" class="text-2xl font-semibold">Cómo va a funcionar</h2>
        <ol class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <li class="rounded-lg border border-borde bg-superficie p-5">
                <p class="text-sm font-medium text-atenuado">Paso 1</p>
                <h3 class="mt-1 text-lg font-medium">Eliges qué pagas</h3>
                <p class="mt-2 text-atenuado">Contribuciones, permiso de circulación, SOAP, patente o aseo. Sin RUT, sin patente del auto, sin dirección.</p>
            </li>
            <li class="rounded-lg border border-borde bg-superficie p-5">
                <p class="text-sm font-medium text-atenuado">Paso 2</p>
                <h3 class="mt-1 text-lg font-medium">Te avisamos antes</h3>
                <p class="mt-2 text-atenuado">Por correo, con días de anticipación, y con el enlace al sitio oficial donde se paga.</p>
            </li>
            <li class="rounded-lg border border-borde bg-superficie p-5">
                <p class="text-sm font-medium text-atenuado">Paso 3</p>
                <h3 class="mt-1 text-lg font-medium">Guardas el comprobante</h3>
                <p class="mt-2 text-atenuado">Para tenerlo a mano la próxima vez. Cifrado, y solo lo ves tú.</p>
            </li>
        </ol>
        <p class="mt-4 text-sm text-atenuado">Las fechas que mostremos vendrán de fuentes oficiales y, mientras no las confirmemos, irán marcadas como «no verificadas».</p>
    </section>

    <section id="lista" aria-labelledby="lista-titulo" class="mx-auto max-w-5xl px-4 py-12">
        <div class="max-w-2xl">
            <h2 id="lista-titulo" class="text-2xl font-semibold">Únete a la lista de espera</h2>
            <p class="mt-2 text-atenuado">Todavía no está abierto. Déjanos tu correo y te avisamos cuando lo esté. Te mandaremos un enlace para confirmar: sin confirmación, borramos el dato en 72 horas.</p>

            @if ($errores->any())
                <div role="alert" aria-labelledby="errores-titulo" class="mt-6 rounded-lg border-2 border-error bg-superficie p-4" tabindex="-1" id="errores">
                    <h3 id="errores-titulo" class="font-semibold text-error">Revisa el formulario: hay {{ $errores->count() === 1 ? 'un problema' : $errores->count().' problemas' }}</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errores->keys() as $campo)
                            <li><a class="underline underline-offset-2" href="#{{ $campo === 'turnstile' ? 'verificacion' : 'campo-'.$campo }}">{{ $errores->first($campo) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="/lista-de-espera" class="mt-6 space-y-6 rounded-lg border border-borde bg-superficie p-5 sm:p-6" novalidate>
                <div>
                    <label for="campo-email" class="block font-medium">Correo electrónico</label>
                    <p id="ayuda-email" class="mt-1 text-sm text-atenuado">Solo para avisarte. No lo compartimos ni lo vendemos.</p>
                    <input id="campo-email" name="email" type="email" required autocomplete="email" inputmode="email" maxlength="320"
                        value="{{ $anterior['email'] ?? '' }}"
                        aria-describedby="{{ $describe('email', 'ayuda-email') }}"
                        @if ($errores->has('email')) aria-invalid="true" @endif
                        class="mt-2 block min-h-11 w-full rounded-md border border-borde-2 bg-lienzo px-3 text-tinta">
                    @if ($errores->has('email'))
                        <p id="error-email" class="mt-2 text-sm font-medium text-error">Error: {{ $errores->first('email') }}</p>
                    @endif
                </div>

                <div>
                    <label for="campo-comuna" class="block font-medium">Comuna <span class="font-normal text-atenuado">(opcional)</span></label>
                    <p id="ayuda-comuna" class="mt-1 text-sm text-atenuado">Para saber qué fechas comunales te importan. Puedes dejarlo en blanco.</p>
                    <select id="campo-comuna" name="comuna"
                        aria-describedby="{{ $describe('comuna', 'ayuda-comuna') }}"
                        @if ($errores->has('comuna')) aria-invalid="true" @endif
                        class="mt-2 block min-h-11 w-full rounded-md border border-borde-2 bg-lienzo px-3 text-tinta">
                        <option value="">Prefiero no decirlo</option>
                        @foreach ($comunas as $region => $lista)
                            <optgroup label="{{ $region }}">
                                @foreach ($lista as $comuna)
                                    <option value="{{ $comuna['code'] }}" @selected(($anterior['comuna'] ?? null) === $comuna['code'])>{{ $comuna['name'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @if ($errores->has('comuna'))
                        <p id="error-comuna" class="mt-2 text-sm font-medium text-error">Error: {{ $errores->first('comuna') }}</p>
                    @endif
                </div>

                <fieldset id="campo-interests" @if ($errores->has('interests') || $errores->has('interests.0')) aria-describedby="error-interests" @endif>
                    <legend class="font-medium">¿Qué te gustaría que te recordemos? <span class="font-normal text-atenuado">(opcional)</span></legend>
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($tipos as $tipo)
                            <label class="flex min-h-11 items-center gap-3 rounded-md border border-borde px-3">
                                <input type="checkbox" name="interests[]" value="{{ $tipo->value }}" class="size-5 accent-(--sitio-acento)" @checked(in_array($tipo->value, $interesesAnteriores, true))>
                                <span>{{ $tipo->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if ($errores->has('interests') || $errores->has('interests.0'))
                        <p id="error-interests" class="mt-2 text-sm font-medium text-error">Error: {{ $errores->first('interests') ?: $errores->first('interests.0') }}</p>
                    @endif
                </fieldset>

                <div>
                    <div class="flex items-start gap-3">
                        <input id="campo-consent" name="consent" type="checkbox" value="1" required
                            aria-describedby="{{ $describe('consent', 'ayuda-consent') }}"
                            @if ($errores->has('consent')) aria-invalid="true" @endif
                            class="mt-1 size-5 shrink-0 accent-(--sitio-acento)">
                        <label for="campo-consent">
                            Acepto que {{ config('sitio.entidad.nombre') }} guarde mi correo para avisarme cuando esto abra, según el
                            <a href="/privacidad" class="font-medium text-acento underline underline-offset-2">aviso de privacidad</a>.
                        </label>
                    </div>
                    <p id="ayuda-consent" class="mt-2 pl-8 text-sm text-atenuado">Entiendo que no es un servicio del Estado ni de ningún municipio. Puedo darme de baja con un clic desde cualquier correo.</p>
                    @if ($errores->has('consent'))
                        <p id="error-consent" class="mt-2 pl-8 text-sm font-medium text-error">Error: {{ $errores->first('consent') }}</p>
                    @endif
                </div>

                <div id="verificacion">
                    <p class="text-sm text-atenuado">Comprobamos que no eres un robot con Cloudflare Turnstile, sin imágenes que descifrar.</p>
                    <div class="cf-turnstile mt-2 min-h-[65px]" data-sitekey="{{ $sitekey }}" data-language="es" data-theme="auto" data-size="flexible"></div>
                    <noscript><p class="mt-2 text-sm font-medium">Para inscribirte necesitas JavaScript activado (lo usa solo la comprobación anti-robots).</p></noscript>
                    @if ($errores->has('turnstile'))
                        <p id="error-turnstile" class="mt-2 text-sm font-medium text-error">Error: {{ $errores->first('turnstile') }}</p>
                    @endif
                </div>

                <button type="submit" class="inline-flex min-h-11 items-center rounded-md bg-acento px-5 font-medium text-sobre-acento hover:bg-acento-fuerte">
                    Inscribirme
                </button>
            </form>
        </div>
    </section>

    <section aria-labelledby="no-hacemos-titulo" class="mx-auto max-w-5xl px-4 py-12">
        <h2 id="no-hacemos-titulo" class="text-2xl font-semibold">Lo que nunca vamos a hacer</h2>
        <ul class="mt-4 max-w-2xl list-disc space-y-2 pl-5 text-atenuado">
            <li>Entrar a portales del Estado, bancos o aseguradoras en tu nombre.</li>
            <li>Calcular multas, intereses o montos: los fija cada organismo.</li>
            <li>Pedirte RUT, patente del vehículo, rol de la propiedad o dirección.</li>
            <li>Vender tus datos o usarlos para publicidad.</li>
        </ul>
        <p class="mt-4"><a href="/que-no-hacemos" class="inline-flex min-h-11 items-center font-medium text-acento underline underline-offset-2">La lista completa</a></p>
    </section>
</x-sitio.layout>
