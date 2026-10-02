{{--
    Muestrario de los componentes ya instalados.

    Cada bloque muestra el componente funcionando y, debajo, cómo se escribe.
    Ver el resultado sin el código obliga a ir a buscarlo al vendor, que es
    justo lo que esta pantalla existe para evitar.
--}}
<x-filament-panels::page>
    <div class="cmp-intro">
        <p>
            Estos componentes vienen con el paquete de interfaz del ecosistema y
            <strong>ya están instalados</strong>: se usan escribiendo la etiqueta, sin
            instalar ni configurar nada. Escribir uno a mano cuando ya existe hace que
            cada sistema se vea distinto.
        </p>
        <p class="cmp-nota">
            Esta pantalla solo existe en desarrollo.
        </p>
    </div>

    @php
        // Datos de muestra, no reales: un muestrario con datos del sistema
        // acabaría enseñando información de personas a quien entre a mirar
        // componentes.
        $dias = [
            ['label' => 'lun', 'value' => 12], ['label' => 'mar', 'value' => 19],
            ['label' => 'mié', 'value' => 7], ['label' => 'jue', 'value' => 23],
            ['label' => 'vie', 'value' => 15], ['label' => 'sáb', 'value' => 4],
        ];
    @endphp

    <section class="cmp-grupo" data-cmp-grupo>
        <h2 class="cmp-titulo">Mostrar un número</h2>
        <div class="cmp-fila">
            <div class="cmp-caja">
                <x-muni::kpi value="1.284" label="Trámites del mes" tone="accent" hint="+12% que el mes pasado" />
                <code class="cmp-codigo" tabindex="0">&lt;x-muni::kpi value="1.284" label="Trámites del mes" tone="accent" /&gt;</code>
            </div>
            <div class="cmp-caja">
                <x-muni::stat value="98%" label="Resueltos a tiempo" tone="ok" delta="+3" deltaDir="up" />
                <code class="cmp-codigo" tabindex="0">&lt;x-muni::stat value="98%" label="Resueltos a tiempo" tone="ok" delta="+3" /&gt;</code>
            </div>
            <div class="cmp-caja">
                <x-muni::ring :value="72" label="Avance" tone="accent" />
                <code class="cmp-codigo" tabindex="0">&lt;x-muni::ring :value="72" label="Avance" /&gt;</code>
            </div>
        </div>
    </section>

    <section class="cmp-grupo" data-cmp-grupo>
        <h2 class="cmp-titulo">Mostrar una serie</h2>
        <div class="cmp-fila">
            <div class="cmp-caja cmp-caja--ancha">
                <x-muni::chart-bar :data="$dias" :height="140" />
                <code class="cmp-codigo" tabindex="0">&lt;x-muni::chart-bar :data="$dias" :height="140" /&gt;</code>
            </div>
            <div class="cmp-caja">
                <x-muni::progress :value="64" label="Expedientes revisados" showValue />
                <code class="cmp-codigo" tabindex="0">&lt;x-muni::progress :value="64" label="…" showValue /&gt;</code>
            </div>
        </div>
    </section>

    <section class="cmp-grupo" data-cmp-grupo>
        <h2 class="cmp-titulo">Decir en qué estado está algo</h2>
        <div class="cmp-fila">
            <div class="cmp-caja">
                <x-muni::alert tone="warn" title="Falta acreditar la fecha de nacimiento">
                    Sin ese dato no se puede tramitar una solicitud de un menor de edad.
                </x-muni::alert>
                <code class="cmp-codigo" tabindex="0">&lt;x-muni::alert tone="warn" title="…"&gt;…&lt;/x-muni::alert&gt;</code>
            </div>
            <div class="cmp-caja">
                <div class="cmp-badges">
                    <x-muni::badge tone="ok">Vigente</x-muni::badge>
                    <x-muni::badge tone="warn">Por vencer</x-muni::badge>
                    <x-muni::badge tone="danger">Vencido</x-muni::badge>
                </div>
                <code class="cmp-codigo" tabindex="0">&lt;x-muni::badge tone="ok"&gt;Vigente&lt;/x-muni::badge&gt;</code>
            </div>
        </div>
    </section>

    <section class="cmp-grupo" data-cmp-grupo>
        <h2 class="cmp-titulo">Cuando todavía no hay nada que mostrar</h2>
        <div class="cmp-fila">
            <div class="cmp-caja">
                <x-muni::empty-state
                    title="Sin solicitudes pendientes"
                    description="Cuando alguien ingrese una, aparecerá acá." />
                <code class="cmp-codigo" tabindex="0">&lt;x-muni::empty-state title="…" description="…" /&gt;</code>
            </div>
            <div class="cmp-caja">
                <div class="cmp-skeletons">
                    <x-muni::skeleton width="60%" height="16px" />
                    <x-muni::skeleton height="12px" />
                    <x-muni::skeleton width="80%" height="12px" />
                </div>
                <code class="cmp-codigo" tabindex="0">&lt;x-muni::skeleton width="60%" height="16px" /&gt;</code>
            </div>
        </div>
    </section>

    <section class="cmp-grupo" data-cmp-grupo>
        <h2 class="cmp-titulo">Mostrar un recorrido</h2>
        <div class="cmp-fila">
            <div class="cmp-caja cmp-caja--ancha">
                <x-muni::stepper
                    :steps="['Ingresada', 'En revisión', 'Resuelta']"
                    :current="1" />
                <code class="cmp-codigo" tabindex="0">&lt;x-muni::stepper :steps="[…]" :current="1" /&gt;</code>
            </div>
        </div>
    </section>

    <p class="cmp-pie">
        Hay más de cincuenta en total. La lista completa está en
        <code>vendor/muni-graneros/laravel-muni-ui/resources/views/components</code>,
        y cada archivo declara arriba las propiedades que acepta.
    </p>
</x-filament-panels::page>
