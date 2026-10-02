<?php

declare(strict_types=1);

// Los dos candados de configuración de `muni-graneros/laravel-muni-shared`
// v1.20.0: antes vivían solo como advertencia en un comentario que nadie hacía
// cumplir. Son traits de PHPUnit puro (no dependen de Pest), así que se agregan
// igual que los de `laravel-muni-candados` en CandadosDelEcosistemaTest.php.
use Muni\Shared\Testing\AssertPermisosNoSeQuedanPegados;
use Muni\Shared\Testing\AssertSegundoFactorNoSeRegala;

uses(AssertPermisosNoSeQuedanPegados::class, AssertSegundoFactorNoSeRegala::class);

it('los permisos no se quedan pegados entre peticiones de Octane', function () {
    // config/permission.php de este sistema ya trae
    // register_octane_reset_listener => true; este candado es lo que lo exige
    // en vez de confiar en que nadie lo vuelva a apagar sin darse cuenta.
    static::assertPermisosNoSeQuedanPegados();
});

it('en producción el segundo factor ni se apaga ni se regala', function () {
    // El entorno de testing enciende MFA_SHOW_CODE para que las pruebas lean el
    // código sin interceptar una notificación. Ese apagado es solo de
    // conveniencia para tests: el `.env.example` real trae MFA_SHOW_CODE=false.
    // Sin este `config()->set`, el candado leería el valor de conveniencia y
    // reportaría un falso «se regala en producción» —no escribe configuración,
    // así que es la prueba la que arma el valor que de verdad se despliega
    // antes de pedirle el veredicto—.
    config()->set('mfa.show_code', false);

    static::assertSegundoFactorNoSeRegala(enProduccion: true);
});
