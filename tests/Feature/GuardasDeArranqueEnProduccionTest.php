<?php

declare(strict_types=1);

use App\Support\CredencialesDePlantilla;

/**
 * Huecos de la guarda de arranque: lo que en producción tiene que estar bien
 * puesto o el sistema arranca abierto sin avisar.
 */
function produccionCompleta(): void
{
    app()['env'] = 'production';
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('database.default', 'mariadb');
    config()->set('database.connections.mariadb.password', 'una-de-verdad');
    config()->set('backup.backup.password', 'otra-de-verdad');
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', false);
    config()->set('waitlist.hash_salt', str_repeat('s', 32));
    config()->set('services.turnstile.sitekey', '0x4AAAAAAAreal');
    config()->set('services.turnstile.secret', '0x4AAAAAAAsecretoreal');
    config()->set('services.turnstile.hostname', 'recordatorios.example');
    config()->set('proxies.confiables', ['172.31.251.1']);
    config()->set('waitlist.purge_confirmed_after', '2027-06-30');
    config()->set('session.secure', true);
    config()->set('app.url', 'https://recordatorios.example');
    config()->set('panel.vpn_cidr', ['10.8.0.0/24']);
}

it('con todo bien puesto, arranca', function () {
    produccionCompleta();

    CredencialesDePlantilla::comprobar();
})->throwsNoExceptions();

it('sin VPN configurada sigue arrancando (panel cerrado para todos: falla cerrado)', function () {
    produccionCompleta();
    config()->set('panel.vpn_cidr', []);

    CredencialesDePlantilla::comprobar();
})->throwsNoExceptions();

it('no arranca si la cookie de sesión no es Secure', function (mixed $valor) {
    produccionCompleta();
    config()->set('session.secure', $valor);

    CredencialesDePlantilla::comprobar();
})->with(['nulo' => null, 'false' => false, 'vacío' => ''])->throws(RuntimeException::class, 'SESSION_SECURE_COOKIE');

it('no arranca con ADMIN_VPN_CIDR abierto a todo Internet o ilegible', function (array $cidr) {
    produccionCompleta();
    config()->set('panel.vpn_cidr', $cidr);

    CredencialesDePlantilla::comprobar();
})->with([
    'v4 /0' => [['0.0.0.0/0']],
    'v6 /0' => [['::/0']],
    'mezclado' => [['10.8.0.0/24', '0.0.0.0/0']],
    'ancho' => [['0.0.0.0/1']],
    'basura' => [['vpn']],
])->throws(RuntimeException::class, 'ADMIN_VPN_CIDR');

it('no arranca con APP_URL vacía, sin https o local', function (string $url) {
    produccionCompleta();
    config()->set('app.url', $url);

    CredencialesDePlantilla::comprobar();
})->with(['', 'http://recordatorios.example', 'https://localhost', 'https://127.0.0.1', 'https://app.localhost', 'no-es-url'])
    ->throws(RuntimeException::class, 'APP_URL');

it('en staging tampoco arranca sin TURNSTILE_HOSTNAME', function () {
    app()['env'] = 'staging';
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('services.turnstile.sitekey', '0x4AAAAAAAreal');
    config()->set('services.turnstile.secret', '0x4AAAAAAAsecretoreal');
    config()->set('services.turnstile.hostname', '');

    CredencialesDePlantilla::comprobar();
})->throws(RuntimeException::class, 'TURNSTILE_HOSTNAME');

it('el entrypoint de producción muestra el error real si la app no arranca, en vez de esperar a la base para siempre', function () {
    $script = (string) file_get_contents(base_path('docker/docker-entrypoint.prod.sh'));

    expect($script)->toContain('php artisan env')
        ->and($script)->not->toMatch('/until php artisan db:show >\/dev\/null 2>&1; do\s+sleep 2\s+done/');
});
