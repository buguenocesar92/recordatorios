<?php

use App\Support\CredencialesDePlantilla;

/**
 * El candado que impide que la contraseña del «.env.example» llegue a producción
 * tiene que distinguir un arranque real de uno de instalación.
 *
 * `composer install` dispara `package:discover`, que arranca la aplicación
 * cuando todavía no hay «.env»: sin él, APP_ENV toma su valor por defecto, que
 * es «production», y el candado tumbaba el propio install —en el CI y en el
 * build de la imagen— con un error que hablaba de la base de datos y despistaba.
 * La señal de que no hay instalación que proteger es que no hay clave de
 * aplicación: sin ella este sistema no sirve una sola petición.
 */
it('no tumba la instalación: sin clave de aplicación no hay nada que proteger', function () {
    app()['env'] = 'production';
    config()->set('app.key', '');
    config()->set('database.default', 'mariadb');
    config()->set('database.connections.mariadb.password', 'sistema_pass');

    CredencialesDePlantilla::comprobar();
})->throwsNoExceptions();

it('en una instalación de verdad sí frena la contraseña de la plantilla', function () {
    app()['env'] = 'production';
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('database.default', 'mariadb');
    config()->set('database.connections.mariadb.password', 'sistema_pass');

    CredencialesDePlantilla::comprobar();
})->throws(RuntimeException::class, 'sigue siendo la del ejemplo');

it('fuera de producción no se mete', function () {
    app()['env'] = 'local';
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('database.default', 'mariadb');
    config()->set('database.connections.mariadb.password', 'sistema_pass');

    CredencialesDePlantilla::comprobar();
})->throwsNoExceptions();

it('en producción no arranca con el segundo factor apagado', function (mixed $valor) {
    app()['env'] = 'production';
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('database.default', 'mariadb');
    config()->set('database.connections.mariadb.password', 'una-de-verdad');
    config()->set('backup.backup.password', '');
    config()->set('mfa.enabled', $valor);
    config()->set('mfa.show_code', false);

    CredencialesDePlantilla::comprobar();
})->with(['false' => false, 'vacío' => '', 'nulo' => null])
    ->throws(RuntimeException::class, 'segundo factor está apagado');

it('en producción no arranca con el código del segundo factor en pantalla', function () {
    app()['env'] = 'production';
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('database.default', 'mariadb');
    config()->set('database.connections.mariadb.password', 'una-de-verdad');
    config()->set('backup.backup.password', '');
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', true);

    CredencialesDePlantilla::comprobar();
})->throws(RuntimeException::class, 'se muestra en pantalla');

it('en producción, con todo en orden, arranca', function () {
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

    CredencialesDePlantilla::comprobar();
})->throwsNoExceptions();

it('en producción no arranca con Turnstile vacío o con las claves de prueba', function (string $sitekey, string $secreto) {
    app()['env'] = 'production';
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('database.default', 'mariadb');
    config()->set('database.connections.mariadb.password', 'una-de-verdad');
    config()->set('backup.backup.password', '');
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', false);
    config()->set('waitlist.hash_salt', str_repeat('s', 32));
    config()->set('services.turnstile.sitekey', $sitekey);
    config()->set('services.turnstile.secret', $secreto);

    CredencialesDePlantilla::comprobar();
})->with([
    'vacías' => ['', ''],
    'secreto de prueba' => ['0x4AAAAAAAreal', '1x0000000000000000000000000000000AA'],
    'sitekey de prueba' => ['1x00000000000000000000AA', '0x4AAAAAAAsecretoreal'],
])->throws(RuntimeException::class, 'Turnstile');

it('en producción no arranca sin la sal de la lista de espera', function (string $sal) {
    app()['env'] = 'production';
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('database.default', 'mariadb');
    config()->set('database.connections.mariadb.password', 'una-de-verdad');
    config()->set('backup.backup.password', '');
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', false);
    config()->set('waitlist.hash_salt', $sal);

    CredencialesDePlantilla::comprobar();
})->with(['vacía' => '', 'corta' => 'corta'])
    ->throws(RuntimeException::class, 'WAITLIST_HASH_SALT');

it('en producción no arranca sin el hostname de Turnstile (sin él, un token de otro sitio pasaría)', function () {
    app()['env'] = 'production';
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('database.default', 'mariadb');
    config()->set('database.connections.mariadb.password', 'una-de-verdad');
    config()->set('backup.backup.password', '');
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', false);
    config()->set('waitlist.hash_salt', str_repeat('s', 32));
    config()->set('services.turnstile.sitekey', '0x4AAAAAAAreal');
    config()->set('services.turnstile.secret', '0x4AAAAAAAsecretoreal');
    config()->set('services.turnstile.hostname', '');

    CredencialesDePlantilla::comprobar();
})->throws(RuntimeException::class, 'TURNSTILE_HOSTNAME');
