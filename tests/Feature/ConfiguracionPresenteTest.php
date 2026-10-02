<?php

/**
 * Los archivos de configuración que el .env necesita para que se le haga caso.
 *
 * Laravel 11+ ignora EN SILENCIO las variables del .env cuyo archivo de
 * configuración no existe: no avisa, no falla, simplemente usa su valor de
 * fábrica. Sin «config/app.php», todo sistema que nazca de este scaffold corre
 * en UTC —cuatro horas adelantado— y con los textos del framework en inglés,
 * por más que el .env diga otra cosa. Se descubre cuando una fecha sale mal en
 * un documento oficial.
 */
it('el sistema corre en la hora de Chile', function () {
    expect(config('app.timezone'))->toBe('America/Santiago')
        ->and(now()->timezone->getName())->toBe('America/Santiago');
});

it('el sistema habla en español', function () {
    expect(config('app.locale'))->toBe('es');
});

it('un proyecto nuevo nace en español, sin depender del .env de quien lo clone', function () {
    // Se mira el archivo de configuración y el ejemplo del entorno, no el valor
    // efectivo: el .env de cada máquina es suyo, pero lo que hereda un sistema
    // recién generado tiene que venir en español de fábrica. El respaldo también:
    // si un texto no está traducido, más vale el idioma de quien lo lee que inglés.
    $config = (string) file_get_contents(config_path('app.php'));
    $ejemplo = (string) file_get_contents(base_path('.env.example'));

    expect($config)->toContain("env('APP_LOCALE', 'es')")
        ->and($config)->toContain("env('APP_FALLBACK_LOCALE', 'es')")
        ->and($ejemplo)->toContain('APP_LOCALE=es')
        ->and($ejemplo)->toContain('APP_FALLBACK_LOCALE=es');
});

it('los archivos de configuración que el .env necesita existen', function () {
    foreach (['app', 'session', 'filesystems', 'mail', 'services'] as $archivo) {
        expect(file_exists(config_path($archivo.'.php')))
            ->toBeTrue("Falta config/{$archivo}.php: su variable del .env se ignora en silencio");
    }
});
