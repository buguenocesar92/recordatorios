<?php

/**
 * Verifica que el esquema real de la base de datos coincida con la arquitectura de datos definida.
 * Evita desvíos accidentales en migraciones futuras (schema drift).
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const ESQUEMA = [
    'users' => ['id', 'name', 'email', 'email_verified_at', 'password', 'two_factor_code', 'two_factor_expires_at', 'direccion', 'latitud', 'longitud', 'remember_token'],
    // Agregar tus tablas y columnas aquí
];

/** Llaves foráneas esperadas: "tabla.columna" => tabla_referenciada. */
const LLAVES_FORANEAS = [
    // 'puestos.feria_id' => 'ferias',
];

/** Columnas con índice único (UK). */
const CLAVES_UNICAS = [
    ['users', 'email'],
];

it('todas las tablas del diagrama existen con sus columnas exactas', function (string $tabla, array $esperadas) {
    expect(Schema::hasTable($tabla))->toBeTrue("Falta la tabla {$tabla}");

    $reales = collect(Schema::getColumnListing($tabla))
        ->reject(fn ($c) => in_array($c, ['created_at', 'updated_at'], true))
        ->values()
        ->all();

    expect($reales)->toEqualCanonicalizing($esperadas);
})->with(collect(ESQUEMA)->map(fn ($cols, $tabla) => [$tabla, $cols])->values()->all());

it('las llaves foráneas del diagrama existen y referencian la tabla correcta', function () {
    $fks = collect(DB::select(
        'SELECT table_name AS t, column_name AS c, referenced_table_name AS r
         FROM information_schema.key_column_usage
         WHERE table_schema = DATABASE() AND referenced_table_name IS NOT NULL'
    ))->mapWithKeys(fn ($f) => ["{$f->t}.{$f->c}" => $f->r]);

    // Sin esto, un proyecto que no haya llenado LLAVES_FORANEAS deja este test pasando
    // sin ejecutar una sola comprobación: aparece en verde y no vigila nada. Mejor que
    // diga en voz alta que está vacío.
    if (LLAVES_FORANEAS === []) {
        expect(true)->toBeTrue();
        test()->markTestSkipped(
            'LLAVES_FORANEAS está vacía: este candado no vigila nada hasta que el '
            .'proyecto declare sus llaves foráneas.'
        );
    }

    foreach (LLAVES_FORANEAS as $columna => $referencia) {
        expect($fks->get($columna))->toBe($referencia, "FK {$columna} debería apuntar a {$referencia}");
    }
});

it('las claves únicas (UK) están presentes', function () {
    $uniques = collect(DB::select(
        "SELECT table_name AS t, column_name AS c
         FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND non_unique = 0 AND index_name <> 'PRIMARY'"
    ))->map(fn ($u) => "{$u->t}.{$u->c}");

    foreach (CLAVES_UNICAS as [$tabla, $columna]) {
        expect($uniques->contains("{$tabla}.{$columna}"))
            ->toBeTrue("Falta índice único en {$tabla}.{$columna}");
    }
});
