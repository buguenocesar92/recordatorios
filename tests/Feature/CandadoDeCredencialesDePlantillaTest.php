<?php

use Muni\Shared\Seguridad\CredencialesDePlantilla;

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
