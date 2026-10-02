<?php

use App\Models\Persona;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * La persona es el titular de datos de la Ley 21.719, y estas pruebas fijan las
 * decisiones del esquema que no son obvias mirando la tabla: que el documento
 * puede faltar, que su unicidad es parcial, y que la normalización la hace la
 * base y no PHP.
 */
it('acepta personas sin documento, que es el caso común de un formulario público', function (): void {
    Persona::factory()->count(3)->create();

    expect(Persona::whereNull('nro_documento')->count())->toBe(3);
});

it('deja convivir a varias personas sin documento: nulos ilimitados bajo el índice único', function (): void {
    // Es lo que hace posible la unicidad PARCIAL. Si alguien cambiara el índice
    // por uno sobre `nro_documento` sin normalizar, o quitara el NULLIF de la
    // columna generada, este test se cae — que es justo lo que se quiere.
    Persona::factory()->count(5)->create();

    expect(Persona::count())->toBe(5);
});

it('impide dos personas con el mismo documento aunque esté escrito distinto', function (): void {
    Persona::factory()->conDocumento('12.345.678-9')->create();

    expect(fn () => Persona::factory()->conDocumento('123456789')->create())
        ->toThrow(QueryException::class);
});

it('normaliza el documento en la BASE, no en PHP', function (): void {
    $persona = Persona::factory()->conDocumento('12.345.678-9')->create();

    // Se lee crudo con el query builder: si la normalización viviera en un
    // mutator del modelo, este SELECT devolvería el valor sin normalizar y el
    // índice único no protegería a quien escriba por otra vía.
    $norm = DB::table('personas')->where('id', $persona->id)->value('nro_documento_norm');

    expect($norm)->toBe('123456789');
});

it('encuentra por documento sin importar cómo se escriba', function (): void {
    $persona = Persona::factory()->conDocumento('12.345.678-9')->create();

    foreach (['12.345.678-9', '123456789', '12345678-9'] as $buscado) {
        expect(Persona::porDocumento($buscado)->first()?->id)->toBe($persona->id);
    }
});

it('encuentra por canal, que es como se acredita quien nunca dejó documento', function (): void {
    $persona = Persona::factory()->create([
        'telefono' => '+56 9 1111 2222',
        'email' => 'vecina@example.cl',
    ]);

    // El teléfono se compara por sus dígitos, así que da igual cómo se escriba
    // —espacios, guiones, paréntesis— mientras sea el MISMO número.
    expect(Persona::porCanal('+56911112222')->first()?->id)->toBe($persona->id)
        ->and(Persona::porCanal('+56 9 1111-2222')->first()?->id)->toBe($persona->id)
        ->and(Persona::porCanal('vecina@example.cl')->first()?->id)->toBe($persona->id);
});

it('NO encuentra por un teléfono sin código de país, y eso es deliberado', function (): void {
    // «9 1111 2222» y «+56 9 1111 2222» son el mismo número para una persona, y
    // este scope no los une. Emparejarlos exigiría comparar por sufijo, y un
    // sufijo puede coincidir con el número de OTRA persona: el precio de
    // equivocarse es acreditar a alguien como titular de datos ajenos.
    //
    // La decisión correcta es normalizar al guardar —el sistema que genere este
    // scaffold sabe qué formato pide su formulario—, no adivinar al buscar.
    // Este test existe para que la limitación sea visible y no una sorpresa.
    Persona::factory()->create(['telefono' => '+56 9 1111 2222']);

    expect(Persona::porCanal('9 1111 2222')->first())->toBeNull();
});

it('NO fusiona a dos personas del mismo nombre sin documento', function (): void {
    // Sin documento el sistema no afirma identidad. Declararlas la misma sin
    // que nadie lo comprobara significa atribuirle a una los datos de la otra.
    Persona::factory()->count(2)->create(['nombre_completo' => 'María González']);

    expect(Persona::where('nombre_completo', 'María González')->count())->toBe(2);
});

it('marca aparte el documento acreditado del solamente escrito', function (): void {
    // Solo el acreditado debería salir hacia el registro federado: empujar al
    // padrón central a quien apenas dejó una consulta es un tratamiento que
    // nadie declaró.
    $escrito = Persona::factory()->conDocumento()->create();
    $verificado = Persona::factory()->acreditada()->create();

    expect($escrito->documento_acreditado)->toBeFalse()
        ->and($verificado->documento_acreditado)->toBeTrue();
});
