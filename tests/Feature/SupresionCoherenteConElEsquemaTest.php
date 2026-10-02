<?php

/**
 * Lo que una supresión anula tiene que poder ser nulo.
 *
 * Nace de un defecto real: `purgarDatosSensibles()` ponía `nombre_completo` en
 * null y esa columna se declara NOT NULL, así que la supresión reventaba con un
 * error de integridad. Un vecino que ejercía su derecho a que borraran sus
 * datos recibía un fallo y los datos NO se borraban.
 *
 * Esta comprobación NO carga clases ni toca la base: lee los archivos como
 * texto. Es deliberado y costó llegar ahí.
 *
 *  - Consultando el esquema, pedir metadatos mientras otras pruebas tienen
 *    transacciones abiertas tumbaba 188 pruebas de otros archivos en licencias
 *    con «DeadlockException».
 *  - Cargando los modelos para preguntarles por reflexión, seguían cayendo 232:
 *    `class_exists()` dispara el autoload de cada modelo, con sus observadores y
 *    su `booted()`, y eso deja rastro en las pruebas que vienen después.
 *
 * Un candado que rompe la batería donde se instala no sirve, por correcto que
 * sea por dentro. Leyendo texto corre en milisegundos y no puede afectar a nada.
 *
 * El precio es que no ve un cambio de esquema hecho fuera de las migraciones.
 */

/**
 * Los campos que un método pone a `null`, leídos del archivo del modelo.
 *
 * @return list<string>
 */
function camposAnuladosEn(string $archivo, string $metodo): array
{
    $codigo = (string) file_get_contents($archivo);

    $inicio = mb_strpos($codigo, "function {$metodo}(");
    if ($inicio === false) {
        return [];
    }

    // Hasta el siguiente «function» o el final: basta para no leer de más.
    $siguiente = mb_strpos($codigo, "\n    public function ", $inicio + 1);
    $cuerpo = mb_substr($codigo, $inicio, $siguiente === false ? null : $siguiente - $inicio);

    preg_match_all("/'([a-z_0-9]+)'\s*=>\s*null/i", $cuerpo, $coincidencias);

    return array_values(array_unique($coincidencias[1]));
}

/**
 * Cómo declaran las migraciones cada columna del proyecto.
 *
 * Devuelve, por nombre de columna, si ALGUNA declaración admite null. Una misma
 * columna puede existir en varias tablas; basta que una la admita para que el
 * `update` no reviente, y esa es la duda que hay que resolver.
 *
 * @return array<string, array{admiteNull: bool, declaraciones: list<string>}>
 */
function columnasDeclaradas(): array
{
    static $mapa = null;

    if ($mapa !== null) {
        return $mapa;
    }

    $mapa = [];
    $tipos = 'string|text|char|date|dateTime|timestamp|boolean|integer|bigInteger|json|decimal|float|uuid|binary';

    foreach (glob(database_path('migrations/*.php')) ?: [] as $archivo) {
        foreach (file($archivo) ?: [] as $linea) {
            if (! preg_match("/->\s*(?:{$tipos})\s*\(\s*'([a-z_0-9]+)'/i", $linea, $m)) {
                continue;
            }

            $columna = $m[1];
            $admite = str_contains($linea, 'nullable()');

            $mapa[$columna] ??= ['admiteNull' => false, 'declaraciones' => []];
            $mapa[$columna]['admiteNull'] = $mapa[$columna]['admiteNull'] || $admite;
            $mapa[$columna]['declaraciones'][] = basename($archivo).($admite ? ' (nullable)' : ' (NOT NULL)');
        }
    }

    return $mapa;
}

it('ninguna supresión anula una columna declarada obligatoria', function () {
    /*
     * Se reconocen por el MÉTODO en el texto, no por la interfaz. En parte del
     * ecosistema los modelos cumplen el contrato de la Ley 21.719 sin declarar
     * `implements TitularDeDatos`, porque la versión instalada del paquete
     * compartido todavía no publica ese contrato: filtrar por la interfaz no
     * encontraría nada justo en esos proyectos.
     */
    $todos = collect(glob(app_path('Models/*.php')) ?: []);

    expect($todos)->not->toBeEmpty('No encontré modelos: la lectura de app/Models dejó de funcionar');

    /*
     * Cada modelo declara CÓMO se suprimen sus datos personales: o anonimiza
     * (método purgarDatosSensibles()/anonimizar()) o se borra la fila entera
     * (constante SUPRESION_POR_BORRADO, con su motivo en el docblock). Un modelo
     * nuevo que no diga ninguna de las dos cosas rompe esta prueba: el derecho
     * de supresión de la Ley 21.719 no puede quedar sin camino.
     */
    foreach ($todos as $archivo) {
        $codigo = (string) file_get_contents($archivo);

        expect(
            str_contains($codigo, 'function purgarDatosSensibles(')
            || str_contains($codigo, 'function anonimizar(')
            || str_contains($codigo, 'const SUPRESION_POR_BORRADO = true;')
        )->toBeTrue(basename($archivo).' no declara cómo se suprimen sus datos personales');
    }

    $modelos = $todos->filter(function (string $archivo): bool {
        $codigo = (string) file_get_contents($archivo);

        return str_contains($codigo, 'function purgarDatosSensibles(')
            || str_contains($codigo, 'function anonimizar(');
    });

    $declaradas = columnasDeclaradas();
    $revisados = 0;

    foreach ($modelos as $archivo) {
        $modelo = basename($archivo, '.php');

        foreach (['purgarDatosSensibles', 'anonimizar'] as $metodo) {
            foreach (camposAnuladosEn($archivo, $metodo) as $campo) {
                // Columnas que no declara ninguna migración del proyecto —las
                // que trae un paquete, por ejemplo— no se pueden juzgar acá.
                if (! isset($declaradas[$campo])) {
                    continue;
                }

                $revisados++;

                expect($declaradas[$campo]['admiteNull'])->toBeTrue(
                    "{$modelo}::{$metodo}() pone «{$campo}» en null, pero ninguna migración lo declara ".
                    'nullable: la supresión revienta y los datos NO se borran. Declarado en: '.
                    implode(', ', $declaradas[$campo]['declaraciones'])
                );
            }
        }
    }

    if ($modelos->isNotEmpty()) {
        expect($revisados)->toBeGreaterThan(0,
            'No se pudo juzgar ni un solo campo: o la lectura del código dejó de encontrarlos, '.
            'o ninguna columna que se anula la declara una migración de este proyecto'
        );
    }
});
