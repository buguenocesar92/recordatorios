<?php

use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Muni\Shared\Privacidad\Contratos\TitularDeDatos;

/**
 * Lo que la Ley 21.719 le exige a este modelo.
 *
 * Estaba cubierto a medias, y es de lo que peor tolera un error: acá se decide
 * qué se le entrega a un vecino que ejerce su derecho de acceso, y qué se borra
 * cuando pide supresión. Un fallo no se ve en pantalla — se ve en una
 * fiscalización, o cuando alguien recibe datos que no debía.
 */
uses(RefreshDatabase::class);

covers(Persona::class);

/** Vía factory: es la que sabe qué campos exige la tabla (nombre_completo, entre otros). */
function personaDePrueba(array $extra = []): Persona
{
    return Persona::factory()->create(array_merge([
        'nombres' => 'Ana María',
        'apellidos' => 'Pérez Soto',
        'nombre_completo' => 'Ana María Pérez Soto',
        'tipo_documento' => 'RUN',
        'nro_documento' => '11.111.111-1',
        'telefono' => '+56911111111',
        'email' => 'ana@ejemplo.cl',
        'fecha_nacimiento' => '1985-06-15',
        'direccion' => 'Avenida Central 123',
        'documento_acreditado' => true,
    ], $extra));
}

it('cumple el contrato de titular de datos', function () {
    expect(personaDePrueba())->toBeInstanceOf(TitularDeDatos::class);
});

it('entrega al titular exactamente los campos declarados, y ninguno más', function () {
    $exportado = personaDePrueba()->exportarDatosPersonales();

    /*
     * La lista se declara campo por campo justamente para que una columna
     * interna que se agregue mañana —una marca de sincronización, el
     * identificador de otro sistema— no termine en el documento que se le
     * entrega a un vecino sin que nadie lo haya decidido. Fijar las claves acá
     * hace que agregar una columna al modelo no la filtre en silencio: si de
     * verdad corresponde entregarla, hay que cambiar esta prueba a propósito.
     */
    expect(array_keys($exportado))->toBe([
        'nombres',
        'apellidos',
        'tipo_documento',
        'numero_documento',
        'documento_acreditado',
        'telefono',
        'correo',
        'fecha_nacimiento',
        'direccion',
        'registrado_el',
    ]);

    expect($exportado['nombres'])->toBe('Ana María')
        ->and($exportado['correo'])->toBe('ana@ejemplo.cl')
        ->and($exportado['fecha_nacimiento'])->toBe('1985-06-15')
        ->and($exportado['documento_acreditado'])->toBeTrue();
});

it('la supresión borra de la base, no solo del objeto en memoria', function () {
    $persona = personaDePrueba();

    $persona->purgarDatosSensibles();

    // Se lee la fila cruda: un accessor o un atributo oculto podría hacer
    // parecer borrado algo que sigue guardado.
    $fila = DB::table('personas')->where('id', $persona->id)->first();

    foreach (['nombres', 'apellidos', 'tipo_documento',
        'nro_documento', 'telefono', 'email', 'fecha_nacimiento', 'direccion'] as $campo) {
        expect($fila->{$campo})->toBeNull("El campo «{$campo}» sobrevivió a la supresión");
    }

    // Este va vacío en vez de null porque la columna es NOT NULL y tiene
    // índice. Lo que importa es que no quede nada que identifique a nadie.
    expect($fila->nombre_completo)->toBe('');

    // Las columnas normalizadas las calcula la base a partir de las anteriores:
    // si sobrevivieran, el documento seguiría siendo buscable tras la supresión.
    expect($fila->nro_documento_norm)->toBeNull()
        ->and($fila->telefono_norm)->toBeNull();

    expect((bool) $fila->documento_acreditado)->toBeFalse();
});

it('la supresión conserva lo que prueba que ocurrió', function () {
    // La misma ley que obliga a borrar obliga a poder demostrar que se borró.
    // El identificador y las fechas no reconocen a nadie y sostienen esa
    // trazabilidad: si desaparecieran, no habría cómo acreditar la supresión.
    $persona = personaDePrueba();
    $id = $persona->id;

    $persona->purgarDatosSensibles();

    $fila = DB::table('personas')->where('id', $id)->first();

    expect($fila)->not->toBeNull('La fila desapareció: no queda rastro de que la supresión ocurrió')
        ->and($fila->created_at)->not->toBeNull();
});

it('anonimizar conserva el año de nacimiento y nada más', function () {
    $persona = personaDePrueba(['fecha_nacimiento' => '1985-06-15']);

    $persona->anonimizar();

    $fila = DB::table('personas')->where('id', $persona->id)->first();

    // El año permite decir «tantas personas de tal tramo etario usaron el
    // servicio»; el día y el mes acercan demasiado a señalar a una.
    expect($fila->fecha_nacimiento)->toStartWith('1985-01-01')
        ->and($fila->nombres)->toBeNull()
        ->and($fila->nro_documento)->toBeNull()
        ->and($fila->email)->toBeNull();
});

it('anonimizar a quien no tenía fecha de nacimiento no la inventa', function () {
    $persona = personaDePrueba(['fecha_nacimiento' => null]);

    $persona->anonimizar();

    $fila = DB::table('personas')->where('id', $persona->id)->first();

    expect($fila->fecha_nacimiento)->toBeNull();
});

it('lo rectificable no incluye lo que constata un funcionario', function () {
    $campos = personaDePrueba()->camposRectificables();

    // El derecho de rectificación no es un cheque en blanco sobre el registro:
    // alguien corrige su apellido mal escrito o su teléfono, no la marca de que
    // su documento fue acreditado en el mesón.
    expect($campos)->not->toContain('documento_acreditado')
        ->and($campos)->not->toContain('id')
        ->and($campos)->toContain('nombres')
        ->and($campos)->toContain('telefono');
});

it('identifica al titular por nombre y documento', function () {
    $persona = personaDePrueba();

    expect($persona->titularNombre())->toContain('Ana María')
        ->and($persona->titularNombre())->toContain('Pérez Soto')
        ->and($persona->titularDocumento())->toContain('11.111.111-1');
});

it('sin fecha de nacimiento devuelve null, que no significa adulto', function () {
    // Quién es niño, niña o adolescente lo decide el módulo que conoce la regla
    // legal. Devolver una fecha inventada acá haría que ese módulo clasifique
    // mal a alguien sin enterarse.
    // Documentos distintos: la tabla tiene índice único sobre el documento
    // normalizado, y repetirlo revienta (que es justamente lo que debe hacer).
    expect(personaDePrueba(['fecha_nacimiento' => null, 'nro_documento' => '22.222.222-2'])
        ->fechaNacimientoTitular())->toBeNull();

    expect(personaDePrueba(['nro_documento' => '33.333.333-3'])
        ->fechaNacimientoTitular())->not->toBeNull();
});
