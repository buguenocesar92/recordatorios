@auth
    @if(($surface ?? null) !== 'app')
        {{-- Paneles Filament: driver.js bundleado --}}
        @vite(['resources/js/tour.js'])
    @endif
    {{-- Acabado accesible del recorrido: foco visible y área táctil en sus
         botones. Lo pedía la prueba automática de accesibilidad. --}}
    <link rel="stylesheet" href="{{ asset('css/onboarding.css') }}">
    <script @cspNonce>
        window.OnboardingConfig = {
            system: @json($system ?? null),
            surface: @json($surface ?? null),
            role: @json(method_exists(auth()->user(), 'getRoleNames') ? auth()->user()->getRoleNames()->first() : null),
            csrfToken: @json(csrf_token()),
            autoStart: {{ ($autoStart ?? true) ? 'true' : 'false' }},
        };
    </script>
    <script src="{{ asset('js/onboarding.js') }}?v=1" @cspNonce></script>
    <script @cspNonce>
        // Driver.js deja en la página un elemento auxiliar («driver-dummy-element»)
        // con atributos ARIA que su propio rol no admite: la revisión automática
        // lo marca como violación crítica, y un lector de pantalla puede
        // anunciar algo que no existe. No es nuestro código, pero sí nuestra
        // página. Se le quitan los atributos en cuanto aparece.
        (function () {
            function limpiar() {
                document.querySelectorAll('#driver-dummy-element').forEach(function (el) {
                    Array.from(el.attributes)
                        .filter(function (a) { return a.name.startsWith('aria-') || a.name === 'role'; })
                        .forEach(function (a) { el.removeAttribute(a.name); });
                    el.setAttribute('aria-hidden', 'true');
                });
            }

            limpiar();
            new MutationObserver(limpiar).observe(document.body, { childList: true, subtree: true });
        })();
    </script>
@endauth
