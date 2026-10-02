<?php

/**
 * SALVAGUARDA: la suite usa RefreshDatabase — si por configuración del entorno
 * la BD efectiva no es una BD de test, abortamos ANTES de borrar nada.
 * (Los env del contenedor viven en $_SERVER y phpunit <env force> NO los pisa:
 * sin esta guardia, RefreshDatabase puede borrar la BD de desarrollo.)
 */
require __DIR__.'/../vendor/autoload.php';

// Una config CACHEADA ignora los env de phpunit y apunta a la BD real.
if (file_exists(__DIR__.'/../bootstrap/cache/config.php')) {
    fwrite(STDERR, '
⛔ ABORTADO: existe bootstrap/cache/config.php — con config cacheada
'.
        'los tests ignorarían sistema_test. Ejecuta: php artisan config:clear

');
    exit(1);
}

$db = $_SERVER['DB_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '';
if (! str_ends_with($db, '_test')) {
    fwrite(STDERR, "\n⛔ ABORTADO: DB_DATABASE=«{$db}» no termina en _test.\n".
        "Los tests solo corren contra una BD de test aislada (make test).\n\n");
    exit(1);
}
